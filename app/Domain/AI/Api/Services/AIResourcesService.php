<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Database\Eloquent\Collection;

use App\Domain\Integrations\Services\IntegrationManager;
use App\Domain\Audit\Actions\LogAuditAction;
use App\Domain\Integrations\Services\GoogleTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;

class AIResourcesService
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private LogAuditAction $logAuditAction,
        private GoogleTokenService $googleTokenService,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function rename(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $uuid, string $name): array
    {
        $resource = $this->findByUuid($organization, $uuid);
        if (!$resource) {
            throw new Exception("Resource not found.", 404);
        }

        $integration = $resource->integration;
        if (!$integration || $integration->provider !== 'google_workspace') {
            throw new Exception("Apenas recursos do Google Workspace podem ser renomeados atualmente.", 400);
        }

        if ($integration->status !== 'connected' || !$integration->is_enabled) {
            throw new Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar este recurso.");
        }

        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}";
        $payload = [
            'name' => $name,
        ];

        $identity = $accessContext->getResolvedIdentity();

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url, $payload) {
            return Http::withToken($token)->patch($url, $payload);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        $resource->update([
            'name' => $name,
            'updated_by_provider_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->logAuditAction->execute('ai_rename_resource', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'old_name' => $resource->getOriginal('name'),
            'new_name' => $name
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => $resource->is_folder ? 'folder' : ($resource->resource_type ?? 'document'),
            'provider' => 'google_workspace',
        ];
    }

    public function move(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $uuid, string $destinationFolderUuid): array
    {
        $resource = $this->findByUuid($organization, $uuid);
        if (!$resource) {
            throw new Exception("Resource not found.", 404);
        }

        $destinationFolder = $this->findByUuid($organization, $destinationFolderUuid);
        if (!$destinationFolder) {
            throw new Exception("Destination folder not found.", 404);
        }

        if (!$destinationFolder->is_folder && $destinationFolder->mime_type !== 'application/vnd.google-apps.folder') {
            throw new Exception("Destination is not a folder.", 422);
        }

        $integration = $resource->integration;
        if (!$integration || $integration->provider !== 'google_workspace') {
            throw new Exception("Apenas recursos do Google Workspace podem ser movidos atualmente.", 400);
        }

        if ($integration->status !== 'connected' || !$integration->is_enabled) {
            throw new Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar este recurso.");
        }
        
        if (!$this->authorizationService->canAccessResource($user, $organization, $destinationFolder)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar a pasta de destino.");
        }

        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $destId = $destinationFolder->external_id;
        if (empty($destId)) {
            throw new Exception("Destination folder lacks an external identifier.", 400);
        }

        // Prevent moving folder into itself
        if ($fileId === $destId) {
            throw new Exception("Cannot move a folder into itself.", 422);
        }

        $identity = $accessContext->getResolvedIdentity();

        // 1. Fetch current parents
        $getUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields=parents";
        $getResponse = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($getUrl) {
            return Http::withToken($token)->get($getUrl);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$getResponse->successful()) {
            throw new Exception("Provider API Error when fetching parents: " . $getResponse->body(), $getResponse->status());
        }

        $currentParents = $getResponse->json('parents', []);
        
        $parentsToRemove = array_filter($currentParents, fn($p) => $p !== $destId);
        
        $queryParams = [];
        if (!in_array($destId, $currentParents)) {
            $queryParams['addParents'] = $destId;
        }
        if (!empty($parentsToRemove)) {
            $queryParams['removeParents'] = implode(',', $parentsToRemove);
        }

        if (empty($queryParams)) {
            // Already in destination and has no other parents
            return [
                'resource_uuid' => $resource->uuid,
                'name' => $resource->name,
                'type' => $resource->is_folder ? 'folder' : ($resource->resource_type ?? 'document'),
                'provider' => 'google_workspace',
                'destination_folder_resource_uuid' => $destinationFolder->uuid,
            ];
        }

        $patchUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?" . http_build_query($queryParams);
        
        $patchResponse = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($patchUrl) {
            return Http::withToken($token)->patch($patchUrl, []);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$patchResponse->successful()) {
            throw new Exception("Provider API Error: " . $patchResponse->body(), $patchResponse->status());
        }

        $oldParentExternalId = $resource->parent_external_id ?? (count($currentParents) > 0 ? $currentParents[0] : null);

        $resource->update([
            'parent_external_id' => $destId,
            'updated_by_provider_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->logAuditAction->execute('ai_move_resource', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'destination_folder_resource_uuid' => $destinationFolder->uuid,
            'old_parent_external_id' => $oldParentExternalId,
            'new_parent_external_id' => $destId
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => $resource->is_folder ? 'folder' : ($resource->resource_type ?? 'document'),
            'provider' => 'google_workspace',
            'destination_folder_resource_uuid' => $destinationFolder->uuid,
        ];
    }

    public function createFolder(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $name, ?string $parentResourceUuid = null): array
    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->where('is_enabled', true)
            ->first();

        if (!$integration) {
            throw new Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        $parentResource = null;
        if ($parentResourceUuid) {
            $parentResource = $this->findByUuid($organization, $parentResourceUuid);
            
            if (!$parentResource) {
                throw new Exception("Pasta pai não encontrada.", 404);
            }

            if (!$this->authorizationService->canAccessResource($user, $organization, $parentResource)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar a pasta pai.");
            }

            if ($parentResource->mime_type !== 'application/vnd.google-apps.folder') {
                throw new Exception("O recurso pai especificado não é uma pasta.", 422);
            }
        }

        $url = "https://www.googleapis.com/drive/v3/files";
        $payload = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];

        if ($parentResource && $parentResource->external_id) {
            $payload['parents'] = [$parentResource->external_id];
        }

        $identity = $accessContext->getResolvedIdentity();

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url, $payload) {
            return Http::withToken($token)->post($url, $payload);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        $googleData = $response->json();
        $googleFileId = $googleData['id'] ?? null;

        if (!$googleFileId) {
            throw new Exception("A API do Google não retornou o ID do arquivo.", 500);
        }

        $resource = IntegrationResource::create([
            'integration_id' => $integration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'folder',
            'external_id' => $googleFileId,
            'parent_external_id' => $parentResource ? $parentResource->external_id : null,
            'name' => $name,
            'mime_type' => 'application/vnd.google-apps.folder',
            'is_folder' => true,
            'created_by_provider_at' => now(),
            'updated_by_provider_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->logAuditAction->execute('ai_create_resource_folder', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'parent_uuid' => $parentResourceUuid
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => 'folder',
            'provider' => 'google_workspace',
            'parent_resource_uuid' => $parentResourceUuid,
        ];
    }

    /**
     * Search resources for the given organization.
     */
    public function search(Organization $organization, string $query, ?string $provider = null, ?string $type = null, int $limit = 50): Collection
    {
        $integrationIds = $organization->integrations()
            ->when($provider, function ($q) use ($provider) {
                // Map frontend provider names to enum cases if needed
                if ($provider === 'google') $provider = 'google_workspace';
                if ($provider === 'microsoft') $provider = 'microsoft_365';
                $q->where('provider', $provider);
            })
            ->pluck('id');

        $results = IntegrationResource::whereIn('integration_id', $integrationIds)
            ->when($type, function ($q) use ($type) {
                $q->where('resource_type', $type);
            })
            ->where(function ($q) use ($query) {
                if (!empty($query)) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%")
                      ->orWhere('mime_type', 'like', "%{$query}%");
                }
            })
            ->orderBy('updated_by_provider_at', 'desc')
            ->limit($limit)
            ->get();

        if ($results->isEmpty() && !empty($query)) {
            return $this->performFuzzySearch($integrationIds->toArray(), $query, $type, $limit);
        }

        return $results;
    }

    /**
     * Fallback to fuzzy searching when exact/LIKE matches fail.
     */
    private function performFuzzySearch(array $integrationIds, string $query, ?string $type, int $limit): Collection
    {
        // Limit candidates to avoid heavy PHP-side processing
        $candidates = IntegrationResource::whereIn('integration_id', $integrationIds)
            ->when($type, function ($q) use ($type) {
                $q->where('resource_type', $type);
            })
            ->orderBy('updated_by_provider_at', 'desc')
            ->limit(300)
            ->get();

        $queryTokens = $this->tokenize($query);
        if (empty($queryTokens)) {
            return new Collection();
        }

        $scoredCandidates = [];

        foreach ($candidates as $candidate) {
            $score = $this->calculateFuzzyScore($queryTokens, $candidate->name, $candidate->description);
            if ($score >= 60) {
                $candidate->fuzzy_score = $score;
                $scoredCandidates[] = $candidate;
            }
        }

        usort($scoredCandidates, function($a, $b) {
            return $b->fuzzy_score <=> $a->fuzzy_score;
        });

        return (new Collection($scoredCandidates))->take($limit);
    }

    private function normalizeText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }
        $text = Str::ascii($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function tokenize(?string $text): array
    {
        $normalized = $this->normalizeText($text);
        $words = explode(' ', $normalized);
        $stopWords = ['de', 'da', 'do', 'em', 'para', 'com', 'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'que', 'e', 'ou', 'por'];
        
        $tokens = [];
        foreach ($words as $word) {
            if (strlen($word) >= 2 && !in_array($word, $stopWords)) {
                $tokens[] = $word;
            }
        }
        return $tokens;
    }

    private function calculateFuzzyScore(array $queryTokens, ?string $name, ?string $description): int
    {
        $nameTokens = $this->tokenize($name);
        $descTokens = $this->tokenize($description);
        
        if (empty($nameTokens) && empty($descTokens)) {
            return 0;
        }

        $totalScore = 0;

        foreach ($queryTokens as $qToken) {
            $bestMatch = 0;
            
            foreach ($nameTokens as $tToken) {
                similar_text($qToken, $tToken, $percent);
                if (str_contains($tToken, $qToken) || str_contains($qToken, $tToken)) {
                    $percent = max($percent, 90.0);
                }
                if ($percent > $bestMatch) {
                    $bestMatch = $percent;
                }
            }
            
            $bestNameMatch = $bestMatch;
            
            if ($bestNameMatch < 80) {
                $bestDescMatch = 0;
                foreach ($descTokens as $tToken) {
                    similar_text($qToken, $tToken, $percent);
                    if (str_contains($tToken, $qToken) || str_contains($qToken, $tToken)) {
                        $percent = max($percent, 85.0);
                    }
                    if ($percent > $bestDescMatch) {
                        $bestDescMatch = $percent;
                    }
                }
                $bestMatch = max($bestNameMatch, $bestDescMatch * 0.8);
            }

            $totalScore += $bestMatch;
        }

        $averageScore = $totalScore / count($queryTokens);
        return (int) round($averageScore);
    }

    /**
     * Find a specific resource by UUID within the organization's scope.
     */
    public function findByUuid(Organization $organization, string $uuid): ?IntegrationResource
    {
        $integrationIds = $organization->integrations()->pluck('id');

        return IntegrationResource::whereIn('integration_id', $integrationIds)
            ->where('uuid', $uuid)
            ->first();
    }

    public function getContent(Organization $organization, string $uuid, ?string $userId = null, ?\App\Domain\Identities\Models\ExternalIdentity $identity = null): array
    {
        $resource = $this->findByUuid($organization, $uuid);
        if (!$resource) {
            throw new Exception("Resource not found or access denied.", 404);
        }

        $integration = $resource->integration;
        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $this->logAuditAction->execute('ai_read_resource_content', 'IntegrationResource', (string) $resource->id, [
            'provider' => $integration->provider,
            'uuid' => $uuid,
            'user_id' => $userId
        ]);

        if ($integration->provider === 'google_workspace' || $integration->provider === 'google') {
            return $this->extractGoogleContent($integration, $resource, $fileId, $identity);
        }

        throw new Exception("Content extraction not implemented for provider {$integration->provider}.", 501);
    }

    public function getFileStream(Organization $organization, string $uuid, ?string $userId = null, ?\App\Domain\Identities\Models\ExternalIdentity $identity = null)
    {
        $resource = $this->findByUuid($organization, $uuid);
        if (!$resource) {
            throw new Exception("Resource not found or access denied.", 404);
        }

        $integration = $resource->integration;
        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $this->logAuditAction->execute('ai_download_resource_file', 'IntegrationResource', (string) $resource->id, [
            'provider' => $integration->provider,
            'uuid' => $uuid,
            'user_id' => $userId
        ]);

        if ($integration->provider === 'google_workspace' || $integration->provider === 'google') {
            return $this->streamGoogleFile($integration, $resource, $fileId, $identity);
        }

        throw new Exception("File streaming not implemented for provider {$integration->provider}.", 501);
    }

    private function getValidToken($integration): string
    {
        if (empty($integration->access_token)) {
            throw new Exception("Integration lacks access token.", 401);
        }

        // Test token validity simply by using it, we will auto-refresh if 401 occurs
        return $integration->access_token;
    }

    private function handleGoogleApiCall($integration, callable $apiCall)
    {
        $response = $apiCall($integration->access_token);

        if ($response->status() === 401) {
            // Token expired, refresh
            $connector = $this->integrationManager->getConnector($integration->provider);
            if ($connector->refreshToken($integration->organization)) {
                $integration->refresh();
                $response = $apiCall($integration->access_token); // Retry
            }
        }

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        return $response;
    }

    private function extractGoogleContent($integration, $resource, string $fileId, ?\App\Domain\Identities\Models\ExternalIdentity $identity = null): array
    {
        $mime = $resource->mime_type;
        $exportMime = null;
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";

        // Mapeamento para exportação (Google Native Formats)
        if ($mime === 'application/vnd.google-apps.document') {
            $exportMime = 'text/plain';
        } elseif ($mime === 'application/vnd.google-apps.spreadsheet') {
            $exportMime = 'text/csv'; // CSV is structured and readable
        } elseif ($mime === 'application/vnd.google-apps.presentation') {
            $exportMime = 'text/plain';
        }

        if ($exportMime) {
            $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=" . urlencode($exportMime);
        } elseif (in_array($mime, ['text/plain', 'text/csv', 'application/json'])) {
            // alt=media is already set for direct download of text files
        } else {
            throw new Exception("This resource format ({$mime}) cannot be safely extracted as text. Use the /file endpoint for binary/multimodal processing.", 415);
        }

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url) {
            return Http::withToken($token)->get($url);
        }, $identity, ['https://www.googleapis.com/auth/drive.readonly']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        return [
            'uuid' => $resource->uuid,
            'name' => $resource->name,
            'mime_type' => $resource->mime_type,
            'content_type' => $exportMime ?: 'text',
            'content' => $response->body(),
        ];
    }

    private function streamGoogleFile($integration, $resource, string $fileId, ?\App\Domain\Identities\Models\ExternalIdentity $identity = null)
    {
        $mime = $resource->mime_type;
        // Se for um arquivo nativo do Google, o download binário direto não funciona. Precisamos exportar como PDF.
        if (str_starts_with($mime, 'application/vnd.google-apps.')) {
            $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=application/pdf";
            $mime = 'application/pdf';
        } else {
            $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
        }

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url) {
            return Http::withToken($token)->get($url);
        }, $identity, ['https://www.googleapis.com/auth/drive.readonly']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        return response($response->body(), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $resource->name) . '"'
        ]);
    }

    /**
     * Read multiple resources, structuring success and error for each.
     */
    public function readMultipleResources(array $resourceUuids, Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext): array
    {
        $results = [];

        foreach ($resourceUuids as $uuid) {
            try {
                $resource = $this->findByUuid($organization, $uuid);
                
                if (!$resource) {
                    $results[] = [
                        'resource_uuid' => $uuid,
                        'success' => false,
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => 'Recurso não encontrado.'
                    ];
                    continue;
                }

                if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
                    $results[] = [
                        'resource_uuid' => $uuid,
                        'success' => false,
                        'code' => 'ACCESS_DENIED',
                        'message' => 'Você não possui permissão para acessar este recurso.'
                    ];
                    continue;
                }

                $contentData = $this->getContent($organization, $uuid, $user->id, $accessContext->getResolvedIdentity());
                
                // Mapeia os dados do conteúdo individual para a resposta da Tool de múltiplos
                $results[] = [
                    'resource_uuid' => $uuid,
                    'success' => true,
                    'name' => $contentData['name'] ?? null,
                    'mime_type' => $contentData['mime_type'] ?? null,
                    'content_type' => $contentData['content_type'] ?? null,
                    'content' => $contentData['content'] ?? null,
                    'truncated' => $contentData['truncated'] ?? false,
                ];
                
            } catch (\Exception $e) {
                // Same logic as AIResourcesController::handleException
                $code = $e->getCode();
                
                if (is_string($code) && !empty($code)) {
                    $errorCode = $code;
                } elseif (is_numeric($code) && $code > 0) {
                    $errorCode = (string)$code;
                } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $errorCode = 'ACCESS_DENIED';
                } else {
                    $errorCode = 'INTERNAL_ERROR';
                }

                $results[] = [
                    'resource_uuid' => $uuid,
                    'success' => false,
                    'code' => $errorCode,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Generate a temporary download URL for multimodal processing.
     */
    public function readResourceFile(string $resourceUuid, Organization $organization, \App\Domain\Identity\Models\User $user): array
    {
        $resource = $this->findByUuid($organization, $resourceUuid);

        if (!$resource) {
            return [
                'success' => false,
                'code' => 'RESOURCE_NOT_FOUND',
            ];
        }

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
            return [
                'success' => false,
                'code' => 'ACCESS_DENIED',
            ];
        }

        $temporaryDownload = \App\Domain\Resources\Models\TemporaryResourceDownload::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'integration_resource_id' => $resource->id,
            'expires_at' => now()->addHour(),
        ]);

        return [
            'success' => true,
            'data' => [
                'resource_uuid' => $resource->uuid,
                'filename' => $resource->name,
                'mime_type' => $resource->mime_type ?? 'application/octet-stream',
                'size' => $resource->size,
                'file_url' => url('/api/ai/resources/file/download/' . $temporaryDownload->uuid),
            ]
        ];
    }

    /**
     * Faz upload de um arquivo para o Google Drive e cria o IntegrationResource local.
     *
     * Fluxo:
     * validar integração → resolver parent opcional → enviar para o Google (multipart upload)
     * → confirmar resposta → criar IntegrationResource → auditoria → retornar
     *
     * IMPORTANTE: IntegrationResource só é criado APÓS o Google confirmar o upload.
     * Se o banco falhar após o Google aceitar, o erro é logado para reconciliação manual.
     */
    public function upload(
        Organization $organization,
        \App\Domain\Identity\Models\User $user,
        \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext,
        \Illuminate\Http\UploadedFile $file,
        ?string $parentResourceUuid = null
    ): array {
        // ── 1. Resolver integração ────────────────────────────────────────────
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->where('is_enabled', true)
            ->first();

        if (!$integration) {
            throw new Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        // ── 2. Resolver pasta de destino (opcional) ───────────────────────────
        $parentResource = null;
        if ($parentResourceUuid) {
            $parentResource = $this->findByUuid($organization, $parentResourceUuid);

            if (!$parentResource) {
                throw new Exception("Pasta de destino não encontrada.", 404);
            }

            if (!$this->authorizationService->canAccessResource($user, $organization, $parentResource)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar a pasta de destino.");
            }

            // Validar que o destino é de fato uma pasta
            if (!$parentResource->is_folder && $parentResource->mime_type !== 'application/vnd.google-apps.folder') {
                throw new Exception("O recurso de destino especificado não é uma pasta.", 422);
            }
        }

        // ── 3. Obter informações seguras do arquivo (via Laravel/Symfony) ─────
        // Nunca confiar apenas no MIME enviado pelo cliente.
        // O Laravel usa Symfony\Component\HttpFoundation\File\UploadedFile que detecta o MIME real.
        $originalName  = $file->getClientOriginalName();
        $detectedMime  = $file->getMimeType(); // MIME detectado pelo servidor
        $extension     = $file->getClientOriginalExtension();
        $sizeBytes     = $file->getSize();

        // Sanitização segura do nome: remove barras e caracteres de traversal,
        // mas preserva o nome original e a extensão.
        $safeName = preg_replace('/[\/\\\0]/', '_', $originalName);
        if (empty(trim($safeName))) {
            $safeName = 'arquivo_' . now()->timestamp . ($extension ? ".{$extension}" : '');
        }

        // ── 4. Montar metadata e fazer upload multipart para o Google Drive ───
        // Multipart Upload (até 5MB) ou para arquivos maiores o Google aceita até 5MB
        // no corpo. Para 50 MB usamos multipart com o Content-Length correto.
        // A API de multipart do Google aceita arquivos até seu limite de upload da conta.
        // Ref: https://developers.google.com/drive/api/guides/manage-uploads#multipart
        $metadata = ['name' => $safeName];
        if ($parentResource && $parentResource->external_id) {
            $metadata['parents'] = [$parentResource->external_id];
        }

        // Campos que queremos que o Google retorne após a criação
        $fields = 'id,name,mimeType,parents,size,modifiedTime,createdTime,webViewLink,iconLink,owners,shared';

        $identity = $accessContext->getResolvedIdentity();
        $fileContent = $file->get(); // Lê o conteúdo do arquivo temporário

        $response = $this->googleTokenService->executeWithRetry(
            $integration,
            function ($token) use ($metadata, $fileContent, $detectedMime, $fields) {
                // Multipart upload usando o endpoint oficial da Google Drive API
                return Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'multipart/related; boundary=nodal_boundary'])
                    ->send('POST', "https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields={$fields}", [
                        'body' => $this->buildMultipartBody($metadata, $fileContent, $detectedMime),
                    ]);
            },
            $identity,
            ['https://www.googleapis.com/auth/drive']
        );

        // Descartar o conteúdo da memória imediatamente após o upload
        unset($fileContent);

        if (!$response->successful()) {
            throw new Exception("Falha ao enviar arquivo para o Google Drive: " . $response->body(), $response->status());
        }

        $googleData = $response->json();
        $googleFileId = $googleData['id'] ?? null;

        if (!$googleFileId) {
            throw new Exception("A API do Google não retornou o ID do arquivo após o upload.", 500);
        }

        // ── 5. Mapear tipo do recurso pelo MIME (reutilizando o mapeamento do projeto) ─
        $resourceType = $this->mapMimeToResourceType($detectedMime);

        // ── 6. Criar IntegrationResource local ───────────────────────────────
        // IMPORTANTE: Só chegamos aqui se o Google confirmou o upload (response->successful).
        // Se o banco falhar agora, capturamos o erro, auditamos e retornamos 500 sem fingir sucesso.
        $googleOwners      = $googleData['owners'] ?? [];
        $googleModifiedTime = $googleData['modifiedTime'] ?? null;
        $googleCreatedTime  = $googleData['createdTime'] ?? null;
        $googleParents     = $googleData['parents'] ?? [];

        try {
            $resource = IntegrationResource::create([
                'integration_id'        => $integration->id,
                'uuid'                  => (string) Str::uuid(),
                'provider'              => 'google_workspace',
                'resource_type'         => $resourceType->value,
                'external_id'           => $googleFileId,
                'parent_external_id'    => $parentResource ? $parentResource->external_id : ($googleParents[0] ?? null),
                'name'                  => $safeName,
                'mime_type'             => $detectedMime,
                'is_folder'             => false,
                'is_shared'             => $googleData['shared'] ?? false,
                'url'                   => $googleData['webViewLink'] ?? null,
                'icon'                  => $googleData['iconLink'] ?? null,
                'owner_name'            => $googleOwners[0]['displayName'] ?? null,
                'owner_email'           => $googleOwners[0]['emailAddress'] ?? null,
                'size'                  => $sizeBytes,
                'created_by_provider_at' => $googleCreatedTime ? \Carbon\Carbon::parse($googleCreatedTime) : now(),
                'updated_by_provider_at' => $googleModifiedTime ? \Carbon\Carbon::parse($googleModifiedTime) : now(),
                'last_synced_at'        => now(),
            ]);
        } catch (\Exception $dbException) {
            // CASO: Google upload = sucesso, Banco local = falha.
            // O arquivo JÁ EXISTE no Google Drive. Não deletamos automaticamente
            // (pode haver implicações de negócio ou o usuário pode precisar recuperar).
            // Auditamos o evento para reconciliação posterior e retornamos erro.
            \Illuminate\Support\Facades\Log::error('Upload para o Google Drive concluído, mas falha ao persistir IntegrationResource localmente.', [
                'google_file_id' => $googleFileId,
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'filename' => $safeName,
                'mime_type' => $detectedMime,
                'db_error' => $dbException->getMessage(),
            ]);

            $this->logAuditAction->execute('ai_upload_resource_partial_failure', 'Integration', (string) $integration->id, [
                'provider'              => 'google_workspace',
                'user_id'               => $user->id,
                'filename'              => $safeName,
                'mime_type'             => $detectedMime,
                'size_bytes'            => $sizeBytes,
                'parent_resource_uuid'  => $parentResourceUuid,
                'note'                  => 'Arquivo criado no Google Drive, mas falhou ao salvar localmente. Requer reconciliação manual.',
            ]);

            throw new Exception(
                "O arquivo foi enviado ao Google Drive, mas ocorreu uma falha ao registrá-lo no Nodal. Contate o suporte com o nome do arquivo e horário da operação.",
                500
            );
        }

        // ── 7. Auditoria de sucesso ───────────────────────────────────────────
        $this->logAuditAction->execute('ai_upload_resource', 'IntegrationResource', (string) $resource->id, [
            'provider'              => 'google_workspace',
            'resource_uuid'         => $resource->uuid,
            'user_id'               => $user->id,
            'filename'              => $safeName,
            'mime_type'             => $detectedMime,
            'size_bytes'            => $sizeBytes,
            'parent_resource_uuid'  => $parentResourceUuid,
        ]);

        return [
            'resource_uuid'        => $resource->uuid,
            'name'                 => $resource->name,
            'type'                 => $resourceType->value,
            'mime_type'            => $resource->mime_type,
            'provider'             => 'google_workspace',
            'parent_resource_uuid' => $parentResourceUuid,
        ];
    }

    /**
     * Monta o body multipart/related para a Google Drive API.
     * Segue a especificação oficial:
     * https://developers.google.com/drive/api/guides/manage-uploads#multipart
     */
    private function buildMultipartBody(array $metadata, string $fileContent, string $mimeType): string
    {
        $boundary = 'nodal_boundary';
        $metaJson = json_encode($metadata);

        return "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . "{$metaJson}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$mimeType}\r\n\r\n"
            . "{$fileContent}\r\n"
            . "--{$boundary}--";
    }

    /**
     * Mapeia um MIME type para o ResourceType do projeto.
     * Reutiliza a mesma lógica do GoogleDriveSyncService para consistência.
     */
    private function mapMimeToResourceType(string $mimeType): \App\Domain\Resources\Enums\ResourceType
    {
        return match (true) {
            $mimeType === 'application/pdf'                                                         => \App\Domain\Resources\Enums\ResourceType::PDF,
            $mimeType === 'application/vnd.google-apps.folder'                                      => \App\Domain\Resources\Enums\ResourceType::FOLDER,
            $mimeType === 'application/vnd.google-apps.document'                                    => \App\Domain\Resources\Enums\ResourceType::DOCUMENT,
            $mimeType === 'application/vnd.google-apps.spreadsheet'                                 => \App\Domain\Resources\Enums\ResourceType::SPREADSHEET,
            $mimeType === 'application/vnd.google-apps.presentation'                                => \App\Domain\Resources\Enums\ResourceType::PRESENTATION,
            $mimeType === 'application/vnd.google-apps.form'                                        => \App\Domain\Resources\Enums\ResourceType::FORM,
            $mimeType === 'application/vnd.google-apps.drawing'                                     => \App\Domain\Resources\Enums\ResourceType::DRAWING,
            str_starts_with($mimeType, 'image/')                                                    => \App\Domain\Resources\Enums\ResourceType::IMAGE,
            str_starts_with($mimeType, 'video/')                                                    => \App\Domain\Resources\Enums\ResourceType::VIDEO,
            str_starts_with($mimeType, 'audio/')                                                    => \App\Domain\Resources\Enums\ResourceType::AUDIO,
            // Documentos Office e outros formatos comuns → document (arquivo genérico)
            in_array($mimeType, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
                'application/rtf',
            ])                                                                                      => \App\Domain\Resources\Enums\ResourceType::DOCUMENT,
            default                                                                                 => \App\Domain\Resources\Enums\ResourceType::OTHER,
        };
    }
}


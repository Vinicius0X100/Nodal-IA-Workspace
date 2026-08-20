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
}

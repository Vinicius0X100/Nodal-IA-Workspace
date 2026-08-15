<?php

namespace App\Domain\AI\Api\Services;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Database\Eloquent\Collection;

use App\Domain\Integrations\Services\IntegrationManager;
use App\Domain\Audit\Actions\LogAuditAction;
use App\Domain\Integrations\Services\GoogleTokenService;
use Illuminate\Support\Facades\Http;
use Exception;

class AIResourcesService
{
    public function __construct(
        private IntegrationManager $integrationManager,
        private LogAuditAction $logAuditAction,
        private GoogleTokenService $googleTokenService,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}
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

        return IntegrationResource::whereIn('integration_id', $integrationIds)
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

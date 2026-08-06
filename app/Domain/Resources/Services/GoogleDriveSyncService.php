<?php

namespace App\Domain\Resources\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Repositories\ResourceRepository;
use Carbon\Carbon;

class GoogleDriveSyncService
{
    public function __construct(
        private ResourceRepository $resourceRepository
    ) {
    }

    /**
     * Sincroniza os recursos (metadados) do Google Workspace.
     */
    public function sync(Integration $integration): void
    {
        if (!$integration->access_token) {
            \Illuminate\Support\Facades\Log::warning("Cannot sync Google Drive: No access token for integration {$integration->id}");
            return;
        }

        $pageToken = null;
        $fields = 'nextPageToken, files(id, name, mimeType, webViewLink, iconLink, owners, parents, modifiedTime, createdTime, size, shortcutDetails, shared)';
        
        do {
            $response = \Illuminate\Support\Facades\Http::withToken($integration->access_token)
                ->get('https://www.googleapis.com/drive/v3/files', [
                    'pageSize' => 100,
                    'fields' => $fields,
                    'pageToken' => $pageToken,
                    'supportsAllDrives' => 'true', // Importante para Shared Drives
                    'includeItemsFromAllDrives' => 'true',
                ]);

            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::error("Failed to fetch Google Drive files: " . $response->body());
                \App\Domain\Integrations\Models\IntegrationLog::create([
                    'integration_id' => $integration->id,
                    'event' => 'sync_drive',
                    'status' => 'error',
                    'message' => 'Falha ao sincronizar Google Drive: ' . $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            $files = $data['files'] ?? [];
            $pageToken = $data['nextPageToken'] ?? null;

            $resourcesToUpsert = [];

            foreach ($files as $item) {
                $typeInfo = $this->determineResourceType($item['mimeType'] ?? '', $item);
                
                $resourcesToUpsert[] = [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'integration_id' => $integration->id,
                    'provider' => Provider::GOOGLE_WORKSPACE->value,
                    'resource_type' => $typeInfo['type']->value,
                    'external_id' => $item['id'],
                    'parent_external_id' => $item['parents'][0] ?? null,
                    'name' => $item['name'] ?? 'Untitled',
                    'description' => null,
                    'mime_type' => $item['mimeType'] ?? null,
                    'url' => $item['webViewLink'] ?? null,
                    'icon' => $item['iconLink'] ?? null,
                    'owner_name' => $item['owners'][0]['displayName'] ?? null,
                    'owner_email' => $item['owners'][0]['emailAddress'] ?? null,
                    'is_folder' => $typeInfo['is_folder'],
                    'is_shared' => $item['shared'] ?? false,
                    'size' => $item['size'] ?? null,
                    'created_by_provider_at' => isset($item['createdTime']) ? Carbon::parse($item['createdTime']) : null,
                    'updated_by_provider_at' => isset($item['modifiedTime']) ? Carbon::parse($item['modifiedTime']) : null,
                    'last_synced_at' => now(),
                    'metadata_json' => json_encode(['shortcutDetails' => $item['shortcutDetails'] ?? null]),
                ];
            }

            if (!empty($resourcesToUpsert)) {
                $this->resourceRepository->upsertResources($resourcesToUpsert);
            }

        } while ($pageToken);
        
        \App\Domain\Integrations\Models\IntegrationLog::create([
            'integration_id' => $integration->id,
            'event' => 'sync_drive',
            'status' => 'success',
            'message' => 'Sincronização de documentos do Google Drive concluída.',
        ]);
    }

    private function determineResourceType(string $mimeType, array $item): array
    {
        $isFolder = false;
        $type = ResourceType::OTHER;

        switch ($mimeType) {
            case 'application/vnd.google-apps.folder':
                $type = ResourceType::FOLDER;
                $isFolder = true;
                break;
            case 'application/vnd.google-apps.document':
                $type = ResourceType::DOCUMENT;
                break;
            case 'application/vnd.google-apps.spreadsheet':
                $type = ResourceType::SPREADSHEET;
                break;
            case 'application/vnd.google-apps.presentation':
                $type = ResourceType::PRESENTATION;
                break;
            case 'application/vnd.google-apps.form':
                $type = ResourceType::FORM;
                break;
            case 'application/vnd.google-apps.drawing':
                $type = ResourceType::DRAWING;
                break;
            case 'application/vnd.google-apps.shortcut':
                $type = ResourceType::SHORTCUT;
                break;
            case 'application/pdf':
                $type = ResourceType::PDF;
                break;
            default:
                if (str_starts_with($mimeType, 'image/')) {
                    $type = ResourceType::IMAGE;
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $type = ResourceType::VIDEO;
                } elseif (str_starts_with($mimeType, 'audio/')) {
                    $type = ResourceType::AUDIO;
                }
                break;
        }

        return ['type' => $type, 'is_folder' => $isFolder];
    }
}

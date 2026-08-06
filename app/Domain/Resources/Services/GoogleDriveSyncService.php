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
        // 1. Obter o token de acesso usando as credenciais da integração ($integration->credentials)
        // 2. Instanciar o Google_Client e Google_Service_Drive
        
        // Aqui simulamos as chamadas paginadas à API do Google Drive usando listFiles()
        // Campos que buscaríamos na API: id, name, mimeType, webViewLink, iconLink, owners, parents, modifiedTime, createdTime, size, shortcutDetails
        
        // Exemplo de como transformaríamos os itens para upsert
        $resourcesToUpsert = [];
        
        // --- INÍCIO DO MOCK / BOILERPLATE DE CHAMADA DA API ---
        $items = $this->fetchFilesFromGoogleApiMock($integration);

        foreach ($items as $item) {
            $typeInfo = $this->determineResourceType($item['mimeType'], $item);
            
            $resourcesToUpsert[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'integration_id' => $integration->id,
                'provider' => Provider::GOOGLE_WORKSPACE->value,
                'resource_type' => $typeInfo['type']->value,
                'external_id' => $item['id'],
                'parent_external_id' => $item['parents'][0] ?? null,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'mime_type' => $item['mimeType'],
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
            
            // Faz upsert em chunks de 100 para evitar timeout/memória
            if (count($resourcesToUpsert) >= 100) {
                $this->resourceRepository->upsertResources($resourcesToUpsert);
                $resourcesToUpsert = [];
            }
        }

        // Upsert final
        if (!empty($resourcesToUpsert)) {
            $this->resourceRepository->upsertResources($resourcesToUpsert);
        }
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

    /**
     * Mock function para simular a busca.
     * Na implementação real, usaremos o Google_Service_Drive e paginação (pageToken).
     */
    private function fetchFilesFromGoogleApiMock(Integration $integration): array
    {
        return [
            [
                'id' => 'folder_123',
                'name' => 'Projetos 2026',
                'mimeType' => 'application/vnd.google-apps.folder',
                'webViewLink' => 'https://drive.google.com/drive/folders/folder_123',
                'iconLink' => 'https://example.com/folder_icon.png',
                'parents' => [],
                'owners' => [['displayName' => 'Vinicius Aquino', 'emailAddress' => 'vinicius@nodal.com']],
                'shared' => true,
                'createdTime' => '2026-01-01T10:00:00Z',
                'modifiedTime' => '2026-08-01T12:00:00Z',
            ],
            [
                'id' => 'doc_456',
                'name' => 'Planejamento Q3',
                'mimeType' => 'application/vnd.google-apps.document',
                'webViewLink' => 'https://docs.google.com/document/d/doc_456/edit',
                'iconLink' => 'https://example.com/doc_icon.png',
                'parents' => ['folder_123'],
                'owners' => [['displayName' => 'Vinicius Aquino', 'emailAddress' => 'vinicius@nodal.com']],
                'shared' => false,
                'createdTime' => '2026-07-01T10:00:00Z',
                'modifiedTime' => '2026-08-05T12:00:00Z',
            ]
        ];
    }
}

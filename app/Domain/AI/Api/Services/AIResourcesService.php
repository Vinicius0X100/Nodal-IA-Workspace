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

    public function createSpreadsheet(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $name, ?string $parentResourceUuid = null): array
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

            if ($parentResource->mime_type !== 'application/vnd.google-apps.folder' && !$parentResource->is_folder) {
                throw new Exception("O recurso pai especificado não é uma pasta.", 422);
            }
        }

        $url = "https://www.googleapis.com/drive/v3/files";
        $payload = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
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

        try {
            $resource = IntegrationResource::create([
                'integration_id' => $integration->id,
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'provider' => \App\Domain\Resources\Enums\Provider::GOOGLE_WORKSPACE->value,
                'resource_type' => \App\Domain\Resources\Enums\ResourceType::SPREADSHEET->value,
                'external_id' => $googleFileId,
                'parent_external_id' => $parentResource ? $parentResource->external_id : null,
                'name' => $name,
                'mime_type' => 'application/vnd.google-apps.spreadsheet',
                'is_folder' => false,
                'created_by_provider_at' => now(),
                'updated_by_provider_at' => now(),
                'last_synced_at' => now(),
            ]);
        } catch (\Exception $dbException) {
            // Compensação: tenta deletar o arquivo no Google caso a persistência falhe
            try {
                $deleteUrl = "https://www.googleapis.com/drive/v3/files/{$googleFileId}";
                $this->googleTokenService->executeWithRetry($integration, function ($token) use ($deleteUrl) {
                    return Http::withToken($token)->delete($deleteUrl);
                }, $identity, ['https://www.googleapis.com/auth/drive']);
                
                $this->logAuditAction->execute('ai_create_spreadsheet_sync_failed_rollback', 'IntegrationResource', $googleFileId, [
                    'provider' => 'google_workspace',
                    'user_id' => $user->id,
                    'name' => $name,
                    'error' => $dbException->getMessage(),
                ]);
            } catch (\Exception $rollbackException) {
                $this->logAuditAction->execute('ai_create_spreadsheet_sync_failed_orphan', 'IntegrationResource', $googleFileId, [
                    'provider' => 'google_workspace',
                    'user_id' => $user->id,
                    'name' => $name,
                    'error' => 'Rollback falhou: ' . $rollbackException->getMessage() . ' | Erro DB: ' . $dbException->getMessage(),
                ]);
            }

            throw new Exception("A planilha foi criada no Google, mas ocorreu um erro interno ao salvá-la no sistema.", 500);
        }

        $this->logAuditAction->execute('ai_create_resource_spreadsheet', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'parent_uuid' => $parentResourceUuid
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => \App\Domain\Resources\Enums\ResourceType::SPREADSHEET->value,
            'provider' => \App\Domain\Resources\Enums\Provider::GOOGLE_WORKSPACE->value,
            'parent_resource_uuid' => $parentResourceUuid,
        ];
    }

    public function formatSpreadsheet(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $uuid, array $operations): array
    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->where('is_enabled', true)
            ->first();

        if (!$integration) {
            throw new \Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        $resource = $this->findByUuid($organization, $uuid);

        if (!$resource) {
            throw new \Exception("Resource not found.", 404);
        }

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource, $accessContext)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar este recurso.");
        }

        if ($resource->resource_type !== \App\Domain\Resources\Enums\ResourceType::SPREADSHEET) {
            throw new \Exception("Este recurso não é uma planilha.", 422);
        }

        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new \Exception("Resource lacks an external identifier.", 400);
        }

        $identity = $accessContext->getResolvedIdentity();
        
        // Obter metadata da planilha para resolver sheetIds
        $metadataUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$fileId}?fields=sheets(properties(sheetId,title,index))";
        $metadataResponse = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($metadataUrl) {
            return \Illuminate\Support\Facades\Http::withToken($token)->get($metadataUrl);
        }, $identity, ['https://www.googleapis.com/auth/drive']);
        
        if (!$metadataResponse->successful()) {
            throw new \Exception("Erro ao obter metadados da planilha: " . $metadataResponse->body(), $metadataResponse->status());
        }

        $sheets = $metadataResponse->json('sheets') ?? [];
        $sheetTitleToId = [];
        $firstSheetId = null;

        // Ordenar pelo índice para garantir que pegamos a primeira aba real
        usort($sheets, fn($a, $b) => ($a['properties']['index'] ?? 0) <=> ($b['properties']['index'] ?? 0));

        foreach ($sheets as $idx => $sheet) {
            $sheetId = $sheet['properties']['sheetId'];
            $title = $sheet['properties']['title'];
            $sheetTitleToId[$title] = $sheetId;
            if ($idx === 0) {
                $firstSheetId = $sheetId;
            }
        }

        $batchRequests = [];

        foreach ($operations as $op) {
            $type = $op['type'];
            
            // Resolve Sheet ID
            $sheetId = $firstSheetId;
            $rangeSheet = null;
            $gridRange = null;

            if (isset($op['range'])) {
                $parsed = \App\Domain\AI\Utils\A1Parser::parse($op['range']);
                $rangeSheet = $parsed['sheetTitle'];
                $gridRange = $parsed['gridRange'];
            }
            
            $opSheet = $op['sheet'] ?? null;
            $targetSheetTitle = $rangeSheet ?? $opSheet;
            
            if ($targetSheetTitle) {
                if (!isset($sheetTitleToId[$targetSheetTitle])) {
                    throw new \Exception("Aba não encontrada: {$targetSheetTitle}", 422);
                }
                $sheetId = $sheetTitleToId[$targetSheetTitle];
            }

            if ($gridRange) {
                $gridRange['sheetId'] = $sheetId;
            }

            if ($type === 'format_range') {
                $format = $op['format'];
                $cellFormat = [];
                $fields = [];

                if (isset($format['background_color'])) {
                    $cellFormat['backgroundColorStyle'] = ['rgbColor' => $this->hexToRgb($format['background_color'])];
                    $fields[] = 'userEnteredFormat.backgroundColorStyle';
                }
                if (isset($format['horizontal_alignment'])) {
                    $cellFormat['horizontalAlignment'] = $format['horizontal_alignment'];
                    $fields[] = 'userEnteredFormat.horizontalAlignment';
                }
                if (isset($format['vertical_alignment'])) {
                    $cellFormat['verticalAlignment'] = $format['vertical_alignment'];
                    $fields[] = 'userEnteredFormat.verticalAlignment';
                }
                if (isset($format['wrap'])) {
                    $cellFormat['wrapStrategy'] = $format['wrap'] ? 'WRAP' : 'OVERFLOW_CELL';
                    $fields[] = 'userEnteredFormat.wrapStrategy';
                }

                $textFormat = [];
                if (isset($format['text_color'])) {
                    $textFormat['foregroundColorStyle'] = ['rgbColor' => $this->hexToRgb($format['text_color'])];
                    $fields[] = 'userEnteredFormat.textFormat.foregroundColorStyle';
                }
                if (isset($format['bold'])) {
                    $textFormat['bold'] = $format['bold'];
                    $fields[] = 'userEnteredFormat.textFormat.bold';
                }
                if (isset($format['italic'])) {
                    $textFormat['italic'] = $format['italic'];
                    $fields[] = 'userEnteredFormat.textFormat.italic';
                }
                if (isset($format['font_size'])) {
                    $textFormat['fontSize'] = $format['font_size'];
                    $fields[] = 'userEnteredFormat.textFormat.fontSize';
                }

                if (!empty($textFormat)) {
                    $cellFormat['textFormat'] = $textFormat;
                }

                if (!empty($cellFormat)) {
                    $batchRequests[] = [
                        'repeatCell' => [
                            'range' => $gridRange,
                            'cell' => ['userEnteredFormat' => $cellFormat],
                            'fields' => implode(',', $fields)
                        ]
                    ];
                }
            } elseif ($type === 'number_format') {
                $presetPatterns = [
                    'CURRENCY_BRL' => ['type' => 'CURRENCY', 'pattern' => '"R$"#,##0.00'],
                    'CURRENCY_USD' => ['type' => 'CURRENCY', 'pattern' => '"$"#,##0.00'],
                    'INTEGER' => ['type' => 'NUMBER', 'pattern' => '#,##0'],
                    'DECIMAL_2' => ['type' => 'NUMBER', 'pattern' => '#,##0.00'],
                    'PERCENT' => ['type' => 'PERCENT', 'pattern' => '0.00%'],
                    'DATE_DMY' => ['type' => 'DATE', 'pattern' => 'dd/mm/yyyy'],
                    'DATE_YMD' => ['type' => 'DATE', 'pattern' => 'yyyy-mm-dd'],
                    'DATETIME_DMY' => ['type' => 'DATE_TIME', 'pattern' => 'dd/mm/yyyy hh:mm:ss']
                ];
                
                $preset = $presetPatterns[$op['format']];
                $batchRequests[] = [
                    'repeatCell' => [
                        'range' => $gridRange,
                        'cell' => ['userEnteredFormat' => ['numberFormat' => $preset]],
                        'fields' => 'userEnteredFormat.numberFormat'
                    ]
                ];
            } elseif ($type === 'borders') {
                $style = $op['style'];
                if ($style === 'NONE') {
                    // Limpar bordas
                    $batchRequests[] = [
                        'updateBorders' => [
                            'range' => $gridRange,
                            'top' => ['style' => 'NONE'],
                            'bottom' => ['style' => 'NONE'],
                            'left' => ['style' => 'NONE'],
                            'right' => ['style' => 'NONE'],
                            'innerHorizontal' => ['style' => 'NONE'],
                            'innerVertical' => ['style' => 'NONE'],
                        ]
                    ];
                } else {
                    $googleStyle = 'SOLID';
                    $color = ['red' => 0, 'green' => 0, 'blue' => 0]; // default black
                    
                    if ($style === 'SUBTLE') {
                        $googleStyle = 'SOLID';
                        $color = ['red' => 0.8, 'green' => 0.8, 'blue' => 0.8]; // light gray
                    } elseif ($style === 'THICK') {
                        $googleStyle = 'SOLID_THICK';
                    }

                    $borderDef = ['style' => $googleStyle, 'colorStyle' => ['rgbColor' => $color]];
                    
                    $batchRequests[] = [
                        'updateBorders' => [
                            'range' => $gridRange,
                            'top' => $borderDef,
                            'bottom' => $borderDef,
                            'left' => $borderDef,
                            'right' => $borderDef,
                            'innerHorizontal' => $borderDef,
                            'innerVertical' => $borderDef,
                        ]
                    ];
                }
            } elseif ($type === 'freeze') {
                $gridProps = [];
                $fields = [];
                if (isset($op['rows'])) {
                    $gridProps['frozenRowCount'] = $op['rows'];
                    $fields[] = 'gridProperties.frozenRowCount';
                }
                if (isset($op['columns'])) {
                    $gridProps['frozenColumnCount'] = $op['columns'];
                    $fields[] = 'gridProperties.frozenColumnCount';
                }
                
                if (!empty($gridProps)) {
                    $batchRequests[] = [
                        'updateSheetProperties' => [
                            'properties' => [
                                'sheetId' => $sheetId,
                                'gridProperties' => $gridProps
                            ],
                            'fields' => implode(',', $fields)
                        ]
                    ];
                }
            } elseif ($type === 'auto_resize_columns') {
                $batchRequests[] = [
                    'autoResizeDimensions' => [
                        'dimensions' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'COLUMNS',
                            'startIndex' => $gridRange['startColumnIndex'],
                            'endIndex' => $gridRange['endColumnIndex']
                        ]
                    ]
                ];
            } elseif ($type === 'set_column_width') {
                $batchRequests[] = [
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'COLUMNS',
                            'startIndex' => $gridRange['startColumnIndex'],
                            'endIndex' => $gridRange['endColumnIndex']
                        ],
                        'properties' => ['pixelSize' => $op['width_px']],
                        'fields' => 'pixelSize'
                    ]
                ];
            } elseif ($type === 'set_row_height') {
                $batchRequests[] = [
                    'updateDimensionProperties' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $gridRange['startRowIndex'],
                            'endIndex' => $gridRange['endRowIndex']
                        ],
                        'properties' => ['pixelSize' => $op['height_px']],
                        'fields' => 'pixelSize'
                    ]
                ];
            } elseif ($type === 'merge_cells') {
                $batchRequests[] = [
                    'mergeCells' => [
                        'range' => $gridRange,
                        'mergeType' => 'MERGE_ALL'
                    ]
                ];
            }
        }

        if (empty($batchRequests)) {
            throw new \Exception("Nenhuma formatação válida encontrada para aplicar.", 400);
        }

        $payload = ['requests' => $batchRequests];
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$fileId}:batchUpdate";

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url, $payload) {
            return \Illuminate\Support\Facades\Http::withToken($token)->post($url, $payload);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new \Exception("Erro na API do Google Sheets: " . $response->body(), $response->status());
        }

        // Registrar auditoria
        $this->logAuditAction->execute(
            'ai_format_resource_spreadsheet',
            get_class($resource),
            $resource->id,
            [
                'resource_uuid' => $resource->uuid,
                'provider' => $integration->provider,
                'applied_operations' => count($batchRequests),
                'operation_types' => array_column($operations, 'type')
            ]
        );

        return [
            'resource_uuid' => $resource->uuid,
            'applied_operations' => count($batchRequests),
            'operation_types' => array_column($operations, 'type'),
            'refresh_preview' => true
        ];
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            'red' => hexdec(substr($hex, 0, 2)) / 255.0,
            'green' => hexdec(substr($hex, 2, 2)) / 255.0,
            'blue' => hexdec(substr($hex, 4, 2)) / 255.0
        ];
    }

    public function updateSpreadsheetValues(Organization $organization, \App\Domain\Identity\Models\User $user, \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext, string $uuid, array $updates): array

    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->where('is_enabled', true)
            ->first();

        if (!$integration) {
            throw new Exception("Integração do Google Workspace não está ativa ou configurada.", 403);
        }

        $resource = $this->findByUuid($organization, $uuid);

        if (!$resource) {
            throw new Exception("Resource not found.", 404);
        }

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar este recurso.");
        }

        if ($resource->resource_type !== \App\Domain\Resources\Enums\ResourceType::SPREADSHEET) {
            throw new Exception("Este recurso não é uma planilha.", 422);
        }

        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $identity = $accessContext->getResolvedIdentity();

        $dataPayload = [];
        foreach ($updates as $update) {
            $dataPayload[] = [
                'range' => $update['range'],
                'majorDimension' => 'ROWS',
                'values' => $update['values']
            ];
        }

        $payload = [
            'valueInputOption' => 'USER_ENTERED',
            'includeValuesInResponse' => false,
            'data' => $dataPayload
        ];

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$fileId}/values:batchUpdate";

        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url, $payload) {
            return Http::withToken($token)->post($url, $payload);
        }, $identity, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        $googleData = $response->json();
        $responses = $googleData['responses'] ?? [];

        $updatedRanges = [];
        $totalUpdatedCells = $googleData['totalUpdatedCells'] ?? 0;

        foreach ($responses as $idx => $res) {
            $rangeString = $res['updatedRange'] ?? ($updates[$idx]['range'] ?? null);
            $updatedRanges[] = [
                'range' => $rangeString,
                'updated_rows' => $res['updatedRows'] ?? 0,
                'updated_columns' => $res['updatedColumns'] ?? 0,
                'updated_cells' => $res['updatedCells'] ?? 0,
            ];
            
            // If the root didn't have totalUpdatedCells, accumulate it just in case
            if (!isset($googleData['totalUpdatedCells'])) {
                $totalUpdatedCells += ($res['updatedCells'] ?? 0);
            }
        }

        $resource->update([
            'updated_by_provider_at' => now(),
            'last_synced_at' => now(),
        ]);

        $this->logAuditAction->execute('ai_update_resource_spreadsheet_values', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'total_updated_cells' => $totalUpdatedCells,
            'ranges_count' => count($updatedRanges)
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'updated_ranges' => $updatedRanges,
            'total_updated_cells' => $totalUpdatedCells,
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
    public function uploadAttachmentToDrive(
        Organization $organization,
        \App\Domain\Identity\Models\User $user,
        \App\Domain\Permissions\Contexts\AuthorizedAccessContext $accessContext,
        \App\Domain\AI\Models\MessageAttachment $attachment,
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

            if (!$parentResource->is_folder && $parentResource->mime_type !== 'application/vnd.google-apps.folder') {
                throw new Exception("O recurso de destino especificado não é uma pasta.", 422);
            }
        }

        $safeName = $attachment->original_name;
        $detectedMime = $attachment->mime_type ?? 'application/octet-stream';
        $sizeBytes = $attachment->size;

        // ── 3. Criar Sessão Resumable de Upload ──────────────────────────────
        $metadata = ['name' => $safeName];
        if ($parentResource && $parentResource->external_id) {
            $metadata['parents'] = [$parentResource->external_id];
        }

        $fields = 'id,name,mimeType,parents,size,modifiedTime,createdTime,webViewLink,iconLink,owners,shared';
        $identity = $accessContext->getResolvedIdentity();

        $sessionResponse = $this->googleTokenService->executeWithRetry(
            $integration,
            function ($token) use ($metadata, $fields, $detectedMime, $sizeBytes) {
                return Http::withToken($token)
                    ->withHeaders([
                        'X-Upload-Content-Type' => $detectedMime,
                        'X-Upload-Content-Length' => $sizeBytes,
                        'Content-Type' => 'application/json; charset=UTF-8'
                    ])
                    ->post("https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields={$fields}", $metadata);
            },
            $identity,
            ['https://www.googleapis.com/auth/drive']
        );

        $uploadUrl = $sessionResponse->header('Location');

        if (!$sessionResponse->successful() || !$uploadUrl) {
            throw new Exception("Falha ao iniciar sessão de upload no Google Drive: " . $sessionResponse->body(), $sessionResponse->status() ?: 500);
        }

        // ── 4. Enviar Arquivo em Chunks de 8MB ────────────────────────────────
        $disk = \Illuminate\Support\Facades\Storage::disk('chat-attachments');
        $stream = $disk->readStream($attachment->storage_path);
        if (!$stream) {
            throw new Exception("ATTACHMENT_FILE_MISSING", 404);
        }

        $chunkSize = 8 * 1024 * 1024; // 8MB
        $bytesSent = 0;
        $googleData = null;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, $chunkSize);
                $currentChunkSize = strlen($chunk);
                
                if ($currentChunkSize === 0) {
                    break; // Fim do arquivo inesperado ou exato
                }

                $start = $bytesSent;
                $end = $bytesSent + $currentChunkSize - 1;

                // Não usamos executeWithRetry para os chunks porque o uploadUrl já carrega o ID da sessão
                // E um erro 401 aqui significa que o upload falhou fatalmente.
                $chunkResponse = Http::withHeaders([
                    'Content-Length' => (string)$currentChunkSize,
                    'Content-Range' => "bytes {$start}-{$end}/{$sizeBytes}",
                ])->withBody($chunk, $detectedMime)->put($uploadUrl);

                if ($chunkResponse->status() === 308) {
                    // 308 Resume Incomplete = Sucesso parcial
                    $bytesSent += $currentChunkSize;
                    continue;
                }

                if ($chunkResponse->status() === 200 || $chunkResponse->status() === 201) {
                    // Upload completo
                    $googleData = $chunkResponse->json();
                    break;
                }

                throw new Exception("Falha no upload do chunk para o Google Drive: " . $chunkResponse->body(), $chunkResponse->status());
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (!$googleData || empty($googleData['id'])) {
            throw new Exception("A API do Google não retornou o ID do arquivo após o upload resumable.", 500);
        }

        // ── 5. Mapear e Criar IntegrationResource local ───────────────────────
        $resourceType = $this->mapMimeToResourceType($detectedMime);
        $googleFileId = $googleData['id'];

        $googleOwners = $googleData['owners'] ?? [];
        $googleModifiedTime = $googleData['modifiedTime'] ?? null;
        $googleCreatedTime = $googleData['createdTime'] ?? null;
        $googleParents = $googleData['parents'] ?? [];

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
            $this->logAuditAction->execute('ai_upload_sync_failed', 'IntegrationResource', $googleFileId, [
                'provider' => 'google_workspace',
                'user_id' => $user->id,
                'name' => $safeName,
                'error' => $dbException->getMessage(),
            ]);
            throw new Exception("O arquivo foi enviado para o Google, mas ocorreu um erro interno ao salvá-lo no sistema.", 500);
        }

        $this->logAuditAction->execute('ai_upload_resource', 'IntegrationResource', (string) $resource->id, [
            'provider' => 'google_workspace',
            'uuid' => $resource->uuid,
            'user_id' => $user->id,
            'name' => $safeName
        ]);

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => $resource->resource_type ?? 'document',
            'provider' => 'google_workspace',
            'url' => $resource->url,
        ];
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


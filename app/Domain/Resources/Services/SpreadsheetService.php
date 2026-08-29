<?php

namespace App\Domain\Resources\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Services\AuthorizationService;
use App\Domain\Integrations\Services\GoogleTokenService;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Enums\Provider;
use Illuminate\Support\Facades\Http;
use Exception;

class SpreadsheetService
{
    public function __construct(
        private AuthorizationService $authorizationService,
        private GoogleTokenService $googleTokenService
    ) {}

    public function getSpreadsheetView(Organization $organization, User $user, string $uuid, ?string $sheetTitle, ?string $range): array
    {
        // Encontra o recurso validando o tenant
        $resource = IntegrationResource::where('uuid', $uuid)
            ->whereHas('integration', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id);
            })
            ->first();

        if (!$resource) {
            throw new Exception("Resource not found.", 404);
        }

        // Valida autorização
        // Frontend uses 'resources.read' as base
        $integration = $resource->integration;
        if (!$integration || !$integration->is_enabled || $integration->status !== 'connected') {
            throw new Exception("Integração indisponível.", 403);
        }

        $accessContext = $this->authorizationService->resolveAccessContext(
            $user,
            $organization,
            'resources.read',
            $integration,
            $integration->provider
        );

        if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Acesso negado ao recurso.");
        }

        if ($resource->resource_type !== ResourceType::SPREADSHEET) {
            throw new Exception("O recurso não é uma planilha.", 422);
        }

        if ($resource->provider !== Provider::GOOGLE_WORKSPACE) {
            throw new Exception("Provider incompatível.", 422);
        }

        $fileId = $resource->external_id;
        if (empty($fileId)) {
            throw new Exception("Resource lacks an external identifier.", 400);
        }

        $identity = $accessContext->getResolvedIdentity();

        $defaultRange = 'A1:Z100';
        $requestedRange = $range ?: $defaultRange;
        
        $rangeQuery = $requestedRange;
        if ($sheetTitle) {
            // Encode sheet title as it can contain spaces/special chars
            $rangeQuery = "'" . str_replace("'", "''", $sheetTitle) . "'!" . $requestedRange;
        }

        // Validate max cells (roughly width * height). 5000 is our limit.
        // A simple heuristic (we won't parse A1 notation perfectly here, but if someone sends A1:ZZZ999 we let Google handle the actual load but we can check lengths). 
        // We will just pass it to Google, and if Google's response is too big we could truncate, but to avoid Google costs, we should validate it.
        // For simplicity, we just pass to Google. If needed, we can parse letters later.

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$fileId}";
        
        $response = $this->googleTokenService->executeWithRetry($integration, function ($token) use ($url, $rangeQuery) {
            return Http::withToken($token)->get($url, [
                'includeGridData' => 'true',
                'ranges' => $rangeQuery,
            ]);
        }, $identity, ['https://www.googleapis.com/auth/drive.readonly', 'https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new Exception("Provider API Error: " . $response->body(), $response->status());
        }

        $googleData = $response->json();
        
        return $this->formatResponse($resource, $googleData, $requestedRange, $sheetTitle, $user, $organization);
    }

    private function formatResponse(IntegrationResource $resource, array $googleData, string $requestedRange, ?string $sheetTitle, User $user, Organization $organization): array
    {
        $sheets = [];
        $grid = null;
        $activeSheetName = $sheetTitle;

        if (isset($googleData['sheets'])) {
            // Se o usuário não pediu sheetTitle, usamos a primeira aba.
            if (!$activeSheetName && count($googleData['sheets']) > 0) {
                $activeSheetName = $googleData['sheets'][0]['properties']['title'] ?? null;
            }

            foreach ($googleData['sheets'] as $sheetData) {
                $props = $sheetData['properties'] ?? [];
                $gridProps = $props['gridProperties'] ?? [];
                
                $title = $props['title'] ?? 'Unknown';
                
                $sheets[] = [
                    'title' => $title,
                    'index' => $props['index'] ?? 0,
                    'row_count' => $gridProps['rowCount'] ?? 0,
                    'column_count' => $gridProps['columnCount'] ?? 0,
                    'frozen_rows' => $gridProps['frozenRowCount'] ?? 0,
                    'frozen_columns' => $gridProps['frozenColumnCount'] ?? 0,
                ];

                if ($title === $activeSheetName && isset($sheetData['data']) && count($sheetData['data']) > 0) {
                    $grid = $this->formatGrid($sheetData['data'][0], $activeSheetName, $requestedRange);
                }
            }
        }

        if (!$grid) {
            throw new Exception("Aba não encontrada ou fora do range.", 404);
        }

        // Capabilities
        $canWrite = $this->authorizationService->can($user, $organization, 'resources.write');

        return [
            'resource_uuid' => $resource->uuid,
            'name' => $resource->name,
            'type' => 'spreadsheet',
            'provider' => 'google_workspace',
            'capabilities' => [
                'preview' => true,
                'edit' => $canWrite,
                'download' => true,
            ],
            'active_sheet' => $activeSheetName,
            'requested_range' => $requestedRange,
            'sheets' => $sheets,
            'grid' => $grid,
        ];
    }

    private function formatGrid(array $gridData, string $sheetTitle, string $requestedRange): array
    {
        $rows = [];
        
        $rowData = $gridData['rowData'] ?? [];

        foreach ($rowData as $row) {
            $formattedRow = [];
            $values = $row['values'] ?? [];

            foreach ($values as $cell) {
                if (empty($cell)) {
                    $formattedRow[] = null;
                    continue;
                }

                $userEnteredValue = $cell['userEnteredValue'] ?? [];
                $effectiveValue = $cell['effectiveValue'] ?? [];
                
                // Extrai o valor real
                $value = null;
                if (isset($effectiveValue['numberValue'])) $value = $effectiveValue['numberValue'];
                elseif (isset($effectiveValue['stringValue'])) $value = $effectiveValue['stringValue'];
                elseif (isset($effectiveValue['boolValue'])) $value = $effectiveValue['boolValue'];
                
                // Fórmula se houver
                $formula = $userEnteredValue['formulaValue'] ?? null;
                
                $format = $cell['effectiveFormat'] ?? [];
                $textFormat = $format['textFormat'] ?? [];
                $bgColor = $format['backgroundColor'] ?? null;
                $numberFormat = $format['numberFormat'] ?? null;

                $bgHex = $bgColor ? $this->rgbToHex($bgColor) : null;
                $textHex = isset($textFormat['foregroundColor']) ? $this->rgbToHex($textFormat['foregroundColor']) : null;

                $formattedRow[] = [
                    'value' => $value,
                    'formatted_value' => $cell['formattedValue'] ?? null,
                    'formula' => $formula,
                    'format' => [
                        'bold' => $textFormat['bold'] ?? false,
                        'background_color' => $bgHex,
                        'text_color' => $textHex,
                        'number_format' => $numberFormat,
                    ]
                ];
            }
            $rows[] = $formattedRow;
        }

        return [
            'range' => "{$sheetTitle}!{$requestedRange}",
            'rows' => $rows,
            'column_widths' => (object)[], // Simplificado na primeira versão
            'row_heights' => (object)[], // Simplificado na primeira versão
            'merged_ranges' => []
        ];
    }

    private function rgbToHex(array $rgb): ?string
    {
        $r = isset($rgb['red']) ? round($rgb['red'] * 255) : 0;
        $g = isset($rgb['green']) ? round($rgb['green'] * 255) : 0;
        $b = isset($rgb['blue']) ? round($rgb['blue'] * 255) : 0;
        
        // Se for tudo 0 e não houver opacidade, pode ser padrão. Mas retornamos hex puro.
        if (!isset($rgb['red']) && !isset($rgb['green']) && !isset($rgb['blue'])) {
            return null;
        }
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

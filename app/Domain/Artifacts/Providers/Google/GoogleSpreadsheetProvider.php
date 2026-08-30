<?php

namespace App\Domain\Artifacts\Providers\Google;

use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCommitIdentity;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCreateCommand;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureResult;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValuesBatch;
use App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnavailableException;
use App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderWriteException;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleTokenService;
use Illuminate\Support\Facades\Http;

class GoogleSpreadsheetProvider implements SpreadsheetProviderInterface
{
    private Integration $integration;
    private $identityContext;

    public function __construct(
        private GoogleTokenService $tokenService
    ) {}

    public function setContext(Integration $integration, $identityContext): void
    {
        $this->integration = $integration;
        $this->identityContext = $identityContext;
    }

    public function capabilities(): SpreadsheetProviderCapabilities
    {
        return new SpreadsheetProviderCapabilities(
            supportsMultipleSheets: true,
            supportsMerge: true,
            supportsFreeze: true,
            supportsAutoResize: true,
            supportsNumberFormat: true,
            supportsRowHeight: true,
            supportsColumnWidth: true,
            maxCellsPerValuesRequest: 50000,
            maxRangesPerValuesRequest: 100,
            maxRequestsPerBatch: 100,
            maxFormatOperationsPerBatch: 50,
            maxPayloadBytes: 2 * 1024 * 1024 // 2MB
        );
    }

    public function createSpreadsheet(SpreadsheetCreateCommand $command): SpreadsheetProviderResource
    {
        $url = "https://www.googleapis.com/drive/v3/files";
        $payload = [
            'name' => $command->title,
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
            'appProperties' => [
                'nodal_commit_uuid' => $command->identity->commitUuid
            ]
        ];

        $response = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($url, $payload) {
            return Http::withToken($token)->post($url, $payload);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new SpreadsheetProviderWriteException("Failed to create spreadsheet: " . $response->body());
        }

        $googleData = $response->json();
        $googleFileId = $googleData['id'] ?? null;

        if (!$googleFileId) {
            throw new SpreadsheetProviderUnavailableException("Google did not return an ID.");
        }

        return new SpreadsheetProviderResource(
            externalId: $googleFileId,
            externalUrl: "https://docs.google.com/spreadsheets/d/{$googleFileId}/edit"
        );
    }

    public function findByCommitKey(SpreadsheetCommitIdentity $identity): ?SpreadsheetProviderResource
    {
        $url = "https://www.googleapis.com/drive/v3/files";
        $q = "appProperties has { key='nodal_commit_uuid' and value='{$identity->commitUuid}' } and trashed=false";
        
        $response = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($url, $q) {
            return Http::withToken($token)->get($url, ['q' => $q]);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive.readonly']);

        if (!$response->successful()) {
            return null;
        }

        $files = $response->json('files');
        if (empty($files)) {
            return null;
        }

        $googleFileId = $files[0]['id'];
        return new SpreadsheetProviderResource(
            externalId: $googleFileId,
            externalUrl: "https://docs.google.com/spreadsheets/d/{$googleFileId}/edit"
        );
    }

    public function prepareStructure(SpreadsheetProviderResource $resource, SpreadsheetStructureBatch $batch): SpreadsheetStructureResult
    {
        // 1. Get current sheets to find the default first sheet (usually ID 0)
        $metadataUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$resource->externalId}?fields=sheets(properties(sheetId,title,index))";
        
        $metadataResponse = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($metadataUrl) {
            return Http::withToken($token)->get($metadataUrl);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);
        
        if (!$metadataResponse->successful()) {
            throw new SpreadsheetProviderWriteException("Error fetching metadata: " . $metadataResponse->body());
        }

        $sheets = $metadataResponse->json('sheets') ?? [];
        usort($sheets, fn($a, $b) => ($a['properties']['index'] ?? 0) <=> ($b['properties']['index'] ?? 0));
        
        $existingByTitle = [];
        foreach ($sheets as $remoteSheet) {
            $existingByTitle[$remoteSheet['properties']['title']] = $remoteSheet['properties']['sheetId'];
        }

        // Validate draft titles for uniqueness
        $draftTitles = array_column($batch->sheetsToCreate, 'title');
        if (count(array_unique($draftTitles)) !== count($draftTitles)) {
            throw new SpreadsheetProviderWriteException("Ambiguous sheet titles in draft. Cannot safely reconcile.");
        }
        
        $batchRequests = [];
        $handles = [];

        foreach ($batch->sheetsToCreate as $idx => $sheetDef) {
            $draftTitle = $sheetDef['title'];

            if (isset($existingByTitle[$draftTitle])) {
                // Sheet already exists remotely (from a previous crashed run)
                $handles[] = new SpreadsheetProviderSheetHandle($sheetDef['uuid'], $existingByTitle[$draftTitle], $draftTitle);
                continue;
            }

            if ($idx === 0 && count($sheets) === 1) {
                // Rename first sheet
                $defaultSheetId = $sheets[0]['properties']['sheetId'];
                $batchRequests[] = [
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $defaultSheetId,
                            'title' => $draftTitle
                        ],
                        'fields' => 'title'
                    ]
                ];
                $handles[] = new SpreadsheetProviderSheetHandle($sheetDef['uuid'], $defaultSheetId, $draftTitle);
            } else {
                // Create new sheet
                $newSheetId = mt_rand(100000, 99999999);
                $batchRequests[] = [
                    'addSheet' => [
                        'properties' => [
                            'sheetId' => $newSheetId,
                            'title' => $draftTitle
                        ]
                    ]
                ];
                $handles[] = new SpreadsheetProviderSheetHandle($sheetDef['uuid'], $newSheetId, $draftTitle);
            }
        }

        if (!empty($batchRequests)) {
            $updateUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$resource->externalId}:batchUpdate";
            $response = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($updateUrl, $batchRequests) {
                return Http::withToken($token)->post($updateUrl, ['requests' => $batchRequests]);
            }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);
            
            if (!$response->successful()) {
                throw new SpreadsheetProviderWriteException("Failed to prepare structure: " . $response->body());
            }
        }

        return new SpreadsheetStructureResult($handles);
    }

    public function writeValues(SpreadsheetProviderResource $resource, SpreadsheetValuesBatch $batch): void
    {
        $sheetId = $batch->sheetHandle->externalSheetId;
        $sheetTitle = current(array_filter((array) $batch->sheetHandle, fn() => true)); // Simplification, need actual title if A1 is used
        
        $data = [];
        foreach ($batch->ranges as $range) {
            // Convert indices to A1
            $startA1 = $this->colIndexToLetter($range->startCol) . ($range->startRow + 1);
            $endA1 = $this->colIndexToLetter($range->endCol) . ($range->endRow + 1);
            $a1Range = "{$batch->sheetHandle->title}!{$startA1}:{$endA1}";
            
            $data[] = [
                'range' => $a1Range,
                'majorDimension' => 'ROWS',
                'values' => $range->values
            ];
        }

        if (empty($data)) return;

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$resource->externalId}/values:batchUpdate";
        
        $response = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($url, $data) {
            return Http::withToken($token)->post($url, [
                'valueInputOption' => 'USER_ENTERED',
                'data' => $data
            ]);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new SpreadsheetProviderWriteException("Failed to write values: " . $response->body());
        }
    }

    public function applyFormatting(SpreadsheetProviderResource $resource, SpreadsheetFormatBatch $batch): void
    {
        $sheetId = $batch->sheetHandle->externalSheetId;
        $batchRequests = [];

        foreach ($batch->operations as $op) {
            $gridRange = [
                'sheetId' => $sheetId,
                'startRowIndex' => $op->startRow,
                'endRowIndex' => $op->endRow + 1,
                'startColumnIndex' => $op->startCol,
                'endColumnIndex' => $op->endCol + 1
            ];

            if ($op->type === 'background_color') {
                $batchRequests[] = [
                    'repeatCell' => [
                        'range' => $gridRange,
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColorStyle' => ['rgbColor' => \App\Domain\Artifacts\Providers\Google\Mappers\GoogleColorMapper::hexToRgb($op->value['val'])]
                            ]
                        ],
                        'fields' => 'userEnteredFormat.backgroundColorStyle'
                    ]
                ];
            } elseif ($op->type === 'text_style') {
                $styleKey = $op->value['key'];
                $boolVal = (bool) $op->value['val'];
                $batchRequests[] = [
                    'repeatCell' => [
                        'range' => $gridRange,
                        'cell' => [
                            'userEnteredFormat' => [
                                'textFormat' => [
                                    $styleKey => $boolVal
                                ]
                            ]
                        ],
                        'fields' => "userEnteredFormat.textFormat.{$styleKey}"
                    ]
                ];
            } elseif ($op->type === 'number_format') {
                $preset = \App\Domain\Artifacts\Providers\Google\Mappers\GoogleNumberFormatMapper::getPreset($op->value['val']);
                if ($preset) {
                    $batchRequests[] = [
                        'repeatCell' => [
                            'range' => $gridRange,
                            'cell' => ['userEnteredFormat' => ['numberFormat' => $preset]],
                            'fields' => 'userEnteredFormat.numberFormat'
                        ]
                    ];
                }
            } elseif ($op->type === 'freeze') {
                $gridProps = [];
                $fields = [];
                if (isset($op->value['rows'])) {
                    $gridProps['frozenRowCount'] = (int) $op->value['rows'];
                    $fields[] = 'gridProperties.frozenRowCount';
                }
                if (isset($op->value['columns'])) {
                    $gridProps['frozenColumnCount'] = (int) $op->value['columns'];
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
            } elseif ($op->type === 'merge') {
                $batchRequests[] = [
                    'mergeCells' => [
                        'range' => $gridRange,
                        'mergeType' => 'MERGE_ALL'
                    ]
                ];
            }
        }

        if (empty($batchRequests)) return;

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$resource->externalId}:batchUpdate";
        
        $response = $this->tokenService->executeWithRetry($this->integration, function ($token) use ($url, $batchRequests) {
            return Http::withToken($token)->post($url, ['requests' => $batchRequests]);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);

        if (!$response->successful()) {
            throw new SpreadsheetProviderWriteException("Failed to format: " . $response->body());
        }
    }

    public function cleanup(SpreadsheetProviderResource $resource): void
    {
        $url = "https://www.googleapis.com/drive/v3/files/{$resource->externalId}";
        $this->tokenService->executeWithRetry($this->integration, function ($token) use ($url) {
            return Http::withToken($token)->delete($url);
        }, $this->identityContext, ['https://www.googleapis.com/auth/drive']);
    }

    private function colIndexToLetter(int $col): string
    {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26) - 1;
        }
        return $letter;
    }
}

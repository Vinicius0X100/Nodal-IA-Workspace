<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Exceptions\ArtifactDraftNotEditableException;
use App\Domain\Artifacts\Exceptions\ArtifactDraftNotFoundException;
use App\Domain\Artifacts\Exceptions\SpreadsheetDraftInvalidRangeException;
use App\Domain\Artifacts\Exceptions\SpreadsheetSheetNotFoundException;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Repositories\SpreadsheetDraftRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UpdateSpreadsheetValuesService
{
    public function __construct(
        private SpreadsheetDraftRepositoryInterface $repository
    ) {}

    public function execute(string $artifactUuid, int $organizationId, string $sheetUuid, int $expectedRevision, array $updates): array
    {
        return DB::transaction(function () use ($artifactUuid, $organizationId, $sheetUuid, $expectedRevision, $updates) {
            $draft = ArtifactDraft::where('uuid', $artifactUuid)
                ->where('organization_id', $organizationId)
                ->where('type', 'spreadsheet')
                ->lockForUpdate()
                ->first();
                
            if (!$draft) {
                throw new ArtifactDraftNotFoundException();
            }
            
            if ($draft->status->value !== 'draft') {
                throw new ArtifactDraftNotEditableException();
            }

            $sheet = $draft->sheets()->where('uuid', $sheetUuid)->first();
            if (!$sheet) {
                throw new SpreadsheetSheetNotFoundException();
            }

            $chunkRows = config('artifacts.spreadsheet.chunk_rows', 50);
            $chunkCols = config('artifacts.spreadsheet.chunk_columns', 50);

            $chunksToUpdate = [];
            
            foreach ($updates as $update) {
                if (!isset($update['range']) || !isset($update['values'])) continue;
                
                $coords = $this->parseRange($update['range']);
                
                foreach ($update['values'] as $rOffset => $rowValues) {
                    $actualRow = $coords['start_row'] + $rOffset;
                    $cChunkRow = floor($actualRow / $chunkRows);
                    
                    foreach ($rowValues as $cOffset => $val) {
                        $actualCol = $coords['start_col'] + $cOffset;
                        $cChunkCol = floor($actualCol / $chunkCols);
                        
                        $cellData = [];
                        
                        // Clear semantics
                        if (is_array($val) && isset($val['clear']) && $val['clear']) {
                            $cellData['clear'] = true;
                        } 
                        // String empty might be value empty if explicitly configured, but let's assume it's just value = ""
                        elseif (is_string($val) && str_starts_with($val, '=')) {
                            $cellData['formula'] = $val;
                        } else {
                            $cellData['value'] = is_array($val) && isset($val['value']) ? $val['value'] : $val;
                        }
                        
                        $chunksToUpdate["{$cChunkRow}_{$cChunkCol}"][$actualRow][$actualCol] = $cellData;
                    }
                }
                
                $this->repository->logChange($draft, $expectedRevision + 1, 'values_updated', $update['range'], null, $sheet);
            }
            
            foreach ($chunksToUpdate as $chunkKey => $payloadUpdates) {
                [$cR, $cC] = explode('_', $chunkKey);
                $this->repository->upsertChunkPayload($sheet, (int)$cR, (int)$cC, $payloadUpdates);
            }

            $this->repository->incrementRevision($draft, $expectedRevision);
            
            return [
                'artifact_uuid' => $draft->uuid,
                'revision' => $draft->revision,
                'refresh_preview' => true,
            ];
        });
    }
    
    private function parseRange(string $range): array
    {
        if (preg_match('/^([A-Z]+)(\d+)(?::([A-Z]+)(\d+))?$/', $range, $matches)) {
            $startCol = $this->colToInt($matches[1]);
            $startRow = ((int)$matches[2]) - 1;
            
            $endCol = isset($matches[3]) && $matches[3] ? $this->colToInt($matches[3]) : $startCol;
            $endRow = isset($matches[4]) && $matches[4] ? ((int)$matches[4]) - 1 : $startRow;
            
            return [
                'start_col' => $startCol,
                'start_row' => $startRow,
                'end_col' => $endCol,
                'end_row' => $endRow,
            ];
        }
        
        throw new SpreadsheetDraftInvalidRangeException();
    }

    private function colToInt(string $col): int
    {
        $col = strtoupper($col);
        $len = strlen($col);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($col[$i]) - 64);
        }
        return $num - 1;
    }
}

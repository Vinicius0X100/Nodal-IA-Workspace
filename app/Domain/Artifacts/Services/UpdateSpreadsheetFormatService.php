<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Exceptions\ArtifactDraftNotEditableException;
use App\Domain\Artifacts\Exceptions\ArtifactDraftNotFoundException;
use App\Domain\Artifacts\Exceptions\SpreadsheetDraftInvalidRangeException;
use App\Domain\Artifacts\Exceptions\SpreadsheetSheetNotFoundException;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Repositories\SpreadsheetDraftRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UpdateSpreadsheetFormatService
{
    public function __construct(
        private SpreadsheetDraftRepositoryInterface $repository
    ) {}

    public function execute(string $artifactUuid, int $organizationId, string $sheetUuid, int $expectedRevision, array $operations): array
    {
        return DB::transaction(function () use ($artifactUuid, $organizationId, $sheetUuid, $expectedRevision, $operations) {
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

            $newRevision = $expectedRevision + 1;

            foreach ($operations as $operation) {
                if (!isset($operation['type'])) continue;
                
                $range = $operation['range'] ?? null;
                $coords = $range ? $this->parseRange($range) : ['start_row' => 0, 'end_row' => null, 'start_col' => 0, 'end_col' => null];
                
                if ($operation['type'] === 'format_range') {
                    $this->repository->addFormatRule(
                        $sheet, 
                        $newRevision, 
                        $coords['start_row'], 
                        $coords['end_row'], 
                        $coords['start_col'], 
                        $coords['end_col'], 
                        $operation['format'] ?? []
                    );
                    
                    $this->repository->logChange($draft, $newRevision, 'format_applied', $range, $operation['format'] ?? [], $sheet);
                }
                elseif ($operation['type'] === 'number_format') {
                    $this->repository->addFormatRule(
                        $sheet, 
                        $newRevision, 
                        $coords['start_row'], 
                        $coords['end_row'], 
                        $coords['start_col'], 
                        $coords['end_col'], 
                        ['number_format' => $operation['format']]
                    );
                    
                    $this->repository->logChange($draft, $newRevision, 'format_applied', $range, ['number_format' => $operation['format']], $sheet);
                }
                // Handle properties (freeze, dimension, merge)
                elseif ($operation['type'] === 'freeze') {
                    $props = $sheet->properties_json ?? [];
                    if (isset($operation['rows'])) $props['frozen_rows'] = $operation['rows'];
                    if (isset($operation['columns'])) $props['frozen_columns'] = $operation['columns'];
                    $sheet->properties_json = $props;
                    $sheet->save();
                    
                    $this->repository->logChange($draft, $newRevision, 'sheet_properties_updated', null, $props, $sheet);
                }
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

<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Exceptions\ArtifactDraftNotFoundException;
use App\Domain\Artifacts\Exceptions\SpreadsheetDraftInvalidRangeException;
use App\Domain\Artifacts\Exceptions\SpreadsheetSheetNotFoundException;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Repositories\SpreadsheetDraftRepositoryInterface;

class SpreadsheetViewportService
{
    public function __construct(
        private SpreadsheetDraftRepositoryInterface $repository
    ) {}

    public function getViewport(string $artifactUuid, int $organizationId, ?string $sheetIdentifier = null, ?string $range = null): array
    {
        $draft = ArtifactDraft::where('uuid', $artifactUuid)
            ->where('organization_id', $organizationId)
            ->where('type', 'spreadsheet')
            ->first();
            
        if (!$draft) {
            throw new ArtifactDraftNotFoundException();
        }
        
        $sheetQuery = $draft->sheets();
        if ($sheetIdentifier) {
            $sheetQuery->where(function ($q) use ($sheetIdentifier) {
                $q->where('uuid', $sheetIdentifier)
                  ->orWhere('title', $sheetIdentifier)
                  ->orWhere('index', (int)$sheetIdentifier);
            });
        } else {
            $sheetQuery->orderBy('index');
        }
        
        $sheet = $sheetQuery->first();
        
        if (!$sheet) {
            throw new SpreadsheetSheetNotFoundException();
        }

        $range = $range ?? 'A1:Z100';
        $coords = $this->parseRange($range);
        
        $chunks = $this->repository->getChunksInViewport($sheet, $coords['start_row'], $coords['end_row'], $coords['start_col'], $coords['end_col']);
        $formats = $this->repository->getFormats($sheet); // formats are retrieved fully or intersecting. For now, all of them.
        
        $cells = [];
        
        // 1. Resolve values
        $chunkRows = config('artifacts.spreadsheet.chunk_rows', 50);
        $chunkCols = config('artifacts.spreadsheet.chunk_columns', 50);
        
        foreach ($chunks as $chunk) {
            if (!$chunk->payload_json) continue;
            
            foreach ($chunk->payload_json as $r => $cols) {
                if ($r < $coords['start_row'] || $r > $coords['end_row']) continue;
                
                foreach ($cols as $c => $cellData) {
                    if ($c < $coords['start_col'] || $c > $coords['end_col']) continue;
                    
                    $cells["{$r}_{$c}"] = [
                        'row' => (int)$r,
                        'column' => (int)$c,
                        'value' => $cellData['value'] ?? null,
                        'formula' => $cellData['formula'] ?? null,
                        'formatted_value' => $cellData['formula'] ?? $cellData['value'] ?? null, // V1 Simple
                        'format' => [],
                    ];
                }
            }
        }
        
        // 2. Resolve precedence for formats
        // O(Formats * Cells_In_Format_Intersecting_Viewport)
        foreach ($formats as $formatRule) {
            $f = $formatRule->format_json;
            
            $startR = max($formatRule->start_row, $coords['start_row']);
            $endR = min($formatRule->end_row ?? $coords['end_row'], $coords['end_row']);
            
            $startC = max($formatRule->start_col, $coords['start_col']);
            $endC = min($formatRule->end_col ?? $coords['end_col'], $coords['end_col']);
            
            for ($r = $startR; $r <= $endR; $r++) {
                for ($c = $startC; $c <= $endC; $c++) {
                    if (!isset($cells["{$r}_{$c}"])) {
                        $cells["{$r}_{$c}"] = [
                            'row' => $r,
                            'column' => $c,
                            'value' => null,
                            'formula' => null,
                            'formatted_value' => null,
                            'format' => []
                        ];
                    }
                    
                    foreach ($f as $attr => $val) {
                        if ($val === null) {
                            unset($cells["{$r}_{$c}"]['format'][$attr]);
                        } else {
                            $cells["{$r}_{$c}"]['format'][$attr] = $val;
                        }
                    }
                }
            }
        }

        $sheetsMeta = $draft->sheets()->orderBy('index')->get(['uuid', 'title', 'index'])->map(function ($s) {
            return [
                'uuid' => $s->uuid,
                'title' => $s->title,
                'index' => $s->index,
                // Add default properties expected by UI
                'row_count' => 1000,
                'column_count' => 26,
                'frozen_rows' => 0,
                'frozen_columns' => 0,
            ];
        })->toArray();

        return [
            'artifact_uuid' => $draft->uuid,
            'type' => $draft->type,
            'status' => $draft->status->value,
            'title' => $draft->title,
            'revision' => $draft->revision,
            'sheets' => $sheetsMeta,
            'active_sheet' => [
                'uuid' => $sheet->uuid,
                'title' => $sheet->title,
                'index' => $sheet->index,
            ],
            'viewport' => [
                'range' => $range,
                'cells' => array_values($cells),
                'column_widths' => $sheet->dimensions_json['column_widths'] ?? (object)[],
                'row_heights' => $sheet->dimensions_json['row_heights'] ?? (object)[],
                'frozen_rows' => $sheet->properties_json['frozen_rows'] ?? 0,
                'frozen_columns' => $sheet->properties_json['frozen_columns'] ?? 0,
                'merged_ranges' => [],
            ]
        ];
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

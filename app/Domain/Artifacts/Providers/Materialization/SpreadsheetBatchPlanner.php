<?php

namespace App\Domain\Artifacts\Providers\Materialization;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetBatchPlannerInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValueRange;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValuesBatch;
use Generator;

class SpreadsheetBatchPlanner implements SpreadsheetBatchPlannerInterface
{
    public function __construct(
        private SpreadsheetMaterializationReaderInterface $reader
    ) {}

    public function planStructure(ArtifactDraft $draft): SpreadsheetStructureBatch
    {
        $sheets = $this->reader->getSheets($draft);
        
        $toCreate = [];
        $firstUuid = null;
        
        foreach ($sheets as $idx => $sheet) {
            if ($idx === 0) {
                $firstUuid = $sheet->uuid;
                $toCreate[] = ['uuid' => $sheet->uuid, 'title' => $sheet->title, 'index' => $sheet->index, 'is_first' => true];
            } else {
                $toCreate[] = ['uuid' => $sheet->uuid, 'title' => $sheet->title, 'index' => $sheet->index, 'is_first' => false];
            }
        }
        
        return new SpreadsheetStructureBatch(
            sheetsToCreate: $toCreate,
            sheetsToRename: [], // We don't rename yet in V1
            firstSheetUuid: $firstUuid
        );
    }
    
    public function planValues(
        ArtifactDraft $draft, 
        string $sheetUuid, 
        string|int $externalSheetId,
        SpreadsheetProviderCapabilities $capabilities
    ): Generator {
        $handle = new SpreadsheetProviderSheetHandle($sheetUuid, $externalSheetId, 'N/A');
        
        $currentRanges = [];
        $currentCells = 0;
        
        foreach ($this->reader->iterateValueChunks($draft, $sheetUuid) as $chunk) {
            $payload = $chunk->payload_json;
            if (empty($payload)) continue;
            
            // For V1, we turn each chunk into a block (bounds).
            // A smarter planner would trim empty rows/cols, but here we just convert the chunk bounds.
            $startRow = $chunk->chunk_row * 50;
            $startCol = $chunk->chunk_column * 50;
            
            $maxRowOffset = max(array_keys($payload));
            $maxColOffset = 0;
            foreach ($payload as $cols) {
                $maxC = max(array_keys($cols));
                if ($maxC > $maxColOffset) $maxColOffset = $maxC;
            }
            
            // Build the 2D array
            $matrix = [];
            for ($r = 0; $r <= $maxRowOffset; $r++) {
                $row = [];
                for ($c = 0; $c <= $maxColOffset; $c++) {
                    $cell = $payload[(string)$r][(string)$c] ?? null;
                    if ($cell && isset($cell['formula'])) {
                        $row[] = $cell['formula'];
                    } elseif ($cell && isset($cell['value'])) {
                        $row[] = $cell['value'];
                    } else {
                        $row[] = null;
                    }
                }
                $matrix[] = $row;
            }
            
            $cellsCount = count($matrix) * count($matrix[0]);
            
            if ($currentCells + $cellsCount > $capabilities->maxCellsPerValuesRequest && count($currentRanges) > 0) {
                yield new SpreadsheetValuesBatch($handle, $currentRanges);
                $currentRanges = [];
                $currentCells = 0;
            }
            
            $currentRanges[] = new SpreadsheetValueRange(
                startRow: $startRow,
                startCol: $startCol,
                endRow: $startRow + $maxRowOffset,
                endCol: $startCol + $maxColOffset,
                values: $matrix
            );
            $currentCells += $cellsCount;
        }
        
        if (count($currentRanges) > 0) {
            yield new SpreadsheetValuesBatch($handle, $currentRanges);
        }
    }
    
    public function planFormatting(
        ArtifactDraft $draft, 
        string $sheetUuid, 
        string|int $externalSheetId,
        SpreadsheetProviderCapabilities $capabilities
    ): Generator {
        $handle = new SpreadsheetProviderSheetHandle($sheetUuid, $externalSheetId, 'N/A');
        
        $operations = [];
        
        // 1. Convert formats
        $formats = $this->reader->iterateFormats($draft, $sheetUuid);
        foreach ($formats as $format) {
            $json = $format->format_json;
            foreach ($json as $key => $value) {
                // Map logical types
                $type = match($key) {
                    'bold', 'italic', 'strikethrough', 'underline' => 'text_style',
                    'background_color' => 'background_color',
                    'text_color' => 'text_color',
                    'number_format' => 'number_format',
                    default => 'unknown'
                };
                
                if ($type === 'unknown') continue;
                
                $operations[] = new SpreadsheetFormatOperation(
                    type: $type,
                    startRow: $format->start_row,
                    endRow: $format->end_row,
                    startCol: $format->start_col,
                    endCol: $format->end_col,
                    value: ['key' => $key, 'val' => $value]
                );
            }
        }
        
        // 2. Convert merges
        $merges = $this->reader->iterateMerges($draft, $sheetUuid);
        foreach ($merges as $merge) {
            $operations[] = new SpreadsheetFormatOperation(
                type: 'merge',
                startRow: $merge->start_row,
                endRow: $merge->end_row,
                startCol: $merge->start_col,
                endCol: $merge->end_col,
                value: []
            );
        }

        // 3. Convert Properties (Freeze)
        $props = $this->reader->getSheetProperties($draft, $sheetUuid);
        if (isset($props['properties']['frozen_rows']) || isset($props['properties']['frozen_columns'])) {
            $operations[] = new SpreadsheetFormatOperation(
                type: 'freeze',
                startRow: 0, endRow: 0, startCol: 0, endCol: 0,
                value: [
                    'rows' => $props['properties']['frozen_rows'] ?? 0,
                    'columns' => $props['properties']['frozen_columns'] ?? 0
                ]
            );
        }
        
        // Split operations into batches
        $chunks = array_chunk($operations, $capabilities->maxFormatOperationsPerBatch);
        foreach ($chunks as $chunk) {
            yield new SpreadsheetFormatBatch($handle, $chunk);
        }
    }
}

<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Enums\ArtifactDraftStatus;
use App\Domain\Artifacts\Exceptions\SpreadsheetMutationTooLargeException;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Repositories\SpreadsheetDraftRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSpreadsheetDraftService
{
    public function __construct(
        private SpreadsheetDraftRepositoryInterface $repository
    ) {}

    public function execute(int $organizationId, ?int $userId, ?string $conversationUuid, array $payload): ArtifactDraft
    {
        return DB::transaction(function () use ($organizationId, $userId, $conversationUuid, $payload) {
            $draft = ArtifactDraft::create([
                'uuid' => Str::uuid(),
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'conversation_uuid' => $conversationUuid,
                'type' => 'spreadsheet',
                'title' => $payload['title'] ?? 'Sem Título',
                'status' => ArtifactDraftStatus::DRAFT,
                'schema_version' => 1,
                'revision' => 1,
            ]);

            $this->repository->logChange($draft, 1, 'draft_created');

            $cellCount = 0;
            $maxCells = config('artifacts.spreadsheet.max_cells_per_mutation', 10000);

            if (isset($payload['sheets']) && is_array($payload['sheets'])) {
                foreach ($payload['sheets'] as $index => $sheetData) {
                    $sheet = $draft->sheets()->create([
                        'uuid' => Str::uuid(),
                        'index' => $index,
                        'title' => $sheetData['title'] ?? "Página " . ($index + 1),
                    ]);

                    if (isset($sheetData['updates']) && is_array($sheetData['updates'])) {
                        foreach ($sheetData['updates'] as $update) {
                            $cellCount += $this->applyValues($sheet, $update);
                            if ($cellCount > $maxCells) {
                                throw new SpreadsheetMutationTooLargeException();
                            }
                        }
                    }

                    if (isset($sheetData['formatting']) && is_array($sheetData['formatting'])) {
                        foreach ($sheetData['formatting'] as $format) {
                            $this->applyFormat($sheet, $format, 1);
                        }
                    }
                }
            }

            return $draft;
        });
    }

    private function applyValues($sheet, array $update): int
    {
        if (!isset($update['range']) || !isset($update['values'])) {
            return 0;
        }
        
        $range = $update['range'];
        // Parse range to get boundaries
        $coords = $this->parseRange($range);
        if (!$coords) return 0;
        
        $startRow = $coords['start_row'];
        $startCol = $coords['start_col'];
        
        $chunksToUpdate = [];
        $chunkRows = config('artifacts.spreadsheet.chunk_rows', 50);
        $chunkCols = config('artifacts.spreadsheet.chunk_columns', 50);
        
        $cellsProcessed = 0;
        
        foreach ($update['values'] as $rOffset => $rowValues) {
            $actualRow = $startRow + $rOffset;
            $cChunkRow = floor($actualRow / $chunkRows);
            
            foreach ($rowValues as $cOffset => $val) {
                $actualCol = $startCol + $cOffset;
                $cChunkCol = floor($actualCol / $chunkCols);
                
                $cellData = [];
                if (is_string($val) && str_starts_with($val, '=')) {
                    $cellData['formula'] = $val;
                } else {
                    $cellData['value'] = $val;
                }
                
                $chunksToUpdate["{$cChunkRow}_{$cChunkCol}"][$actualRow][$actualCol] = $cellData;
                $cellsProcessed++;
            }
        }
        
        foreach ($chunksToUpdate as $chunkKey => $payloadUpdates) {
            [$cR, $cC] = explode('_', $chunkKey);
            $this->repository->upsertChunkPayload($sheet, (int)$cR, (int)$cC, $payloadUpdates);
        }
        
        return $cellsProcessed;
    }

    private function applyFormat($sheet, array $formatRule, int $revision): void
    {
        if (!isset($formatRule['range'])) return;
        
        $coords = $this->parseRange($formatRule['range']);
        if (!$coords) return;
        
        $formatData = $formatRule['format'] ?? [];
        if ($formatRule['type'] === 'number_format') {
            $formatData = ['number_format' => $formatRule['format']];
        }
        
        $this->repository->addFormatRule(
            $sheet, 
            $revision, 
            $coords['start_row'], 
            $coords['end_row'], 
            $coords['start_col'], 
            $coords['end_col'], 
            $formatData
        );
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
        
        throw new \App\Domain\Artifacts\Exceptions\SpreadsheetDraftInvalidRangeException();
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

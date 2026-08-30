<?php

namespace App\Domain\Artifacts\Repositories;

use App\Domain\Artifacts\Exceptions\DraftRevisionConflictException;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\SpreadsheetDraftChunk;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpreadsheetDraftRepository implements SpreadsheetDraftRepositoryInterface
{
    public function incrementRevision(ArtifactDraft $draft, int $expectedRevision): void
    {
        $updated = DB::table('artifact_drafts')
            ->where('id', $draft->id)
            ->where('revision', $expectedRevision)
            ->update(['revision' => $expectedRevision + 1, 'updated_at' => now()]);
            
        if ($updated === 0) {
            throw new DraftRevisionConflictException();
        }
        
        $draft->revision = $expectedRevision + 1;
    }

    public function getChunksInViewport(SpreadsheetDraftSheet $sheet, int $startRow, int $endRow, int $startCol, int $endCol): Collection
    {
        $chunkRows = config('artifacts.spreadsheet.chunk_rows', 50);
        $chunkCols = config('artifacts.spreadsheet.chunk_columns', 50);

        $startChunkRow = floor($startRow / $chunkRows);
        $endChunkRow = floor($endRow / $chunkRows);
        $startChunkCol = floor($startCol / $chunkCols);
        $endChunkCol = floor($endCol / $chunkCols);

        return $sheet->chunks()
            ->whereBetween('chunk_row', [$startChunkRow, $endChunkRow])
            ->whereBetween('chunk_column', [$startChunkCol, $endChunkCol])
            ->get();
    }

    public function upsertChunkPayload(SpreadsheetDraftSheet $sheet, int $chunkRow, int $chunkCol, array $payloadUpdates): void
    {
        DB::transaction(function () use ($sheet, $chunkRow, $chunkCol, $payloadUpdates) {
            $chunk = SpreadsheetDraftChunk::firstOrCreate(
                ['sheet_id' => $sheet->id, 'chunk_row' => $chunkRow, 'chunk_column' => $chunkCol],
                ['payload_json' => [], 'uuid' => \Illuminate\Support\Str::uuid()]
            );

            $payload = $chunk->payload_json ?? [];

            foreach ($payloadUpdates as $r => $cols) {
                foreach ($cols as $c => $cellData) {
                    if (isset($cellData['clear']) && $cellData['clear']) {
                        Arr::forget($payload, "{$r}.{$c}");
                        if (empty($payload[$r])) {
                            Arr::forget($payload, "{$r}");
                        }
                    } else {
                        Arr::set($payload, "{$r}.{$c}", $cellData);
                    }
                }
            }

            if (empty($payload)) {
                $chunk->delete();
            } else {
                $chunk->payload_json = $payload;
                $chunk->revision += 1;
                $chunk->save();
            }
        });
    }

    public function deleteChunk(SpreadsheetDraftSheet $sheet, int $chunkRow, int $chunkCol): void
    {
        $sheet->chunks()->where('chunk_row', $chunkRow)->where('chunk_column', $chunkCol)->delete();
    }

    public function addFormatRule(SpreadsheetDraftSheet $sheet, int $revision, int $startRow, ?int $endRow, int $startCol, ?int $endCol, array $formatJson): void
    {
        DB::transaction(function () use ($sheet, $revision, $startRow, $endRow, $startCol, $endCol, $formatJson) {
            $maxIndex = $sheet->formats()->where('revision', $revision)->max('operation_index') ?? -1;
            
            $sheet->formats()->create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'revision' => $revision,
                'operation_index' => $maxIndex + 1,
                'start_row' => $startRow,
                'end_row' => $endRow,
                'start_col' => $startCol,
                'end_col' => $endCol,
                'format_json' => $formatJson,
            ]);
        });
    }

    public function getFormats(SpreadsheetDraftSheet $sheet): Collection
    {
        return $sheet->formats()->orderBy('revision', 'asc')->orderBy('operation_index', 'asc')->get();
    }

    public function logChange(ArtifactDraft $draft, int $revision, string $type, ?string $range = null, ?array $metadata = null, ?SpreadsheetDraftSheet $sheet = null): void
    {
        \App\Domain\Artifacts\Models\ArtifactDraftChange::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'revision' => $revision,
            'type' => $type,
            'range' => $range,
            'metadata_json' => $metadata,
            'sheet_uuid' => $sheet?->uuid,
        ]);
    }
}

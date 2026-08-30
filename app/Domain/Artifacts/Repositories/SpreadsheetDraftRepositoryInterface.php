<?php

namespace App\Domain\Artifacts\Repositories;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use Illuminate\Support\Collection;

interface SpreadsheetDraftRepositoryInterface
{
    /**
     * Increment the global revision of the artifact draft atomically.
     * Throws exception if expected_revision does not match the DB revision.
     */
    public function incrementRevision(ArtifactDraft $draft, int $expectedRevision): void;

    /**
     * Get chunks intersecting the given viewport range.
     */
    public function getChunksInViewport(SpreadsheetDraftSheet $sheet, int $startRow, int $endRow, int $startCol, int $endCol): Collection;

    /**
     * Write partial values to a specific chunk, creating it if it doesn't exist.
     */
    public function upsertChunkPayload(SpreadsheetDraftSheet $sheet, int $chunkRow, int $chunkCol, array $payloadUpdates): void;

    /**
     * Delete a chunk entirely.
     */
    public function deleteChunk(SpreadsheetDraftSheet $sheet, int $chunkRow, int $chunkCol): void;

    /**
     * Add a formatting rule to the sheet, using revision and operation index.
     */
    public function addFormatRule(SpreadsheetDraftSheet $sheet, int $revision, int $startRow, ?int $endRow, int $startCol, ?int $endCol, array $formatJson): void;
    
    /**
     * Get all formats for a sheet ordered by precedence (revision, operation_index).
     */
    public function getFormats(SpreadsheetDraftSheet $sheet): Collection;
    
    /**
     * Log a determinist change to the journal.
     */
    public function logChange(ArtifactDraft $draft, int $revision, string $type, ?string $range = null, ?array $metadata = null, ?SpreadsheetDraftSheet $sheet = null): void;
}

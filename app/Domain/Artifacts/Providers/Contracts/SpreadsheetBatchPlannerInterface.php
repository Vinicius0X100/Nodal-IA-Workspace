<?php

namespace App\Domain\Artifacts\Providers\Contracts;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use Generator;

interface SpreadsheetBatchPlannerInterface
{
    public function planStructure(ArtifactDraft $draft): SpreadsheetStructureBatch;
    
    public function planValues(
        ArtifactDraft $draft, 
        string $sheetUuid, 
        string|int $externalSheetId,
        SpreadsheetProviderCapabilities $capabilities
    ): Generator; // Yields SpreadsheetValuesBatch
    
    public function planFormatting(
        ArtifactDraft $draft, 
        string $sheetUuid, 
        string|int $externalSheetId,
        SpreadsheetProviderCapabilities $capabilities
    ): Generator; // Yields SpreadsheetFormatBatch
}

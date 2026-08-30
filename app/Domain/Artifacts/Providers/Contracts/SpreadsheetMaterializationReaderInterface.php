<?php

namespace App\Domain\Artifacts\Providers\Contracts;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetSheetSnapshot;

interface SpreadsheetMaterializationReaderInterface
{
    /**
     * @return iterable<SpreadsheetSheetSnapshot>
     */
    public function getSheets(ArtifactDraft $draft): iterable;
    
    public function iterateValueChunks(ArtifactDraft $draft, string $sheetUuid): iterable;
    
    public function iterateFormats(ArtifactDraft $draft, string $sheetUuid): iterable;
    
    public function iterateMerges(ArtifactDraft $draft, string $sheetUuid): iterable;
    
    public function getSheetProperties(ArtifactDraft $draft, string $sheetUuid): array;
}

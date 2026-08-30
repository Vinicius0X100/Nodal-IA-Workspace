<?php

namespace App\Domain\Artifacts\Providers\Contracts;

use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCreateCommand;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCommitIdentity;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureResult;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetValuesBatch;

interface SpreadsheetProviderInterface
{
    public function capabilities(): SpreadsheetProviderCapabilities;
    
    public function createSpreadsheet(SpreadsheetCreateCommand $command): SpreadsheetProviderResource;
    
    public function findByCommitKey(SpreadsheetCommitIdentity $identity): ?SpreadsheetProviderResource;
    
    public function prepareStructure(SpreadsheetProviderResource $resource, SpreadsheetStructureBatch $batch): SpreadsheetStructureResult;
    
    public function writeValues(SpreadsheetProviderResource $resource, SpreadsheetValuesBatch $batch): void;
    
    public function applyFormatting(SpreadsheetProviderResource $resource, SpreadsheetFormatBatch $batch): void;
    
    public function cleanup(SpreadsheetProviderResource $resource): void;
}

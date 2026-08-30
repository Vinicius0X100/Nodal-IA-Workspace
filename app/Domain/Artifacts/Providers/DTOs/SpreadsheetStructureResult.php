<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetStructureResult
{
    /**
     * @param SpreadsheetProviderSheetHandle[] $sheetHandles
     */
    public function __construct(
        public array $sheetHandles
    ) {}
}

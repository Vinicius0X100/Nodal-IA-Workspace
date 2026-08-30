<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetValuesBatch
{
    /**
     * @param SpreadsheetProviderSheetHandle $sheetHandle
     * @param SpreadsheetValueRange[] $ranges
     */
    public function __construct(
        public SpreadsheetProviderSheetHandle $sheetHandle,
        public array $ranges
    ) {}
}

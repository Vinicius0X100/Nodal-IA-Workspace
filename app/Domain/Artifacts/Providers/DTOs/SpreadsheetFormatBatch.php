<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetFormatBatch
{
    /**
     * @param SpreadsheetProviderSheetHandle $sheetHandle
     * @param SpreadsheetFormatOperation[] $operations
     */
    public function __construct(
        public SpreadsheetProviderSheetHandle $sheetHandle,
        public array $operations
    ) {}
}

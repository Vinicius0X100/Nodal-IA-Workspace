<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetProviderSheetHandle
{
    public function __construct(
        public string $draftSheetUuid,
        public string|int $externalSheetId, // Can be int (Google) or string (Excel)
        public string $title
    ) {}
}

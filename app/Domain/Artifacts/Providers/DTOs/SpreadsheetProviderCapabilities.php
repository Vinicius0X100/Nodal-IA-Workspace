<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetProviderCapabilities
{
    public function __construct(
        public bool $supportsMultipleSheets,
        public bool $supportsMerge,
        public bool $supportsFreeze,
        public bool $supportsAutoResize,
        public bool $supportsNumberFormat,
        public bool $supportsRowHeight,
        public bool $supportsColumnWidth,
        public int $maxCellsPerValuesRequest,
        public int $maxRangesPerValuesRequest,
        public int $maxRequestsPerBatch,
        public int $maxFormatOperationsPerBatch,
        public int $maxPayloadBytes
    ) {}
}

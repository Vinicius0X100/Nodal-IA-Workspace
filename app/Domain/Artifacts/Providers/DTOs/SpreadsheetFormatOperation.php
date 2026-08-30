<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetFormatOperation
{
    public function __construct(
        public string $type, // 'background_color', 'bold', 'number_format', 'freeze', 'merge', etc.
        public int $startRow,
        public int $endRow,
        public int $startCol,
        public int $endCol,
        public mixed $value // The specific value (e.g. '#FFF', 'CURRENCY', true)
    ) {}
}

<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetValueRange
{
    public function __construct(
        public int $startRow,
        public int $startCol,
        public int $endRow, // Inclusive
        public int $endCol, // Inclusive
        public array $values // 2D array of values
    ) {}
}

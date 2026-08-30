<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetSheetSnapshot
{
    public function __construct(
        public string $uuid,
        public int $index,
        public string $title
    ) {}
}

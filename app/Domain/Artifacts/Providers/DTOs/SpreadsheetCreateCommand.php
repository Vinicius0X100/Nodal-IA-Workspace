<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetCreateCommand
{
    public function __construct(
        public string $title,
        public SpreadsheetCommitIdentity $identity
    ) {}
}

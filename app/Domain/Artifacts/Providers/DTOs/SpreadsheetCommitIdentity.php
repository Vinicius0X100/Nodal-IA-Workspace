<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetCommitIdentity
{
    public function __construct(
        public string $commitUuid
    ) {}
}

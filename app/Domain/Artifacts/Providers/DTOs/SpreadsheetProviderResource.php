<?php

namespace App\Domain\Artifacts\Providers\DTOs;

readonly class SpreadsheetProviderResource
{
    public function __construct(
        public string $externalId,
        public string $externalUrl
    ) {}
}

<?php

namespace App\Domain\Artifacts\Providers\Contracts;

use App\Domain\Integrations\Models\Integration;

interface SpreadsheetProviderResolverInterface
{
    public function resolve(Integration $integration): SpreadsheetProviderInterface;
}

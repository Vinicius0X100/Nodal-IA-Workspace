<?php

namespace App\Domain\Artifacts\Providers;

use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnsupportedOperationException;

class SpreadsheetProviderResolver implements SpreadsheetProviderResolverInterface
{
    public function resolve(Integration $integration): SpreadsheetProviderInterface
    {
        if ($integration->provider === 'google_workspace') {
            return app(\App\Domain\Artifacts\Providers\Google\GoogleSpreadsheetProvider::class);
        }
        
        throw new SpreadsheetProviderUnsupportedOperationException("Spreadsheet provider not supported for: {$integration->provider}");
    }
}

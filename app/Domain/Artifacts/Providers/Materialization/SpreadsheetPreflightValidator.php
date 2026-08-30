<?php

namespace App\Domain\Artifacts\Providers\Materialization;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnsupportedOperationException;

class SpreadsheetPreflightValidator
{
    public function validate(ArtifactDraft $draft, SpreadsheetProviderCapabilities $capabilities): bool
    {
        $sheetsCount = $draft->sheets()->count();
        if ($sheetsCount > 1 && !$capabilities->supportsMultipleSheets) {
            throw new SpreadsheetProviderUnsupportedOperationException("Provider does not support multiple sheets.");
        }
        
        // V1 simple validation:
        // Checking formats
        foreach ($draft->sheets as $sheet) {
            $props = $sheet->properties_json ?? [];
            if ((isset($props['frozen_rows']) || isset($props['frozen_columns'])) && !$capabilities->supportsFreeze) {
                throw new SpreadsheetProviderUnsupportedOperationException("Provider does not support frozen rows/columns.");
            }
            
            // Checking formats
            $formats = $sheet->formats;
            foreach ($formats as $format) {
                $keys = array_keys($format->format_json);
                if (in_array('number_format', $keys) && !$capabilities->supportsNumberFormat) {
                    throw new SpreadsheetProviderUnsupportedOperationException("Provider does not support number formats.");
                }
            }
        }
        
        return true; // SUPPORTED
    }
}

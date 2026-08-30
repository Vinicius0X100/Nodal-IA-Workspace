<?php

namespace App\Domain\Artifacts\Providers\Materialization;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetSheetSnapshot;

class SpreadsheetMaterializationReader implements SpreadsheetMaterializationReaderInterface
{
    public function getSheets(ArtifactDraft $draft): iterable
    {
        return $draft->sheets()->orderBy('index')->get()->map(function ($sheet) {
            return new SpreadsheetSheetSnapshot($sheet->uuid, $sheet->index, $sheet->title);
        });
    }
    
    public function iterateValueChunks(ArtifactDraft $draft, string $sheetUuid): iterable
    {
        $sheet = $draft->sheets()->where('uuid', $sheetUuid)->first();
        if (!$sheet) return [];

        $query = $sheet->chunks()->orderBy('chunk_row')->orderBy('chunk_column');
        
        foreach ($query->cursor() as $chunk) {
            yield $chunk;
        }
    }
    
    public function iterateFormats(ArtifactDraft $draft, string $sheetUuid): iterable
    {
        $sheet = $draft->sheets()->where('uuid', $sheetUuid)->first();
        if (!$sheet) return [];
        
        // Ordered semantically: older revisions first, so newer override them correctly via Google
        $query = $sheet->formats()
            ->orderBy('revision', 'asc')
            ->orderBy('operation_index', 'asc');
            
        foreach ($query->cursor() as $format) {
            yield $format;
        }
    }

    public function iterateMerges(ArtifactDraft $draft, string $sheetUuid): iterable
    {
        $sheet = $draft->sheets()->where('uuid', $sheetUuid)->first();
        if (!$sheet) return [];
        
        // Return merges
        $query = $sheet->merges()
            ->orderBy('id', 'asc');
            
        foreach ($query->cursor() as $merge) {
            yield $merge;
        }
    }
    
    public function getSheetProperties(ArtifactDraft $draft, string $sheetUuid): array
    {
        $sheet = $draft->sheets()->where('uuid', $sheetUuid)->first();
        if (!$sheet) return [];
        
        return [
            'properties' => $sheet->properties_json ?? [],
            'dimensions' => $sheet->dimensions_json ?? [],
        ];
    }
}

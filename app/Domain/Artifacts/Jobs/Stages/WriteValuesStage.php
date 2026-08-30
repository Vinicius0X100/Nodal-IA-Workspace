<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface;
use App\Domain\Artifacts\Providers\SpreadsheetBatchPlanner;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle;

class WriteValuesStage implements StageInterface
{
    private SpreadsheetProviderResolverInterface $resolver;
    private SpreadsheetMaterializationReaderInterface $reader;
    private SpreadsheetBatchPlanner $planner;

    public function __construct(
        SpreadsheetProviderResolverInterface $resolver,
        SpreadsheetMaterializationReaderInterface $reader,
        SpreadsheetBatchPlanner $planner
    ) {
        $this->resolver = $resolver;
        $this->reader = $reader;
        $this->planner = $planner;
    }

    public function execute(ArtifactCommitAttempt $attempt): StageResult
    {
        $draft = $attempt->artifactDraft;
        
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $draft->organization_id)
            ->where('provider', $attempt->provider)
            ->firstOrFail();

        $provider = $this->resolver->resolve($integration);
        
        $resource = new SpreadsheetProviderResource(
            externalId: $attempt->provider_external_id,
            externalUrl: ''
        );

        $capabilities = $provider->getCapabilities();
        $maxBatchesPerJob = config('artifacts.commit.max_batches_per_job', 5);

        // Resume from checkpoint
        $checkpoint = $attempt->checkpoint_json ?? [];
        $processedBatchesThisRun = 0;
        
        $sheets = $draft->sheets()->orderBy('index')->get();

        foreach ($sheets as $sheet) {
            $mapping = $attempt->sheetMappings()->where('draft_sheet_uuid', $sheet->uuid)->first();
            if (!$mapping) {
                continue; // Should not happen if PrepareStructureStage finished properly
            }
            
            $handle = new SpreadsheetProviderSheetHandle(
                draftSheetUuid: $sheet->uuid,
                externalSheetId: $mapping->provider_sheet_identifier,
                title: $sheet->title
            );

            // Fetch chunks
            $chunksIterable = $this->reader->iterateValueChunks($draft, $sheet->uuid);
            $batches = $this->planner->planValues($draft, $sheet->uuid, $chunksIterable, $capabilities);

            foreach ($batches as $batchIndex => $batch) {
                $checkpointKey = "values_{$sheet->uuid}_{$batchIndex}";

                // Skip if already processed in previous runs
                if (isset($checkpoint['completed_batches'][$checkpointKey])) {
                    continue;
                }
                
                // We inject the handle into the batch for the provider
                $batch->sheetHandle = $handle;

                // Write to provider
                $provider->writeValues($resource, $batch);
                
                // Update checkpoint
                $checkpoint['completed_batches'][$checkpointKey] = true;
                $checkpoint['processed_batches'] = ($checkpoint['processed_batches'] ?? 0) + 1;
                $attempt->update(['checkpoint_json' => $checkpoint]);
                
                $processedBatchesThisRun++;
                
                // Enforce bounded work
                if ($processedBatchesThisRun >= $maxBatchesPerJob) {
                    return new StageResult(false);
                }
            }
        }

        return new StageResult(true);
    }
}

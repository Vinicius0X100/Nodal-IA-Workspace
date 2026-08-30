<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource;
use App\Domain\Artifacts\Models\ArtifactCommitSheetMapping;

class PrepareStructureStage implements StageInterface
{
    private SpreadsheetProviderResolverInterface $resolver;

    public function __construct(SpreadsheetProviderResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    public function execute(ArtifactCommitAttempt $attempt): StageResult
    {
        $draft = $attempt->artifactDraft;
        
        // 1. Check if mapping already exists (Idempotency)
        $hasMappings = $attempt->sheetMappings()->exists();
        if ($hasMappings) {
            return new StageResult(true);
        }

        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $draft->organization_id)
            ->where('provider', $attempt->provider)
            ->firstOrFail();

        $provider = $this->resolver->resolve($integration);
        
        $resource = new SpreadsheetProviderResource(
            externalId: $attempt->provider_external_id,
            externalUrl: '' // not needed for this API call
        );

        // 2. Prepare structure
        $sheetsData = [];
        foreach ($draft->sheets()->orderBy('index')->get() as $sheet) {
            $sheetsData[] = [
                'uuid' => $sheet->uuid,
                'title' => $sheet->title,
                'index' => $sheet->index
            ];
        }

        $batch = new SpreadsheetStructureBatch($sheetsData, [], null);
        $result = $provider->prepareStructure($resource, $batch);

        // 3. Save mappings deterministically
        foreach ($result->sheetHandles as $draftUuid => $handle) {
            ArtifactCommitSheetMapping::create([
                'artifact_commit_attempt_id' => $attempt->id,
                'draft_sheet_uuid' => $draftUuid,
                'provider_sheet_identifier' => $handle->externalSheetId,
            ]);
        }

        return new StageResult(true);
    }
}

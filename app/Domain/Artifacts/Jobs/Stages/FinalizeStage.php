<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Support\Str;

class FinalizeStage implements StageInterface
{
    public function execute(ArtifactCommitAttempt $attempt): StageResult
    {
        $draft = $attempt->artifactDraft;
        
        // Find integration
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $draft->organization_id)
            ->where('provider', $attempt->provider)
            ->first();
            
        if (!$integration) {
            throw new \Exception("Integration not found for provider {$attempt->provider}");
        }

        // 1. Create or reuse IntegrationResource
        // The attempt_uuid ensures we never create duplicated resources on crash
        $integrationResource = IntegrationResource::firstOrCreate(
            [
                'integration_id' => $integration->id,
                'external_id' => $attempt->provider_external_id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'provider' => \App\Domain\Resources\Enums\Provider::from($attempt->provider),
                'resource_type' => \App\Domain\Resources\Enums\ResourceType::from('spreadsheet'),
                'name' => $draft->title ?? 'Nodal Artifact',
                'metadata_json' => [
                    'source_commit_uuid' => $attempt->commit_uuid
                ]
            ]
        );

        // 2. Finalize draft
        $draft->update([
            'status' => 'committed',
            'committed_resource_uuid' => $integrationResource->uuid
        ]);

        $attempt->update([
            'status' => 'succeeded',
            'finished_at' => now(),
            // Ensure stage moves to completed state explicitly
            'current_stage' => 'completed'
        ]);

        return new StageResult(true); // Job completes
    }
}

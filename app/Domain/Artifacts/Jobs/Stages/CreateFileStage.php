<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCreateCommand;
use Exception;

class CreateFileStage implements StageInterface
{
    private SpreadsheetProviderResolverInterface $resolver;

    public function __construct(SpreadsheetProviderResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    public function execute(ArtifactCommitAttempt $attempt): StageResult
    {
        // Check if external ID is already resolved (Idempotency)
        if ($attempt->provider_external_id) {
            return new StageResult(true);
        }

        $draft = $attempt->artifactDraft;
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $draft->organization_id)
            ->where('provider', $attempt->provider)
            ->firstOrFail();

        $provider = $this->resolver->resolve($integration);

        $identity = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetCommitIdentity($attempt->commit_uuid);
        $existingResource = $provider->findByCommitKey($identity);
        
        if ($existingResource) {
            $attempt->update(['provider_external_id' => $existingResource->externalId]);
            return new StageResult(true);
        }

        // 2. Create if not found
        $command = new SpreadsheetCreateCommand(
            title: $draft->title ?? 'Nodal Artifact',
            commitKey: $attempt->commit_uuid
        );

        $resource = $provider->createSpreadsheet($command);
        
        $attempt->update(['provider_external_id' => $resource->externalId]);

        return new StageResult(true);
    }
}

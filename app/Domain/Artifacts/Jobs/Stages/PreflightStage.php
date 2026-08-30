<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Providers\SpreadsheetPreflightValidator;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface;

class PreflightStage implements StageInterface
{
    private SpreadsheetProviderResolverInterface $resolver;
    private SpreadsheetPreflightValidator $validator;
    private SpreadsheetMaterializationReaderInterface $reader;

    public function __construct(
        SpreadsheetProviderResolverInterface $resolver,
        SpreadsheetPreflightValidator $validator,
        SpreadsheetMaterializationReaderInterface $reader
    ) {
        $this->resolver = $resolver;
        $this->validator = $validator;
        $this->reader = $reader;
    }

    public function execute(ArtifactCommitAttempt $attempt): StageResult
    {
        $draft = $attempt->artifactDraft;
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $draft->organization_id)
            ->where('provider', $attempt->provider)
            ->firstOrFail();

        $provider = $this->resolver->resolve($integration);
        $capabilities = $provider->getCapabilities();

        // The preflight validator checks if the draft structure can be applied
        // It throws SpreadsheetProviderUnsupportedOperationException if it fails
        $this->validator->validate($capabilities, $draft, $this->reader);

        return new StageResult(true);
    }
}

<?php

namespace App\Domain\Artifacts\Jobs\Stages;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;

interface StageInterface
{
    /**
     * Executes a bound of work for the given stage.
     * 
     * @param ArtifactCommitAttempt $attempt The attempt being processed.
     * @return StageResult
     * @throws \Throwable Permanent or transient exceptions.
     */
    public function execute(ArtifactCommitAttempt $attempt): StageResult;
}

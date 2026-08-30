<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Enums\ArtifactDraftStatus;
use App\Domain\Artifacts\Models\ArtifactDraft;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ArtifactDraftService
{
    /**
     * Transition the state to committing and lock the draft.
     */
    public function transitionToCommitting(ArtifactDraft $draft): ArtifactDraft
    {
        return DB::transaction(function () use ($draft) {
            $lockedDraft = ArtifactDraft::where('id', $draft->id)->lockForUpdate()->first();
            
            if ($lockedDraft->status !== ArtifactDraftStatus::DRAFT && $lockedDraft->status !== ArtifactDraftStatus::FAILED) {
                throw new RuntimeException("ARTIFACT_DRAFT_NOT_EDITABLE");
            }
            
            $lockedDraft->status = ArtifactDraftStatus::COMMITTING;
            $lockedDraft->save();
            
            return $lockedDraft;
        });
    }

    /**
     * Transition to failed.
     */
    public function transitionToFailed(ArtifactDraft $draft): void
    {
        $draft->update(['status' => ArtifactDraftStatus::FAILED]);
    }

    /**
     * Transition to committed.
     */
    public function transitionToCommitted(ArtifactDraft $draft, string $resourceUuid): void
    {
        $draft->update([
            'status' => ArtifactDraftStatus::COMMITTED,
            'committed_resource_uuid' => $resourceUuid
        ]);
    }
}

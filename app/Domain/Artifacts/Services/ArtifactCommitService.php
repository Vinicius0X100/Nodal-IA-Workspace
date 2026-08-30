<?php

namespace App\Domain\Artifacts\Services;

use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Jobs\MaterializeArtifactDraftJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ArtifactCommitService
{
    /**
     * Commits a draft asynchronously.
     * Must be called in an authenticated context where we know the organization.
     */
    public function commit(string $artifactUuid, int $organizationId): array
    {
        return DB::transaction(function () use ($artifactUuid, $organizationId) {
            $draft = ArtifactDraft::where('uuid', $artifactUuid)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (!$draft) {
                throw new NotFoundHttpException('ArtifactDraft not found or you lack permission.');
            }

            // 1. If committed
            if ($draft->status === \App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTED) {
                return [
                    'artifact_uuid' => $draft->uuid,
                    'commit_uuid' => null,
                    'resource_uuid' => $draft->committed_resource_uuid,
                    'status' => 'committed',
                ];
            }

            // 2. If committing
            if ($draft->status === \App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTING) {
                $attempt = $draft->commitAttempts()->orderBy('id', 'desc')->first();
                
                // Recover dispatch failure: if it is still pending, redispatch safely.
                // The lockForUpdate inside the job prevents race conditions if a worker is just starting it.
                if ($attempt && $attempt->status === 'pending') {
                    DB::afterCommit(function () use ($attempt) {
                        MaterializeArtifactDraftJob::dispatch($attempt->id);
                    });
                }

                return [
                    'artifact_uuid' => $draft->uuid,
                    'commit_uuid' => $attempt?->commit_uuid,
                    'status' => 'committing',
                ];
            }

            // 3. If draft
            if ($draft->status !== \App\Domain\Artifacts\Enums\ArtifactDraftStatus::DRAFT) {
                throw new InvalidArgumentException("Draft is in invalid state for commit: {$draft->status->value}");
            }

            $attempt = ArtifactCommitAttempt::create([
                'commit_uuid' => (string) Str::uuid(),
                'artifact_draft_id' => $draft->id,
                'source_revision' => $draft->revision,
                'provider' => 'google_workspace', // Later this can be dynamic or fetched from Integration
                'status' => 'pending',
                'current_stage' => 'preflight',
                'attempt_number' => 1,
            ]);

            $draft->update([
                'status' => \App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTING
            ]);

            // Dispatch after commit
            DB::afterCommit(function () use ($attempt) {
                MaterializeArtifactDraftJob::dispatch($attempt->id);
            });

            return [
                'artifact_uuid' => $draft->uuid,
                'commit_uuid' => $attempt->commit_uuid,
                'status' => 'committing',
            ];
        });
    }

    public function getStatus(string $artifactUuid, int $organizationId): array
    {
        $draft = ArtifactDraft::where('uuid', $artifactUuid)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$draft) {
            throw new NotFoundHttpException('ArtifactDraft not found.');
        }

        if ($draft->status === \App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTED) {
            return [
                'artifact_uuid' => $draft->uuid,
                'commit_uuid' => null,
                'status' => 'committed',
                'resource_uuid' => $draft->committed_resource_uuid,
            ];
        }

        $attempt = $draft->commitAttempts()->orderBy('id', 'desc')->first();
        
        if (!$attempt) {
            return [
                'artifact_uuid' => $draft->uuid,
                'status' => $draft->status->value,
            ];
        }

        if ($attempt->status === 'failed') {
            return [
                'artifact_uuid' => $draft->uuid,
                'commit_uuid' => $attempt->commit_uuid,
                'status' => 'failed',
                'stage' => $attempt->current_stage,
                'error' => $attempt->error_payload ?? [],
            ];
        }

        return [
            'artifact_uuid' => $draft->uuid,
            'commit_uuid' => $attempt->commit_uuid,
            'status' => $draft->status->value, // usually committing
            'stage' => $attempt->current_stage,
            'progress' => [
                'processed_batches' => $attempt->checkpoint_json['processed_batches'] ?? 0,
                'total_batches' => null
            ]
        ];
    }
}

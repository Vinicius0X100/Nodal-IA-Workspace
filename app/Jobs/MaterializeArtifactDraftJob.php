<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use Illuminate\Support\Facades\DB;
use App\Domain\Artifacts\Jobs\Stages\PreflightStage;
use App\Domain\Artifacts\Jobs\Stages\CreateFileStage;
use App\Domain\Artifacts\Jobs\Stages\PrepareStructureStage;
use App\Domain\Artifacts\Jobs\Stages\WriteValuesStage;
use App\Domain\Artifacts\Jobs\Stages\ApplyFormatsStage;
use App\Domain\Artifacts\Jobs\Stages\FinalizeStage;
use App\Domain\Artifacts\Exceptions\SpreadsheetProviderTransientException;

class MaterializeArtifactDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [10, 30, 60];
    public $timeout = 120; // 2 minutes max per execution to ensure bounded work

    private int $attemptId;

    public function __construct(int $attemptId)
    {
        $this->attemptId = $attemptId;
    }

    public function handle(): void
    {
        $stageResult = DB::transaction(function () {
            $attempt = ArtifactCommitAttempt::where('id', $this->attemptId)
                ->lockForUpdate()
                ->first();

            if (!$attempt) {
                return 'abort';
            }

            if ($attempt->status === 'succeeded' || $attempt->status === 'failed') {
                return 'abort';
            }
            
            if ($attempt->status === 'pending') {
                $attempt->update(['status' => 'working', 'started_at' => now()]);
            }

            try {
                return $this->executeStage($attempt);
            } catch (SpreadsheetProviderTransientException $e) {
                // We let the job fail so Laravel Queue retries it
                throw $e;
            } catch (\Throwable $e) {
                // Permanent failure — mark attempt AND draft as failed so the
                // frontend does not get stuck in "committing" forever.
                $draft = $attempt->artifactDraft;

                $attempt->update([
                    'status'        => 'failed',
                    'finished_at'   => now(),
                    'error_payload' => [
                        'message' => $e->getMessage(),
                        'code'    => $e->getCode(),
                        'stage'   => $attempt->current_stage,
                    ]
                ]);

                // Propagate failure to the Draft only when no external resource
                // was created yet (provider_external_id is null). If the file
                // already exists on Google, the draft stays in committing so a
                // future retry can reconcile rather than leaving an orphan.
                if (empty($attempt->provider_external_id) && $draft) {
                    $draft->update(['status' => \App\Domain\Artifacts\Enums\ArtifactDraftStatus::FAILED]);
                }

                // Cleanup/Restart logic will be evaluated by manual/cron processes for V1
                return 'abort';
            }
        });

        if ($stageResult === 'continue') {
            self::dispatch($this->attemptId); // Redispatch for next bound/stage
        }
    }

    private function executeStage(ArtifactCommitAttempt $attempt): string
    {
        $stages = [
            'preflight' => PreflightStage::class,
            'create_file' => CreateFileStage::class,
            'prepare_structure' => PrepareStructureStage::class,
            'write_values' => WriteValuesStage::class,
            'apply_formats' => ApplyFormatsStage::class,
            'finalize' => FinalizeStage::class,
        ];

        $stageClass = $stages[$attempt->current_stage] ?? null;
        if (!$stageClass) {
            throw new \Exception("Invalid stage: {$attempt->current_stage}");
        }

        /** @var \App\Domain\Artifacts\Jobs\Stages\StageInterface $executor */
        $executor = app($stageClass);
        $result = $executor->execute($attempt);

        if ($result->isComplete) {
            $nextStage = $this->getNextStage($attempt->current_stage);
            if ($nextStage) {
                $attempt->update(['current_stage' => $nextStage]);
                return 'continue';
            } else {
                return 'abort'; // Finished all stages!
            }
        }

        // Bounded work not yet finished for this stage
        return 'continue';
    }

    private function getNextStage(string $currentStage): ?string
    {
        $flow = ['preflight', 'create_file', 'prepare_structure', 'write_values', 'apply_formats', 'finalize'];
        $idx = array_search($currentStage, $flow);
        if ($idx !== false && isset($flow[$idx + 1])) {
            return $flow[$idx + 1];
        }
        return null;
    }
}

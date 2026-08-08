<?php

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleDirectorySyncService;
use App\Domain\Directory\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleGroupMembersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Integration $integration,
        public Group $group
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleDirectorySyncService $syncService): void
    {
        try {
            $syncService->syncGroupMembers($this->integration, $this->group);
        } catch (\Exception $e) {
            Log::error("Erro no SyncGoogleGroupMembersJob", ['error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-google-directory-groups')]
#[Description('Command description')]
class SyncGoogleDirectoryGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:sync-google-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs already imported Google Workspace groups data and memberships';

    /**
     * Execute the console command.
     */
    public function handle(\App\Domain\Integrations\Services\GoogleDirectorySyncService $syncService)
    {
        $this->info('Starting Google Groups Sync...');
        
        $integrations = \App\Domain\Integrations\Models\Integration::where('provider', 'google_workspace')
            ->where('status', 'connected')
            ->get();
            
        foreach ($integrations as $integration) {
            $this->info("Syncing groups for integration: {$integration->id}");
            $syncService->syncImportedGroups($integration);
        }
        
        $this->info('Sync completed successfully.');
    }
}

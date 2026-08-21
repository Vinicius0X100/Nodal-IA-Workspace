<?php

namespace App\Domain\Resources\Jobs;

use App\Domain\Audit\Actions\LogAuditAction;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Services\ResourceSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProviderResourcesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour max
    public $uniqueFor = 3600; // Tempo máximo que o job fica bloqueado caso falhe e não libere a trava

    public function __construct(
        public Integration $integration,
        public ?string $userId = null // Who triggered the sync
    ) {
    }

    /**
     * O ID único do Job é o ID da integração.
     * Isso garante que apenas um sync rode por vez por cliente.
     */
    public function uniqueId(): string
    {
        return (string) $this->integration->id;
    }

    public function handle(
        ResourceSyncService $syncService,
        LogAuditAction $logAction
    ): void {
        try {
            // Fake the session for LogAuditAction if needed, or pass it directly.
            // Since it's a CLI/Job context, we set the active_organization_id manually
            session(['active_organization_id' => $this->integration->organization_id]);

            // Log start
            $logAction->execute(
                'resource_sync_started',
                'Integration',
                (string) $this->integration->id,
                ['provider' => $this->integration->provider]
            );

            // Run Sync
            $syncService->sync($this->integration);

            // Log completion
            $logAction->execute(
                'resource_sync_completed',
                'Integration',
                (string) $this->integration->id,
                ['provider' => $this->integration->provider]
            );

        } catch (Exception $e) {
            // Log failure
            $logAction->execute(
                'resource_sync_failed',
                'Integration',
                (string) $this->integration->id,
                [
                    'provider' => $this->integration->provider,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw $e;
        } finally {
            // Limpar sessao
            session()->forget('active_organization_id');
        }
    }
}

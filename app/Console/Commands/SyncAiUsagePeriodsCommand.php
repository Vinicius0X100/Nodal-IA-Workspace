<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Console\Command;

class SyncAiUsagePeriodsCommand extends Command
{
    protected $signature = 'billing:sync-periods';
    protected $description = 'Sincroniza os períodos de uso abertos com as assinaturas efetivas.';

    public function handle(BillingSubscriptionService $service): int
    {
        $this->info('Iniciando sincronização de períodos de uso...');

        // Busca todas as organizações que têm períodos abertos
        $orgIds = AiUsagePeriod::where('status', 'open')
            ->distinct()
            ->pluck('organization_id');

        $organizations = Organization::whereIn('id', $orgIds)->get();

        $count = 0;
        foreach ($organizations as $org) {
            $service->syncCurrentPeriod($org);
            $count++;
            $this->line("Organização {$org->id} sincronizada.");
        }

        $this->info("Sincronização concluída! {$count} organizações processadas.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Billing\Services\BillingPeriodClosingService;
use Illuminate\Console\Command;

class BillingClosePeriodsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:close-periods 
                            {--dry-run : Simula o fechamento sem alterar o banco de dados} 
                            {--organization= : ID específico de organização para processar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Executa o fechamento de períodos de faturamento vencidos e emite as faturas correspondentes.';

    /**
     * Execute the console command.
     */
    public function handle(BillingPeriodClosingService $closingService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orgId = $this->option('organization') ? (int) $this->option('organization') : null;

        if ($dryRun) {
            $this->warn("🔍 MODO SIMULAÇÃO (DRY-RUN): Nenhuma fatura será gerada e nenhum período será alterado.\n");
        } else {
            $this->info("⚙️ Iniciando fechamento de períodos de faturamento...\n");
        }

        $result = $closingService->closeDuePeriods(dryRun: $dryRun, organizationId: $orgId);

        if ($result['found'] === 0) {
            $this->info("Nenhum período elegível para fechamento no momento (status=open e period_end < agora).");
            return 0;
        }

        if ($dryRun) {
            $rows = array_map(function ($item) {
                return [
                    $item['organization_name'] . " (#{$item['organization_id']})",
                    $item['plan_name'],
                    $item['period_start'] . ' a ' . $item['period_end'],
                    'R$ ' . number_format($item['monthly_price_cents'] / 100, 2, ',', '.'),
                    number_format($item['included_credits'], 0, ',', '.'),
                    number_format($item['billable_credits_used'], 2, ',', '.'),
                    number_format($item['overage_credits'], 2, ',', '.'),
                    'R$ ' . number_format($item['billed_overage_cents'] / 100, 2, ',', '.') . ($item['postpaid_limit_applied'] ? ' (Teto)' : ''),
                    'R$ ' . number_format($item['total_cents'] / 100, 2, ',', '.'),
                ];
            }, $result['details']);

            $this->table(
                ['Organização', 'Plano', 'Período', 'Mensalidade', 'Franquia', 'Uso', 'Excedente', 'Adicional', 'Total Previsto'],
                $rows
            );

            $this->info("\nSimulação concluída com sucesso para {$result['found']} período(s).");
            return 0;
        }

        // Execução Real
        $this->info("==========================================");
        $this->info("Resumo do Fechamento de Períodos:");
        $this->info("==========================================");
        $this->line("Períodos encontrados:      {$result['found']}");
        $this->line("Períodos fechados:         {$result['closed']}");
        $this->line("Faturas criadas:           {$result['invoices_created']}");
        $this->line("Próximos períodos abertos: {$result['next_periods_opened']}");

        if ($result['errors'] > 0) {
            $this->error("Erros encontrados:         {$result['errors']}");
            foreach ($result['details'] as $detail) {
                if (($detail['status'] ?? '') === 'error') {
                    $this->error("- Org: {$detail['organization']} (Período #{$detail['period_id']}): {$detail['error']}");
                }
            }
            return 1;
        }

        $this->info("✅ Todos os períodos foram fechados e faturados com sucesso!");
        return 0;
    }
}

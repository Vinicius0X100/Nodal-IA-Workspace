<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Reports\Jobs\GenerateAsyncReportJob;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Support\Str;

class MetaReportRouterService
{
    public function __construct(
        private MetaInsightsService $insightsService
    ) {}

    /**
     * Roteia a consulta de Insights para execução Síncrona ou Assíncrona 
     * com base nas estimativas de volume e tempo.
     */
    public function dispatchInsights(Integration $integration, array $params): array
    {
        $resourceUuid = $params['resource_uuid'] ?? null;
        $level = $params['level'] ?? 'campaign';
        $periodString = $params['period'] ?? 'last_7d';

        $period = new MetaInsightsPeriod($periodString);

        if ($this->shouldRunAsync($level, $period)) {
            $report = AsyncReport::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $integration->organization_id,
                'integration_id' => $integration->id,
                'provider' => 'meta',
                'type' => 'insights',
                'status' => 'queued',
                'params' => $params,
            ]);

            GenerateAsyncReportJob::dispatch($report);

            return [
                'async' => true,
                'data' => [
                    'report_uuid' => $report->uuid,
                    'status' => 'queued'
                ]
            ];
        }

        // Executa síncrono (com cache)
        $data = $this->insightsService->getInsights($integration, $resourceUuid, $level, $period);

        return [
            'async' => false,
            'data' => $data
        ];
    }

    private function shouldRunAsync(string $level, MetaInsightsPeriod $period): bool
    {
        // Se pedir nível de Ad, a resposta é muito densa (pode ter milhares)
        if ($level === 'ad') {
            return true;
        }

        // Se o período for maior que 14 dias
        if ($period->getDaysCount() > 14) {
            return true;
        }

        return false;
    }
}

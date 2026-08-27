<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Reports\Jobs\GenerateAsyncReportJob;
use App\Domain\Reports\Services\InsightsCostEstimator;
use App\Domain\Reports\Services\InsightsQuerySignature;
use App\Domain\Reports\Services\IdempotentReportResolver;
use App\Domain\Integrations\Models\Integration;

/**
 * Roteia consultas de Insights para execução Síncrona ou Assíncrona.
 *
 * Decisão 100% backend-driven — o frontend e o AI Agent nunca escolhem.
 * A decisão é delegada ao InsightsCostEstimator (configurável).
 *
 * Idempotência: via IdempotentReportResolver + InsightsQuerySignature.
 */
class MetaReportRouterService
{
    public function __construct(
        private MetaInsightsService $insightsService,
        private InsightsCostEstimator $costEstimator,
        private IdempotentReportResolver $reportResolver,
    ) {}

    /**
     * Roteia a consulta de Insights para execução Síncrona ou Assíncrona.
     *
     * @return array{async: bool, data: array}
     */
    public function dispatchInsights(Integration $integration, array $params): array
    {
        // Resolve período — suporta preset ou date_from/date_to
        $periodString = $this->resolvePeriodString($params);

        // Normaliza params para sempre ter 'period' internamente
        $params['period'] = $periodString;

        $period = new MetaInsightsPeriod($periodString);

        // Decisão de roteamento via policy configurável
        if ($this->costEstimator->shouldRunAsync($integration, $params, $period)) {
            return $this->dispatchAsync($integration, $params);
        }

        // Execução síncrona (com cache)
        $resourceUuid = $params['resource_uuid'] ?? null;
        $level = $params['level'] ?? 'campaign';
        $data = $this->insightsService->getInsights($integration, $resourceUuid, $level, $period);

        return [
            'async' => false,
            'data' => $data,
        ];
    }

    /**
     * Resolve a string de período a partir dos params.
     * Suporta presets ('last_7d') e datas customizadas (date_from/date_to).
     */
    private function resolvePeriodString(array $params): string
    {
        if (!empty($params['period'])) {
            return $params['period'];
        }

        if (!empty($params['date_from']) && !empty($params['date_to'])) {
            return "custom:{$params['date_from']}:{$params['date_to']}";
        }

        return 'last_7d';
    }

    /**
     * Despacha ou reutiliza um Job assíncrono idempotente.
     *
     * @return array{async: true, data: array{report_uuid: string, status: string, reused: bool}}
     */
    private function dispatchAsync(Integration $integration, array $params): array
    {
        ['report' => $report, 'reused' => $reused] = $this->reportResolver->resolve(
            $integration,
            $params,
            'meta',
            'insights',
        );

        // Só despacha novo Job se o report acabou de ser criado (não reused)
        if (!$reused) {
            GenerateAsyncReportJob::dispatch($report);
        }

        return [
            'async' => true,
            'data' => [
                'report_uuid' => $report->uuid,
                'status' => $report->status,
                'reused' => $reused,
            ],
        ];
    }
}

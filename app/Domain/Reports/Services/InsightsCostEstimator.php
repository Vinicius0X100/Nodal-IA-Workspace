<?php

namespace App\Domain\Reports\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\Meta\MetaInsightsPeriod;
use App\Domain\Resources\Models\IntegrationResource;

/**
 * Estima o custo de uma consulta de Insights para decidir sync vs async.
 *
 * A decisão é 100% backend-driven — o frontend e o AI Agent nunca escolhem.
 *
 * Todos os fatores de custo são configuráveis via config/reports.php.
 * O threshold de decisão também é configurável.
 */
class InsightsCostEstimator
{
    /**
     * Estima o custo total da consulta.
     *
     * @param  Integration        $integration  Contexto para queries locais de contagem
     * @param  array              $params       Parâmetros normalizados da consulta
     * @param  MetaInsightsPeriod $period       Período já resolvido
     * @return int                              Pontuação de custo estimado
     */
    public function estimate(
        Integration $integration,
        array $params,
        MetaInsightsPeriod $period,
    ): int {
        $factors = config('reports.cost_factors', []);
        $cost = 0;
        $level = $params['level'] ?? 'campaign';

        // ── Fator: Nível de detalhe ───────────────────────────────────────────
        if ($level === 'ad') {
            $cost += $factors['level_ad'] ?? 50;
        }

        // ── Fator: Período ────────────────────────────────────────────────────
        $days = $period->getDaysCount();
        if ($days > 30) {
            $cost += $factors['days_over_30'] ?? 60;
        } elseif ($days > 14) {
            $cost += $factors['days_over_14'] ?? 30;
        }

        // ── Fator: Quantidade de resources ────────────────────────────────────
        $resourceCount = $this->countResources($params);
        if ($resourceCount > 1) {
            $extraResources = $resourceCount - 1;
            $cost += $extraResources * ($factors['resource_extra'] ?? 20);
        }

        // ── Fator: Volume local de campanhas/adsets/ads ───────────────────────
        // Usa contagens locais (DB) — não faz chamada à API Meta
        $localCounts = $this->getLocalCounts($integration, $level);

        if ($localCounts['campaigns'] > 20) {
            $cost += $factors['campaigns_over_20'] ?? 15;
        }

        if ($localCounts['adsets'] > 50) {
            $cost += $factors['adsets_over_50'] ?? 20;
        }

        if ($localCounts['ads'] > 100) {
            $cost += $factors['ads_over_100'] ?? 25;
        }

        return $cost;
    }

    /**
     * Retorna true se a consulta deve ser executada de forma assíncrona.
     */
    public function shouldRunAsync(
        Integration $integration,
        array $params,
        MetaInsightsPeriod $period,
    ): bool {
        // Fallback seguro para testes/dev: forçar execução assíncrona
        if (config('reports.force_async') === true && !app()->isProduction()) {
            return true;
        }

        $cost = $this->estimate($integration, $params, $period);
        $threshold = (int) config('reports.async_threshold', 100);
        return $cost >= $threshold;
    }

    /**
     * Conta resources passados nos params.
     */
    private function countResources(array $params): int
    {
        if (!empty($params['resource_uuids']) && is_array($params['resource_uuids'])) {
            return count($params['resource_uuids']);
        }
        return empty($params['resource_uuid']) ? 0 : 1;
    }

    /**
     * Contagem local de Campaigns/AdSets/Ads (não chama a Meta API).
     * Usa dados já sincronizados no integration_resources.
     *
     * @return array{campaigns: int, adsets: int, ads: int}
     */
    private function getLocalCounts(Integration $integration, string $level): array
    {
        // Só conta se o nível exigir detalhe suficiente para importar
        $counts = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0];

        if (in_array($level, ['campaign', 'adset', 'ad'], true)) {
            $counts['campaigns'] = IntegrationResource::where('integration_id', $integration->id)
                ->where('resource_type', 'campaign')
                ->count();
        }

        if (in_array($level, ['adset', 'ad'], true)) {
            $counts['adsets'] = IntegrationResource::where('integration_id', $integration->id)
                ->where('resource_type', 'ad_set')
                ->count();
        }

        if ($level === 'ad') {
            $counts['ads'] = IntegrationResource::where('integration_id', $integration->id)
                ->where('resource_type', 'ad')
                ->count();
        }

        return $counts;
    }
}

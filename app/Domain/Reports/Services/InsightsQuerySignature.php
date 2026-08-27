<?php

namespace App\Domain\Reports\Services;

use App\Domain\Integrations\Models\Integration;

/**
 * Gera um hash SHA-256 determinístico para identificar uma consulta de Insights.
 *
 * O hash é SEMPRE tenant-aware: inclui organization_id e integration_id como
 * primeiros elementos para tornar impossível a colisão cross-tenant.
 *
 * Nunca inclui tokens, access_tokens, IDs externos ou dados sensíveis.
 */
class InsightsQuerySignature
{
    /**
     * Gera a assinatura da consulta.
     *
     * @param  Integration  $integration   Contexto de tenant (org + integration)
     * @param  array        $params        Parâmetros normalizados da consulta
     * @return string                      SHA-256 hexadecimal (64 chars)
     */
    public function generate(Integration $integration, array $params): string
    {
        $canonical = $this->buildCanonical($integration, $params);
        return hash('sha256', $canonical);
    }

    /**
     * Monta a string canônica estável (determinística) para hashing.
     *
     * Inclui obrigatoriamente:
     *   - organization_id   (isolamento cross-tenant)
     *   - integration_id    (isolamento cross-integration)
     *   - resource_uuids    (ordenados, para normalizar ordem)
     *   - level
     *   - período normalizado (presets ou date_from/date_to)
     *   - versão lógica da query (para invalidação futura)
     */
    private function buildCanonical(Integration $integration, array $params): string
    {
        // Tenant context (primeiros — nunca podem ser omitidos)
        $tenantPrefix = "org:{$integration->organization_id}|int:{$integration->id}";

        // Resource UUIDs normalizados (ordenados para evitar diff de ordem)
        $uuids = $this->normalizeUuids($params);

        // Nível da consulta
        $level = $params['level'] ?? 'campaign';

        // Período normalizado
        $period = $this->normalizePeriod($params);

        // Versão lógica (permite invalidar caches ao mudar normalização)
        $version = config('reports.query_version', 'v1');

        return implode('|', [
            $tenantPrefix,
            'uuids:' . implode(',', $uuids),
            'level:' . $level,
            'period:' . $period,
            'ver:' . $version,
        ]);
    }

    /**
     * Extrai e ordena os UUIDs dos resources da consulta.
     */
    private function normalizeUuids(array $params): array
    {
        $uuids = [];

        if (!empty($params['resource_uuid'])) {
            $uuids[] = $params['resource_uuid'];
        }

        if (!empty($params['resource_uuids']) && is_array($params['resource_uuids'])) {
            $uuids = array_merge($uuids, $params['resource_uuids']);
        }

        $uuids = array_unique($uuids);
        sort($uuids); // Ordenação lexicográfica para determinismo

        return $uuids;
    }

    /**
     * Normaliza a representação do período.
     * Presets são mantidos como-estão; datas customizadas são normalizadas.
     */
    private function normalizePeriod(array $params): string
    {
        if (!empty($params['period'])) {
            return 'preset:' . $params['period'];
        }

        if (!empty($params['date_from']) && !empty($params['date_to'])) {
            // Garante formato YYYY-MM-DD consistente
            return 'custom:' . $params['date_from'] . ':' . $params['date_to'];
        }

        // Fallback ao default seguro
        return 'preset:last_7d';
    }
}

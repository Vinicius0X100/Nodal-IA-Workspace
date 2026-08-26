<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Cache;

class MetaInsightsService
{
    private const ALLOWED_LEVELS = ['account', 'campaign', 'adset', 'ad'];

    private const LEAD_ACTION_TYPES = [
        'lead',
        'onsite_conversion.lead_grouped',
        'offsite_conversion.fb_pixel_lead',
    ];

    private const CONVERSION_ACTION_TYPES = [
        'purchase',
        'offsite_conversion.fb_pixel_purchase',
        'onsite_conversion.purchase',
    ];

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository $repository
    ) {}

    /**
     * Ponto de entrada primário para leitura síncrona com Cache.
     */
    public function getInsights(Integration $integration, string $resourceUuid, string $level, MetaInsightsPeriod $period): array
    {
        if (!in_array($level, self::ALLOWED_LEVELS, true)) {
            throw new \InvalidArgumentException("Nível não suportado: {$level}");
        }

        $resource = $this->repository->findByUuid($integration->organization_id, $resourceUuid);
        if (!$resource || $resource->integration_id !== $integration->id || $resource->provider->value !== 'meta') {
            throw new \InvalidArgumentException("Recurso não encontrado ou não pertence a esta organização.");
        }

        $cacheKey = "meta:insights:{$integration->id}:{$resourceUuid}:{$level}:{$period->getHash()}";
        $ttl = (int) config('integrations.meta.insights_cache_ttl', env('META_INSIGHTS_CACHE_TTL', 300));

        return Cache::remember($cacheKey, $ttl, function () use ($integration, $resource, $level, $period) {
            return $this->fetchAndNormalize($integration, $resource, $level, $period);
        });
    }

    /**
     * Utilizado pelo Job de relatórios assíncronos pesados (não usa cache, e busca em lotes).
     */
    public function generateHeavyReport(\App\Domain\Reports\Models\AsyncReport $report): array
    {
        $integration = $report->integration;
        $params = $report->params ?? [];
        $resourceUuid = $params['resource_uuid'] ?? null;
        $level = $params['level'] ?? 'campaign';
        $periodString = $params['period'] ?? 'last_7d';

        if (!$resourceUuid) {
            throw new \InvalidArgumentException("resource_uuid é obrigatório para relatórios pesados.");
        }

        $resource = $this->repository->findByUuid($integration->organization_id, $resourceUuid);
        if (!$resource) {
            throw new \InvalidArgumentException("Recurso base não encontrado.");
        }

        $period = new MetaInsightsPeriod($periodString, $resource->metadata_json['timezone_name'] ?? 'UTC');

        return $this->fetchAndNormalize($integration, $resource, $level, $period);
    }

    /**
     * Busca na Meta e mapeia os External IDs devolvidos para UUIDs do Nodal, ocultando lixo da Meta.
     */
    private function fetchAndNormalize(Integration $integration, IntegrationResource $resource, string $level, MetaInsightsPeriod $period): array
    {
        $externalId = $resource->external_id;
        $endpoint = "/{$externalId}/insights";

        $params = array_merge([
            'level' => $level,
            'fields' => 'spend,impressions,reach,clicks,cpc,cpm,ctr,actions,cost_per_action_type,action_values,purchase_roas,account_currency,campaign_id,adset_id,ad_id,account_id',
        ], $period->toGraphApiParams());

        $rawResults = $this->client->getAll($endpoint, $integration, $params);

        $normalized = [];

        foreach ($rawResults as $row) {
            $currency = $row['account_currency'] ?? 'USD';
            
            // Oculta/Substitui o ID externo da entidade base pelo UUID
            $rowExternalId = $this->extractIdByLevel($row, $level) ?? $externalId;
            $mappedUuid = $this->findLocalUuid($integration->id, $rowExternalId);

            $spend = (float) ($row['spend'] ?? 0);
            $impressions = (int) ($row['impressions'] ?? 0);
            $reach = (int) ($row['reach'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $ctr = (float) ($row['ctr'] ?? 0);
            
            // Meta envia CPC e CPM como string, convertemos
            $cpc = isset($row['cpc']) ? (float) $row['cpc'] : null;
            $cpm = isset($row['cpm']) ? (float) $row['cpm'] : null;

            $leads = $this->aggregateActionValue($row['actions'] ?? [], self::LEAD_ACTION_TYPES);
            $conversions = $this->aggregateActionValue($row['actions'] ?? [], self::CONVERSION_ACTION_TYPES);
            $purchaseValue = $this->aggregateActionValue($row['action_values'] ?? [], self::CONVERSION_ACTION_TYPES, true);
            
            // Calcula/Normaliza ROAS
            $roas = null;
            if (!empty($row['purchase_roas'])) {
                // Meta às vezes devolve um array para ROAS
                $roas = (float) ($row['purchase_roas'][0]['value'] ?? 0);
            } elseif ($spend > 0 && $purchaseValue > 0) {
                $roas = round($purchaseValue / $spend, 2);
            }

            $cpl = $leads > 0 ? round($spend / $leads, 2) : null;
            $cpa = $conversions > 0 ? round($spend / $conversions, 2) : null;

            $normalized[] = [
                'resource_uuid' => $mappedUuid,
                'level' => $level,
                'metrics' => [
                    'currency' => $currency,
                    'spend' => $spend,
                    'impressions' => $impressions,
                    'reach' => $reach,
                    'clicks' => $clicks,
                    'ctr' => $ctr,
                    'cpc' => $cpc,
                    'cpm' => $cpm,
                    'leads' => $leads,
                    'cpl' => $cpl,
                    'conversions' => $conversions,
                    'cost_per_conversion' => $cpa,
                    'purchase_value' => $purchaseValue,
                    'roas' => $roas,
                ]
            ];
        }

        return $normalized;
    }

    private function extractIdByLevel(array $row, string $level): ?string
    {
        return match($level) {
            'account' => $row['account_id'] ?? null,
            'campaign' => $row['campaign_id'] ?? null,
            'adset' => $row['adset_id'] ?? null,
            'ad' => $row['ad_id'] ?? null,
            default => null,
        };
    }

    private function findLocalUuid(int $integrationId, string $externalId): ?string
    {
        // Usa uma query leve para não carregar o model inteiro
        return IntegrationResource::where('integration_id', $integrationId)
            ->where('external_id', $externalId)
            ->value('uuid');
    }

    private function aggregateActionValue(array $actions, array $targetTypes, bool $isFloat = false): int|float
    {
        $total = 0;
        foreach ($actions as $action) {
            if (isset($action['action_type']) && in_array($action['action_type'], $targetTypes, true)) {
                $total += $isFloat ? (float) ($action['value'] ?? 0) : (int) ($action['value'] ?? 0);
            }
        }
        return $total;
    }
}

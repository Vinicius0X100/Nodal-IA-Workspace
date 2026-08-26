<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaAdSetsService
{
    private const FIELDS = 'id,campaign_id,name,status,effective_status,optimization_goal,billing_event,start_time,end_time,daily_budget,lifetime_budget';
    private const LIMIT = 100;

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository  $repository
    ) {}

    /**
     * Sincroniza Ad Sets para as contas de anúncio informadas.
     * Busca no nível da Ad Account para evitar N+1 (não busca por campaign).
     *
     * @param Integration $integration
     * @param array $adAccountExternalIds Array de IDs reais das Ad Accounts
     * @return int Quantidade de ad sets sincronizados
     */
    public function syncAdSets(Integration $integration, array $adAccountExternalIds): int
    {
        $totalSynced = 0;

        foreach ($adAccountExternalIds as $adAccountId) {
            try {
                $adSets = $this->client->getAll("/{$adAccountId}/adsets", $integration, [
                    'fields' => self::FIELDS,
                    'limit'  => self::LIMIT,
                ]);

                if (empty($adSets)) {
                    continue;
                }

                $resources = [];
                foreach ($adSets as $adSet) {
                    $resources[] = $this->normalizeAdSet($integration, $adSet);
                }

                if (count($resources) > 0) {
                    $this->repository->upsertResources($resources);
                    $totalSynced += count($resources);
                }
            } catch (MetaRateLimitException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('[MetaAdSetsService] Falha ao sincronizar Ad Sets para a conta ' . $adAccountId, [
                    'integration_id' => $integration->id,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $totalSynced;
    }

    private function normalizeAdSet(Integration $integration, array $adSet): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::AD_SET->value,
            'external_id'          => $adSet['id'],
            'parent_external_id'   => $adSet['campaign_id'] ?? null,
            'name'                 => $adSet['name'] ?? 'Ad Set sem nome',
            'description'          => null,
            'mime_type'            => null,
            'url'                  => null,
            'icon'                 => null,
            'owner_name'           => null,
            'owner_email'          => null,
            'is_folder'            => false,
            'is_shared'            => false,
            'size'                 => null,
            'created_by_provider_at' => null, // Meta não retorna created_time direto no node padrão de adset facilmente
            'updated_by_provider_at' => null,
            'last_synced_at'       => now(),
            'metadata_json'        => json_encode([
                'status'            => $adSet['status'] ?? null,
                'effective_status'  => $adSet['effective_status'] ?? null,
                'optimization_goal' => $adSet['optimization_goal'] ?? null,
                'billing_event'     => $adSet['billing_event'] ?? null,
                'start_time'        => $adSet['start_time'] ?? null,
                'end_time'          => $adSet['end_time'] ?? null,
                'daily_budget'      => $adSet['daily_budget'] ?? null,
                'lifetime_budget'   => $adSet['lifetime_budget'] ?? null,
            ]),
        ];
    }
}

<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaAdsService
{
    private const FIELDS = 'id,adset_id,name,status,effective_status,created_time,updated_time';
    private const LIMIT = 100;

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository  $repository
    ) {}

    /**
     * Sincroniza Ads para as contas de anúncio informadas.
     * Busca no nível da Ad Account para evitar N+1 (não busca por adset).
     *
     * @param Integration $integration
     * @param array $adAccountExternalIds Array de IDs reais das Ad Accounts
     * @return int Quantidade de ads sincronizados
     */
    public function syncAds(Integration $integration, array $adAccountExternalIds): int
    {
        $totalSynced = 0;

        foreach ($adAccountExternalIds as $adAccountId) {
            try {
                $ads = $this->client->getAll("/{$adAccountId}/ads", $integration, [
                    'fields' => self::FIELDS,
                    'limit'  => self::LIMIT,
                ]);

                if (empty($ads)) {
                    continue;
                }

                $resources = [];
                foreach ($ads as $ad) {
                    $resources[] = $this->normalizeAd($integration, $ad);
                }

                if (count($resources) > 0) {
                    $this->repository->upsertResources($resources);
                    $totalSynced += count($resources);
                }
            } catch (MetaRateLimitException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('[MetaAdsService] Falha ao sincronizar Ads para a conta ' . $adAccountId, [
                    'integration_id' => $integration->id,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $totalSynced;
    }

    private function normalizeAd(Integration $integration, array $ad): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::AD->value,
            'external_id'          => $ad['id'],
            'parent_external_id'   => $ad['adset_id'] ?? null,
            'name'                 => $ad['name'] ?? 'Anúncio sem nome',
            'description'          => null,
            'mime_type'            => null,
            'url'                  => null,
            'icon'                 => null,
            'owner_name'           => null,
            'owner_email'          => null,
            'is_folder'            => false,
            'is_shared'            => false,
            'size'                 => null,
            'created_by_provider_at' => isset($ad['created_time']) ? \Carbon\Carbon::parse($ad['created_time']) : null,
            'updated_by_provider_at' => isset($ad['updated_time']) ? \Carbon\Carbon::parse($ad['updated_time']) : null,
            'last_synced_at'       => now(),
            'metadata_json'        => json_encode([
                'status'           => $ad['status'] ?? null,
                'effective_status' => $ad['effective_status'] ?? null,
            ]),
        ];
    }
}

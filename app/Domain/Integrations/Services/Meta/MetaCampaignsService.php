<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaCampaignsService
{
    private const FIELDS = 'id,name,status,effective_status,objective,created_time,updated_time,daily_budget,lifetime_budget';
    private const LIMIT = 100;

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository  $repository
    ) {}

    /**
     * Sincroniza Campanhas para as contas de anúncio informadas.
     *
     * @param Integration $integration
     * @param array $adAccountExternalIds Array de IDs reais das Ad Accounts (ex: 'act_1234')
     * @return int Quantidade de campanhas sincronizadas
     */
    public function syncCampaigns(Integration $integration, array $adAccountExternalIds): int
    {
        $totalSynced = 0;

        foreach ($adAccountExternalIds as $adAccountId) {
            try {
                $campaigns = $this->client->getAll("/{$adAccountId}/campaigns", $integration, [
                    'fields' => self::FIELDS,
                    'limit'  => self::LIMIT,
                ]);

                if (empty($campaigns)) {
                    continue;
                }

                $resources = [];
                foreach ($campaigns as $campaign) {
                    $resources[] = $this->normalizeCampaign($integration, $campaign, $adAccountId);
                }

                if (count($resources) > 0) {
                    $this->repository->upsertResources($resources);
                    $totalSynced += count($resources);
                }
            } catch (MetaRateLimitException $e) {
                // Relança para o Job capturar e tentar novamente depois
                throw $e;
            } catch (\Exception $e) {
                Log::error('[MetaCampaignsService] Falha ao sincronizar Campaigns para a conta ' . $adAccountId, [
                    'integration_id' => $integration->id,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $totalSynced;
    }

    private function normalizeCampaign(Integration $integration, array $campaign, string $adAccountId): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::CAMPAIGN->value,
            'external_id'          => $campaign['id'],
            'parent_external_id'   => $adAccountId,
            'name'                 => $campaign['name'] ?? 'Campanha sem nome',
            'description'          => null,
            'mime_type'            => null,
            'url'                  => null,
            'icon'                 => null,
            'owner_name'           => null,
            'owner_email'          => null,
            'is_folder'            => false,
            'is_shared'            => false,
            'size'                 => null,
            'created_by_provider_at' => isset($campaign['created_time']) ? \Carbon\Carbon::parse($campaign['created_time']) : null,
            'updated_by_provider_at' => isset($campaign['updated_time']) ? \Carbon\Carbon::parse($campaign['updated_time']) : null,
            'last_synced_at'       => now(),
            'metadata_json'        => json_encode([
                'status'           => $campaign['status'] ?? null,
                'effective_status' => $campaign['effective_status'] ?? null,
                'objective'        => $campaign['objective'] ?? null,
                'daily_budget'     => $campaign['daily_budget'] ?? null,
                'lifetime_budget'  => $campaign['lifetime_budget'] ?? null,
            ]),
        ];
    }
}

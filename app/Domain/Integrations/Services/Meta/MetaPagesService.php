<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Busca as Facebook Pages e Instagram Accounts associados à integração Meta conectada
 * e persiste os resultados em `integration_resources` via ResourceRepository.
 *
 * Utiliza o endpoint /me/accounts com field expansion para buscar o instagram_business_account
 * na mesma requisição, otimizando o limite de chamadas da API Graph.
 */
class MetaPagesService
{
    /**
     * Campos solicitados à Graph API.
     * Busca os dados da Page e, aninhado, os dados da conta profissional do Instagram.
     */
    private const FIELDS = 'id,name,username,category,picture,instagram_business_account{id,username,name,profile_picture_url}';

    /** Limite de contas por página. */
    private const PAGE_LIMIT = 100;

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository  $repository
    ) {}

    /**
     * Sincroniza Facebook Pages e Instagram Accounts.
     *
     * @return array Array associativo com contagem ['pages' => int, 'instagram' => int]
     *
     * @throws MetaRateLimitException
     * @throws \App\Domain\Identities\Exceptions\IntegrationInactiveException
     * @throws \RuntimeException
     */
    public function syncPagesAndInstagram(Integration $integration): array
    {
        try {
            $accounts = $this->client->getAll('/me/accounts', $integration, [
                'fields' => self::FIELDS,
                'limit'  => self::PAGE_LIMIT,
            ]);

            if (empty($accounts)) {
                IntegrationLog::create([
                    'integration_id' => $integration->id,
                    'event'          => 'meta_pages_sync_completed',
                    'status'         => 'success',
                    'message'        => 'Nenhuma Facebook Page encontrada para esta conta Meta.',
                ]);
                return ['pages' => 0, 'instagram' => 0];
            }

            $pageResources = [];
            $instagramResources = [];

            foreach ($accounts as $account) {
                // 1. Normaliza a Facebook Page
                $pageResources[] = $this->normalizeFacebookPage($integration, $account);

                // 2. Normaliza a conta do Instagram vinculada (se existir)
                if (!empty($account['instagram_business_account'])) {
                    $instagramResources[] = $this->normalizeInstagramAccount(
                        $integration,
                        $account['instagram_business_account'],
                        $account['id'] // parent_external_id = Facebook Page ID
                    );
                }
            }

            // Faz upsert das Pages
            if (count($pageResources) > 0) {
                $this->repository->upsertResources($pageResources);
            }

            // Faz upsert das contas do Instagram
            if (count($instagramResources) > 0) {
                $this->repository->upsertResources($instagramResources);
            }

            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'meta_pages_sync_completed',
                'status'         => 'success',
                'message'        => sprintf(
                    "%d Facebook Page(s) e %d Instagram Account(s) sincronizados.",
                    count($pageResources),
                    count($instagramResources)
                ),
            ]);

            return [
                'pages'     => count($pageResources),
                'instagram' => count($instagramResources),
            ];

        } catch (MetaRateLimitException $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'meta_pages_sync_failed',
                'status'         => 'error',
                'message'        => 'Rate limit atingido ao sincronizar Facebook Pages.',
            ]);
            throw $e;
        } catch (\Exception $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'meta_pages_sync_failed',
                'status'         => 'error',
                'message'        => 'Falha ao sincronizar Facebook Pages e Instagram: ' . $e->getMessage(),
            ]);

            Log::error('[MetaPagesService] Falha ao sincronizar Pages/Instagram', [
                'integration_id' => $integration->id,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Normaliza uma Facebook Page da Graph API para o formato de `integration_resources`.
     */
    private function normalizeFacebookPage(Integration $integration, array $page): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::FACEBOOK_PAGE->value,
            'external_id'          => $page['id'], // Real ID da Meta Page, NUNCA exposto
            'parent_external_id'   => null,
            'name'                 => $page['name'] ?? 'Página sem nome',
            'description'          => null,
            'mime_type'            => null,
            'url'                  => null,
            'icon'                 => null,
            'owner_name'           => null,
            'owner_email'          => null,
            'is_folder'            => false,
            'is_shared'            => false,
            'size'                 => null,
            'created_by_provider_at' => null,
            'updated_by_provider_at' => null,
            'last_synced_at'       => now(),
            'metadata_json'        => json_encode([
                'username' => $page['username'] ?? null,
                'category' => $page['category'] ?? null,
                'picture'  => $page['picture']['data']['url'] ?? null,
            ]),
        ];
    }

    /**
     * Normaliza uma Instagram Account vinculada para o formato de `integration_resources`.
     */
    private function normalizeInstagramAccount(Integration $integration, array $ig, string $pageId): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::INSTAGRAM_ACCOUNT->value,
            'external_id'          => $ig['id'], // Real ID da conta IG Business, NUNCA exposto
            'parent_external_id'   => $pageId, // Associa à Facebook Page pai
            'name'                 => $ig['name'] ?? $ig['username'] ?? 'Instagram sem nome',
            'description'          => null,
            'mime_type'            => null,
            'url'                  => null,
            'icon'                 => null,
            'owner_name'           => null,
            'owner_email'          => null,
            'is_folder'            => false,
            'is_shared'            => false,
            'size'                 => null,
            'created_by_provider_at' => null,
            'updated_by_provider_at' => null,
            'last_synced_at'       => now(),
            'metadata_json'        => json_encode([
                'username'        => $ig['username'] ?? null,
                'profile_picture' => $ig['profile_picture_url'] ?? null,
            ]),
        ];
    }
}

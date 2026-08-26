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
 * Busca as Ad Accounts disponíveis para a integração Meta conectada
 * e persiste os resultados em `integration_resources` via ResourceRepository.
 *
 * Não depende de session() — recebe Integration explicitamente.
 * Seguro para uso em Web, Queue, AI Gateway e Scheduler.
 *
 * O identificador público de uma Ad Account é sempre `IntegrationResource.uuid`.
 * O `external_id` (ID real da Meta) NUNCA é exposto em DTOs ou respostas públicas.
 */
class MetaAdAccountsService
{
    /**
     * Campos solicitados à Graph API.
     * Ajuste aqui para adicionar/remover campos do Meta Ad Account.
     */
    private const FIELDS = 'id,name,currency,timezone_name,account_status';

    /** Limite de contas por página (máximo da Meta para este endpoint). */
    private const PAGE_LIMIT = 500;

    public function __construct(
        private MetaMarketingClient $client,
        private ResourceRepository  $repository
    ) {}

    /**
     * Sincroniza as Ad Accounts da Meta com `integration_resources`.
     *
     * Fluxo:
     *   1. Chama GET /me/adaccounts com paginação automática
     *   2. Normaliza cada Ad Account para o formato `integration_resources`
     *   3. Faz upsert via ResourceRepository (chave: integration_id + external_id)
     *   4. Registra IntegrationLog com sucesso ou falha
     *
     * @return int Quantidade de Ad Accounts sincronizadas
     *
     * @throws MetaRateLimitException
     * @throws \App\Domain\Identities\Exceptions\IntegrationInactiveException
     * @throws \RuntimeException
     */
    public function syncAdAccounts(Integration $integration): int
    {
        try {
            $accounts = $this->client->getAll('/me/adaccounts', $integration, [
                'fields' => self::FIELDS,
                'limit'  => self::PAGE_LIMIT,
            ]);

            if (empty($accounts)) {
                IntegrationLog::create([
                    'integration_id' => $integration->id,
                    'event'          => 'sync_meta_ad_accounts',
                    'status'         => 'success',
                    'message'        => 'Nenhuma Ad Account encontrada para esta conta Meta.',
                ]);
                return 0;
            }

            $resources = array_map(
                fn(array $account) => $this->normalizeAdAccount($integration, $account),
                $accounts
            );

            // Upsert via ResourceRepository — chave composta: [integration_id, external_id]
            // Segunda sync não duplica; atualiza name, metadata e last_synced_at.
            $this->repository->upsertResources($resources);

            $count = count($resources);

            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'sync_meta_ad_accounts',
                'status'         => 'success',
                'message'        => "{$count} conta(s) de anúncio sincronizada(s) com sucesso.",
            ]);

            return $count;

        } catch (MetaRateLimitException $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'sync_meta_ad_accounts',
                'status'         => 'error',
                'message'        => 'Rate limit atingido ao sincronizar Ad Accounts.',
            ]);
            throw $e;
        } catch (\Exception $e) {
            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event'          => 'sync_meta_ad_accounts',
                'status'         => 'error',
                'message'        => 'Falha ao sincronizar Ad Accounts: ' . $e->getMessage(),
            ]);

            Log::error('[MetaAdAccountsService] Falha ao sincronizar Ad Accounts', [
                'integration_id' => $integration->id,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Normaliza um Ad Account da Graph API para o formato de `integration_resources`.
     *
     * IMPORTANTE:
     * - `external_id` = ID real da Meta (ex: 'act_123456789') — armazenado internamente, NUNCA exposto
     * - `uuid` = identificador público gerado pelo Model no boot() — único exposto via API
     * - `parent_external_id` = null (Ad Account não tem pai na hierarquia de recursos)
     */
    private function normalizeAdAccount(Integration $integration, array $account): array
    {
        return [
            'uuid'                 => (string) Str::uuid(),
            'integration_id'       => $integration->id,
            'provider'             => Provider::META->value,
            'resource_type'        => ResourceType::AD_ACCOUNT->value,
            'external_id'          => $account['id'],                    // 'act_XXXXXXXXX'
            'parent_external_id'   => null,
            'name'                 => $account['name'] ?? 'Conta sem nome',
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
                'currency'       => $account['currency']       ?? null,
                'timezone_name'  => $account['timezone_name']  ?? null,
                'account_status' => $account['account_status'] ?? null,
            ]),
        ];
    }
}

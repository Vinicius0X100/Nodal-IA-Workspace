<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Única camada técnica responsável por todas as chamadas HTTP à Meta Graph API.
 *
 * Responsabilidades centralizadas aqui:
 * - Base URL e versão configurável (services.meta.graph_version)
 * - Authorization via Bearer token
 * - Timeout padrão
 * - Paginação por cursor (paging.cursors.after) — reutilizável por futuras entidades
 * - Tratamento sanitizado de erros (sem expor tokens)
 * - Handling de 401 → delega markAsNeedsReconnect ao MetaTokenService
 * - Handling inicial de rate limit (códigos Meta 17, 32, 613)
 * - Registro de IntegrationLog sem tokens ou payloads sensíveis
 *
 * Controllers e Domain Services NÃO devem usar Http:: diretamente para Meta.
 */
class MetaMarketingClient
{
    /** Códigos de erro da Graph API que indicam rate limit. */
    private const RATE_LIMIT_CODES = [17, 32, 613];

    /** Timeout padrão em segundos para chamadas à Graph API. */
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private MetaTokenService $tokenService
    ) {}

    /**
     * Executa uma requisição GET à Graph API e retorna os dados da primeira página.
     *
     * @param  string      $endpoint  Endpoint relativo, ex: '/me/adaccounts'
     * @param  Integration $integration
     * @param  array       $params    Parâmetros de query adicionais
     * @return array       Dados brutos da resposta JSON
     *
     * @throws \App\Domain\Identities\Exceptions\IntegrationInactiveException
     * @throws MetaRateLimitException
     * @throws \RuntimeException
     */
    public function get(string $endpoint, Integration $integration, array $params = []): array
    {
        $token   = $this->tokenService->getValidToken($integration);
        $baseUrl = $this->buildUrl($endpoint);

        $response = Http::withToken($token)
            ->timeout(self::TIMEOUT_SECONDS)
            ->get($baseUrl, $params);

        return $this->handleResponse($response, $integration, $endpoint);
    }

    /**
     * Busca TODOS os itens de um endpoint paginado usando cursor da Meta.
     *
     * Consome automaticamente todas as páginas via `paging.cursors.after`,
     * acumulando os resultados em um único array.
     *
     * Reutilizável por Campaigns, Ad Sets, Ads e qualquer futura entidade Meta.
     *
     * @param  string      $endpoint    Endpoint relativo, ex: '/me/adaccounts'
     * @param  Integration $integration
     * @param  array       $params      Parâmetros base (fields, limit, etc.)
     * @return array       Array acumulado de todos os items de todas as páginas
     *
     * @throws \App\Domain\Identities\Exceptions\IntegrationInactiveException
     * @throws MetaRateLimitException
     * @throws \RuntimeException
     */
    public function getAll(string $endpoint, Integration $integration, array $params = []): array
    {
        $token    = $this->tokenService->getValidToken($integration);
        $baseUrl  = $this->buildUrl($endpoint);
        $allItems = [];
        $cursor   = null;

        do {
            $queryParams = array_merge($params, array_filter(['after' => $cursor]));

            $response = Http::withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($baseUrl, $queryParams);

            $page   = $this->handleResponse($response, $integration, $endpoint);
            $items  = $page['data'] ?? [];
            $allItems = array_merge($allItems, $items);

            // Avança cursor para próxima página (Meta usa cursors.after)
            $cursor = $page['paging']['cursors']['after'] ?? null;

            // Também suporta o campo 'next' diretamente (algumas APIs da Meta retornam assim)
            $hasNext = !empty($page['paging']['next']);

        } while ($cursor && $hasNext);

        return $allItems;
    }

    /**
     * Monta a URL completa com a versão configurável da Graph API.
     *
     * Versão lida de config('services.meta.graph_version').
     * Nunca hardcoded.
     */
    private function buildUrl(string $endpoint): string
    {
        $version = config('services.meta.graph_version', 'v19.0');
        $base    = rtrim("https://graph.facebook.com/{$version}", '/');
        $path    = '/' . ltrim($endpoint, '/');

        return $base . $path;
    }

    /**
     * Processa a Response da Graph API aplicando todos os handlers centralizados.
     *
     * @throws MetaRateLimitException  Se a Meta retornar rate limit
     * @throws \RuntimeException       Para outros erros da API
     */
    private function handleResponse(Response $response, Integration $integration, string $endpoint): array
    {
        // 401 — token inválido ou revogado
        if ($response->status() === 401) {
            $this->tokenService->markAsNeedsReconnect($integration, 'Graph API retornou 401 para ' . $endpoint);
            throw new \RuntimeException('Token Meta inválido. A integração precisa ser reconectada.');
        }

        $body = $response->json() ?? [];

        // Erros explícitos da Graph API (ex: rate limit, permissão, token inválido)
        if (isset($body['error'])) {
            $errorCode    = (int) ($body['error']['code'] ?? 0);
            $errorMessage = $body['error']['message'] ?? 'Erro desconhecido da Meta Graph API';
            $errorType    = $body['error']['type'] ?? '';

            // Rate limit
            if (in_array($errorCode, self::RATE_LIMIT_CODES, true)) {
                $this->logSecureError($integration, 'rate_limit', "Rate limit atingido na Graph API. Código: {$errorCode}");
                throw new MetaRateLimitException("Meta Graph API rate limit atingido (código {$errorCode}). Tente novamente em instantes.");
            }

            // Token inválido via código de erro (190 = token expirado/revogado)
            if ($errorCode === 190 || $errorType === 'OAuthException') {
                $this->tokenService->markAsNeedsReconnect($integration, "Graph API retornou erro de token: código {$errorCode}");
                throw new \RuntimeException('Token Meta inválido ou expirado. Reconecte a integração.');
            }

            // Outros erros da API — loga sem expor detalhes sensíveis
            $this->logSecureError(
                $integration,
                'graph_api_error',
                "Erro da Graph API no endpoint {$endpoint}. Código: {$errorCode}, Tipo: {$errorType}"
            );

            throw new \RuntimeException("Erro da Meta Graph API (código {$errorCode}): {$errorMessage}");
        }

        // Resposta com status não-2xx sem corpo de erro estruturado
        if (!$response->successful()) {
            $this->logSecureError(
                $integration,
                'graph_api_http_error',
                "Graph API retornou HTTP {$response->status()} para {$endpoint}"
            );
            throw new \RuntimeException("Erro HTTP {$response->status()} ao chamar a Meta Graph API.");
        }

        return $body;
    }

    /**
     * Registra erro sem expor tokens, access_tokens ou payloads completos.
     */
    private function logSecureError(Integration $integration, string $event, string $message): void
    {
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'event'          => $event,
            'status'         => 'error',
            'message'        => $message,
        ]);

        Log::error("[MetaMarketingClient] {$event}", [
            'integration_id' => $integration->id,
            'message'        => $message,
        ]);
    }
}

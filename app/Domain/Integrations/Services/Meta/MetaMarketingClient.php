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
 * - Timeouts por camada (connect, request)
 * - Paginação por cursor (paging.cursors.after) — reutilizável por futuras entidades
 * - getAllChunked(): paginação sem acúmulo em memória (callback por página)
 * - Retry com exponential backoff + jitter para rate limit e 5xx transitórios
 * - Tratamento sanitizado de erros (sem expor tokens)
 * - Handling de 401 → delega markAsNeedsReconnect ao MetaTokenService
 * - Handling de rate limit (códigos Meta 17, 32, 613) — NUNCA marca needs_reconnect
 * - Registro de IntegrationLog sem tokens ou payloads sensíveis
 *
 * Controllers e Domain Services NÃO devem usar Http:: diretamente para Meta.
 */
class MetaMarketingClient
{
    /** Códigos de erro da Graph API que indicam rate limit. */
    private const RATE_LIMIT_CODES = [17, 32, 613];

    /** Códigos HTTP que indicam erros transitórios (retry seguro). */
    private const TRANSIENT_HTTP_CODES = [500, 502, 503, 504];

    /** Timeout de conexão TCP (segundos). */
    private const TIMEOUT_CONNECT_SECONDS = 10;

    /** Timeout de resposta por request (segundos). */
    private const TIMEOUT_REQUEST_SECONDS = 45;

    /** Backoff máximo entre retries (segundos). */
    private const MAX_BACKOFF_SECONDS = 120;

    /** Base do backoff exponencial (segundos). */
    private const BACKOFF_BASE_SECONDS = 2;

    public function __construct(
        private MetaTokenService $tokenService
    ) {}

    /**
     * Executa uma requisição GET à Graph API e retorna os dados da primeira página.
     */
    public function get(string $endpoint, Integration $integration, array $params = []): array
    {
        $token = $this->tokenService->getValidToken($integration);
        $url = $this->buildUrl($endpoint);

        $response = $this->requestWithRetry('GET', $url, $params, $token, $integration, $endpoint);
        return $this->handleResponse($response, $integration, $endpoint);
    }

    /**
     * Busca TODOS os itens de um endpoint paginado, acumulando em memória.
     *
     * @deprecated Para volumes grandes, prefira getAllChunked() que não acumula em memória.
     */
    public function getAll(string $endpoint, Integration $integration, array $params = []): array
    {
        $allItems = [];

        $this->getAllChunked($endpoint, $integration, $params, function (array $pageItems) use (&$allItems) {
            $allItems = array_merge($allItems, $pageItems);
        });

        return $allItems;
    }

    /**
     * Busca TODOS os itens de um endpoint paginado via streaming de páginas.
     *
     * Chama $callback($pageItems, $pageNumber) para cada página, sem acumular tudo em memória.
     * O caller decide o que fazer com cada página (persistir, processar, etc.).
     *
     * @param  callable(array, int): void  $callback     Recebe ($items, $pageNumber)
     * @param  int                         $maxPages     Limite de segurança (default: config)
     * @return array{pages: int, records: int}           Métricas de paginação
     *
     * @throws MetaRateLimitException
     * @throws \RuntimeException
     */
    public function getAllChunked(
        string $endpoint,
        Integration $integration,
        array $params,
        callable $callback,
        int $maxPages = 0,
    ): array {
        if ($maxPages <= 0) {
            $maxPages = (int) config('reports.max_pages_per_job', 500);
        }

        $token = $this->tokenService->getValidToken($integration);
        $url = $this->buildUrl($endpoint);
        $cursor = null;
        $pageNumber = 0;
        $totalRecords = 0;

        do {
            $pageNumber++;

            if ($pageNumber > $maxPages) {
                Log::warning('[MetaMarketingClient] Limite de páginas atingido.', [
                    'integration_id' => $integration->id,
                    'endpoint' => $endpoint,
                    'max_pages' => $maxPages,
                ]);
                break;
            }

            $queryParams = array_merge($params, array_filter(['after' => $cursor]));

            $response = $this->requestWithRetry('GET', $url, $queryParams, $token, $integration, $endpoint);
            $page = $this->handleResponse($response, $integration, $endpoint);

            $items = $page['data'] ?? [];
            $totalRecords += count($items);

            // Entrega a página ao caller — sem acumular em memória aqui
            $callback($items, $pageNumber);

            // Avança cursor
            $cursor = $page['paging']['cursors']['after'] ?? null;
            $hasNext = !empty($page['paging']['next']);

        } while ($cursor && $hasNext);

        return [
            'pages' => $pageNumber,
            'records' => $totalRecords,
        ];
    }

    /**
     * Executa uma requisição HTTP com retry inteligente.
     *
     * Diferencia:
     *  - 401 / OAuthException       → needs_reconnect imediato, sem retry
     *  - Rate limit (17, 32, 613)   → retry com backoff + jitter, NUNCA needs_reconnect
     *  - 5xx transitório            → retry com backoff + jitter
     *  - 4xx definitivo (não 429)   → lança exceção sem retry
     *
     * @throws MetaRateLimitException   Rate limit após todos os retries esgotados
     * @throws \RuntimeException        Erro definitivo ou transitório após retries
     */
    private function requestWithRetry(
        string $method,
        string $url,
        array $params,
        string $token,
        Integration $integration,
        string $endpoint,
        int $maxAttempts = 3,
    ): Response {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = Http::withToken($token)
                    ->connectTimeout(self::TIMEOUT_CONNECT_SECONDS)
                    ->timeout(self::TIMEOUT_REQUEST_SECONDS)
                    ->get($url, $params);

                // Sucesso ou erro definitivo — não retry
                if ($response->successful()) {
                    return $response;
                }

                // 401 → needs_reconnect imediato, sem retry
                if ($response->status() === 401) {
                    $this->tokenService->markAsNeedsReconnect($integration, "Graph API retornou 401 para {$endpoint}");
                    throw new \RuntimeException('Token Meta inválido. A integração precisa ser reconectada.');
                }

                // Erros estruturados da Graph API
                $body = $response->json() ?? [];
                if (isset($body['error'])) {
                    $errorCode = (int) ($body['error']['code'] ?? 0);
                    $errorType = $body['error']['type'] ?? '';

                    // 401 via código de erro ou OAuthException
                    if ($errorCode === 190 || $errorType === 'OAuthException') {
                        $this->tokenService->markAsNeedsReconnect($integration, "Token expirado: código {$errorCode}");
                        throw new \RuntimeException('Token Meta inválido ou expirado. Reconecte a integração.');
                    }

                    // Rate limit — retry com backoff, NUNCA needs_reconnect
                    if (in_array($errorCode, self::RATE_LIMIT_CODES, true)) {
                        $this->logSecureError($integration, 'rate_limit', "Rate limit código {$errorCode} (tentativa {$attempt}/{$maxAttempts})");

                        if ($attempt >= $maxAttempts) {
                            throw new MetaRateLimitException("Meta rate limit após {$maxAttempts} tentativas (código {$errorCode}).");
                        }

                        // Respeita Retry-After se confiável
                        $retryAfter = $this->parseRetryAfter($response);
                        $this->applyBackoff($attempt, $retryAfter);
                        continue;
                    }

                    // 4xx definitivo (não é rate limit)
                    $this->logSecureError($integration, 'graph_api_error', "Erro definitivo código {$errorCode} endpoint {$endpoint}");
                    throw new \RuntimeException("Erro da Meta Graph API (código {$errorCode}): " . ($body['error']['message'] ?? 'Erro desconhecido'));
                }

                // 5xx transitório → retry
                if (in_array($response->status(), self::TRANSIENT_HTTP_CODES, true)) {
                    $this->logSecureError($integration, 'graph_api_5xx', "HTTP {$response->status()} transitório em {$endpoint} (tentativa {$attempt}/{$maxAttempts})");

                    if ($attempt >= $maxAttempts) {
                        throw new \RuntimeException("Erro HTTP {$response->status()} da Meta Graph API após {$maxAttempts} tentativas.");
                    }

                    $this->applyBackoff($attempt, null);
                    continue;
                }

                // Outros erros não-2xx sem retry
                $this->logSecureError($integration, 'graph_api_http_error', "HTTP {$response->status()} definitivo em {$endpoint}");
                throw new \RuntimeException("Erro HTTP {$response->status()} ao chamar a Meta Graph API.");

            } catch (MetaRateLimitException | \RuntimeException $e) {
                $lastException = $e;
                // RuntimeException de needs_reconnect ou erro definitivo não tem retry
                if (!($e instanceof MetaRateLimitException)) {
                    throw $e;
                }
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Timeout de rede — retry
                $this->logSecureError($integration, 'network_timeout', "Timeout de rede em {$endpoint} (tentativa {$attempt}/{$maxAttempts})");
                $lastException = new \RuntimeException("Timeout de conexão com a Meta Graph API.");

                if ($attempt >= $maxAttempts) {
                    throw $lastException;
                }
                $this->applyBackoff($attempt, null);
            }
        }

        throw $lastException ?? new \RuntimeException('Erro desconhecido na Meta Graph API.');
    }

    /**
     * Aplica backoff exponencial com jitter aleatório.
     *
     * Fórmula: min(base * 2^attempt + jitter, MAX_BACKOFF)
     */
    private function applyBackoff(int $attempt, ?int $retryAfterSeconds): void
    {
        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $seconds = min($retryAfterSeconds, self::MAX_BACKOFF_SECONDS);
        } else {
            $exponential = self::BACKOFF_BASE_SECONDS * (2 ** ($attempt - 1));
            $jitter = random_int(0, (int) ($exponential * 0.3)); // ±30% jitter
            $seconds = min($exponential + $jitter, self::MAX_BACKOFF_SECONDS);
        }

        // Em ambiente de teste (QUEUE_CONNECTION=sync), não dorme
        if (!app()->environment('testing')) {
            sleep($seconds);
        }
    }

    /**
     * Extrai o valor de Retry-After do header ou do body da Meta.
     */
    private function parseRetryAfter(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');
        if ($retryAfter && is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }
        return null;
    }

    /**
     * Processa a Response da Graph API aplicando todos os handlers centralizados.
     */
    private function handleResponse(Response $response, Integration $integration, string $endpoint): array
    {
        // Respostas 2xx com body de erro estruturado
        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            $errorCode = (int) ($body['error']['code'] ?? 0);
            $errorMessage = $body['error']['message'] ?? 'Erro desconhecido da Meta Graph API';
            $errorType = $body['error']['type'] ?? '';

            if (in_array($errorCode, self::RATE_LIMIT_CODES, true)) {
                $this->logSecureError($integration, 'rate_limit', "Rate limit código {$errorCode}");
                throw new MetaRateLimitException("Meta rate limit (código {$errorCode}).");
            }

            if ($errorCode === 190 || $errorType === 'OAuthException') {
                $this->tokenService->markAsNeedsReconnect($integration, "Token inválido: código {$errorCode}");
                throw new \RuntimeException('Token Meta inválido ou expirado. Reconecte a integração.');
            }

            $this->logSecureError($integration, 'graph_api_error', "Erro código {$errorCode} em {$endpoint}");
            throw new \RuntimeException("Erro da Meta Graph API (código {$errorCode}): {$errorMessage}");
        }

        if (!$response->successful()) {
            $this->logSecureError($integration, 'graph_api_http_error', "HTTP {$response->status()} em {$endpoint}");
            throw new \RuntimeException("Erro HTTP {$response->status()} ao chamar a Meta Graph API.");
        }

        return $body;
    }

    /**
     * Monta a URL completa com a versão configurável da Graph API.
     */
    private function buildUrl(string $endpoint): string
    {
        $version = config('services.meta.graph_version', 'v19.0');
        $base = rtrim("https://graph.facebook.com/{$version}", '/');
        $path = '/' . ltrim($endpoint, '/');
        return $base . $path;
    }

    /**
     * Registra erro sem expor tokens, access_tokens ou payloads completos.
     */
    private function logSecureError(Integration $integration, string $event, string $message): void
    {
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'event' => $event,
            'status' => 'error',
            'message' => $message,
        ]);

        Log::error("[MetaMarketingClient] {$event}", [
            'integration_id' => $integration->id,
            'message' => $message,
        ]);
    }
}

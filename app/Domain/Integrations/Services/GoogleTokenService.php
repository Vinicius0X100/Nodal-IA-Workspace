<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Firebase\JWT\JWT;
use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identities\Exceptions\ProviderDelegationRequiredException;
use App\Domain\Identities\Exceptions\IntegrationInactiveException;

class GoogleTokenService
{
    /** Status que indicam que a integração não está operacional. */
    private const INACTIVE_STATUSES = ['not_connected', 'disconnected', 'needs_reconnect', 'revoked', 'disabled', 'error'];

    /**
     * Lança IntegrationInactiveException se a integração estiver inativa ou desabilitada.
     *
     * @throws IntegrationInactiveException
     */
    private function assertIntegrationActive(Integration $integration): void
    {
        if (!$integration->is_enabled || in_array($integration->status, self::INACTIVE_STATUSES, true)) {
            throw new IntegrationInactiveException(
                "A integração Google Workspace está desconectada ou desabilitada. Reconecte a conta para continuar."
            );
        }
    }

    /**
     * Invalida todos os tokens delegados em cache para uma integração.
     * Deve ser chamado no fluxo de desconexão.
     */
    public function invalidateDelegatedTokenCache(Integration $integration): void
    {
        // Remove a chave genérica sem scopes para retrocompatibilidade
        Cache::forget("google_delegated_token:{$integration->id}");

        // Invalida via tag se o driver de cache suportar tags
        try {
            Cache::tags(["integration:{$integration->id}"])->flush();
        } catch (\BadMethodCallException) {
            // Driver sem suporte a tags (ex: file, database) — log e segue
            Log::info("[GoogleTokenService] Driver de cache sem suporte a tags. Tokens delegados podem persistir até expiração natural.", [
                'integration_id' => $integration->id,
            ]);
        }
    }

    /**
     * Retorna um access_token válido.
     * Renova o token automaticamente caso esteja vencido ou próximo do vencimento (buffer).
     *
     * @param Integration $integration
     * @param bool $forceRefresh
     * @return string
     * @throws IntegrationInactiveException
     * @throws Exception
     */
    public function getValidAccessToken(Integration $integration, bool $forceRefresh = false): string
    {
        $this->assertIntegrationActive($integration);

        if (!$integration->access_token && !$integration->refresh_token) {
            throw new Exception("A integração não possui tokens de acesso nem de renovação.");
        }

        // Buffer de segurança: 5 minutos
        $isExpiringSoon = $integration->token_expires_at 
            && $integration->token_expires_at->copy()->subMinutes(5)->isPast();

        if (!$forceRefresh && $integration->access_token && !$isExpiringSoon) {
            return $integration->access_token;
        }

        if (!$integration->refresh_token) {
            throw new Exception("O token de acesso expirou e a integração não possui refresh_token para renovação.");
        }

        return $this->refreshOAuthToken($integration);
    }

    /**
     * Retorna um access_token utilizando Google Domain-Wide Delegation (Service Account).
     * O token será gerado em nome do usuário especificado pela $externalIdentity (Impersonation).
     *
     * @param Integration $integration
     * @param ExternalIdentity $externalIdentity
     * @param array $scopes
     * @return string
     * @throws IntegrationInactiveException
     * @throws ProviderDelegationRequiredException
     * @throws Exception
     */
    public function getDelegatedAccessToken(Integration $integration, ExternalIdentity $externalIdentity, array $scopes = []): string
    {
        // Bloqueia DWD se a integração estiver inativa ou desabilitada
        $this->assertIntegrationActive($integration);

        $config = $integration->config;
        $serviceAccountJson = config('services.google_workspace.service_account_json') ?: ($config->delegation_credentials_json ?? null);

        if (empty($serviceAccountJson)) {
            $this->logSecureError($integration, 'delegation_token', 'Credenciais de delegação de domínio não configuradas globalmente ou no tenant.');
            throw new ProviderDelegationRequiredException("A configuração de Domain-Wide Delegation (Service Account JSON) não foi realizada no Nodal para esta integração.");
        }

        // Se a chave vier do ENV como string (pode ser o JSON puro, um base64 ou um caminho de arquivo)
        $serviceAccount = $serviceAccountJson;
        if (is_string($serviceAccountJson)) {
            // Verifica se é um arquivo (relativo ou absoluto)
            if (is_file(base_path($serviceAccountJson))) {
                $serviceAccountJson = file_get_contents(base_path($serviceAccountJson));
            } elseif (is_file($serviceAccountJson)) {
                $serviceAccountJson = file_get_contents($serviceAccountJson);
            }
            
            $serviceAccount = json_decode($serviceAccountJson, true);
        }
        
        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            $this->logSecureError($integration, 'delegation_token', 'O JSON da Service Account é inválido ou incompleto.');
            throw new ProviderDelegationRequiredException("O JSON da Service Account configurado é inválido.");
        }

        // Ordena e gera hash dos scopes para garantir que scopes diferentes tenham tokens diferentes em cache
        $scopesStr = empty($scopes) ? 'default' : implode(',', $scopes);
        $scopesHash = md5($scopesStr);

        // Cache o token delegado por e-mail, integração e scopes para não ficar pedindo todo o tempo (dura 1 hr)
        $cacheKey = "google_delegated_token:{$integration->id}:{$externalIdentity->primary_email}:{$scopesHash}";
        if ($cachedToken = Cache::get($cacheKey)) {
            return $cachedToken;
        }

        $now = time();
        // O JWT expira em 1 hora, de acordo com o padrão do Google
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'sub' => $externalIdentity->primary_email,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => implode(' ', $scopes),
        ];

        try {
            $jwt = JWT::encode($payload, $serviceAccount['private_key'], 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Salvar em cache com 55 minutos para renovar antes de expirar
                Cache::put($cacheKey, $data['access_token'], now()->addMinutes(55));

                return $data['access_token'];
            }

            $errorData = $response->json();
            $errorCode = $errorData['error'] ?? 'unknown_error';
            $this->logSecureError($integration, 'delegation_token', 'Falha ao solicitar token delegado: ' . json_encode($errorData) . ' | Scopes solicitados: ' . implode(',', $scopes));
            
            if ($errorCode === 'invalid_grant' || $response->status() === 400 || $response->status() === 401) {
                $integration->update(['status' => 'needs_reconnect']);
            }

            throw new Exception("Falha ao obter token delegado: " . ($errorData['error_description'] ?? $errorCode ?? 'Erro desconhecido. Verifique o Google Admin Console.'));
        } catch (\Exception $e) {
            $this->logSecureError($integration, 'delegation_token', 'Erro interno ao gerar JWT: ' . $e->getMessage());
            throw new ProviderDelegationRequiredException("Erro ao gerar credencial delegada: " . $e->getMessage());
        }
    }

    /**
     * Executa uma renovação via OAuth 2.0.
     *
     * @param Integration $integration
     * @return string
     * @throws Exception
     */
    protected function refreshOAuthToken(Integration $integration): string
    {
        $config = $integration->config;
        $clientId = config('services.google_workspace.client_id') ?: ($config->client_id ?? null);
        $clientSecret = config('services.google_workspace.client_secret') ?: ($config->client_secret ?? null);

        if (!$clientId || !$clientSecret) {
            $this->logSecureError($integration, 'token_refresh', 'Faltam credenciais globais (client_id, client_secret).');
            throw new Exception("Faltam credenciais para renovar o token.");
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            $updates = [
                'access_token' => $data['access_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in']),
                'status' => 'connected'
            ];

            // A API do Google pode opcionalmente retornar um novo refresh_token
            if (!empty($data['refresh_token'])) {
                $updates['refresh_token'] = $data['refresh_token'];
            }

            $integration->update($updates);

            IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'token_refresh',
                'status' => 'success',
                'message' => 'Token renovado com sucesso.',
            ]);

            return $data['access_token'];
        }

        $errorData = $response->json();
        $errorCode = $errorData['error'] ?? 'unknown_error';

        if ($errorCode === 'invalid_grant' || $response->status() === 400 || $response->status() === 401) {
            $integration->update(['status' => 'needs_reconnect']);
            $this->logSecureError($integration, 'token_refresh', "O refresh token foi revogado ou é inválido. A integração exige reconexão. Código: {$errorCode}");
            throw new Exception("O refresh token é inválido ou foi revogado pelo usuário no painel do Google. Reconecte a conta.");
        }

        $this->logSecureError($integration, 'token_refresh', 'Falha temporária ou desconhecida ao renovar token. HTTP ' . $response->status());
        throw new Exception("Falha ao renovar o token do Google Workspace.");
    }

    /**
     * Executa uma requisição. Caso retorne 401, força um refresh token preventivo e tenta de novo.
     * Retorna o próprio objeto Response do Laravel Http.
     * 
     * @param Integration $integration
     * @param callable $requestClosure O closure deve aceitar a string $accessToken e retornar \Illuminate\Http\Client\Response
     * @param array $scopes Escopos a delegar caso $externalIdentity seja preenchido
     * @return \Illuminate\Http\Client\Response
     * @throws IntegrationInactiveException
     * @throws Exception
     */
    public function executeWithRetry(Integration $integration, callable $requestClosure, ?ExternalIdentity $externalIdentity = null, array $scopes = []): \Illuminate\Http\Client\Response
    {
        // Ponto central de guarda: rejeita qualquer operação se a integração não estiver ativa
        $this->assertIntegrationActive($integration);

        $accessToken = $externalIdentity 
            ? $this->getDelegatedAccessToken($integration, $externalIdentity, $scopes)
            : $this->getValidAccessToken($integration);

        $response = $requestClosure($accessToken);

        if ($response->status() === 401) {
            if ($externalIdentity) {
                // Para DWD o cache é invalidado e gera um novo JWT
                Cache::forget("google_delegated_token:{$integration->id}:{$externalIdentity->primary_email}");
                $newAccessToken = $this->getDelegatedAccessToken($integration, $externalIdentity, $scopes);
            } else {
                Log::info("[GoogleTokenService] Recebeu 401. Tentando forçar refresh token.", ['integration_id' => $integration->id]);
                $newAccessToken = $this->getValidAccessToken($integration, true);
            }
            
            // Tenta mais uma vez
            $retryResponse = $requestClosure($newAccessToken);

            if ($retryResponse->status() === 401) {
                // Se o segundo der 401, o novo token recém gerado não tem permissão para isso, ou revogaram geral
                $this->logSecureError($integration, 'execute_retry_failed', 'O endpoint retornou 401 mesmo após um refresh token bem sucedido.');
            }

            return $retryResponse;
        }

        return $response;
    }

    /**
     * Loga erros sem expor segredos.
     */
    protected function logSecureError(Integration $integration, string $event, string $message): void
    {
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'event' => $event,
            'status' => 'error',
            'message' => $message,
        ]);
        Log::error("[GoogleTokenService] {$event}", [
            'integration_id' => $integration->id, 
            'message' => $message
        ]);
    }
}

<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Firebase\JWT\JWT;
use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identities\Exceptions\ProviderDelegationRequiredException;

class GoogleTokenService
{
    /**
     * Retorna um access_token válido.
     * Renova o token automaticamente caso esteja vencido ou próximo do vencimento (buffer).
     *
     * @param Integration $integration
     * @param bool $forceRefresh
     * @return string
     * @throws Exception
     */
    public function getValidAccessToken(Integration $integration, bool $forceRefresh = false): string
    {
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
     * @throws ProviderDelegationRequiredException
     * @throws Exception
     */
    public function getDelegatedAccessToken(Integration $integration, ExternalIdentity $externalIdentity, array $scopes = []): string
    {
        $config = $integration->config;

        if (!$config || empty($config->delegation_credentials_json)) {
            $this->logSecureError($integration, 'delegation_token', 'Credenciais de delegação de domínio não configuradas.');
            throw new ProviderDelegationRequiredException("A configuração de Domain-Wide Delegation (Service Account JSON) não foi realizada no Nodal para esta integração.");
        }

        $serviceAccount = $config->delegation_credentials_json; // Já vem decodificado pelo cast encrypted:array
        if (empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            $this->logSecureError($integration, 'delegation_token', 'O JSON da Service Account é inválido ou incompleto.');
            throw new ProviderDelegationRequiredException("O JSON da Service Account configurado é inválido.");
        }

        // Cache o token delegado por e-mail e por integração para não ficar pedindo todo o tempo (dura 1 hr)
        $cacheKey = "google_delegated_token:{$integration->id}:{$externalIdentity->primary_email}";
        if ($cachedToken = \Illuminate\Support\Facades\Cache::get($cacheKey)) {
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
                \Illuminate\Support\Facades\Cache::put($cacheKey, $data['access_token'], now()->addMinutes(55));

                return $data['access_token'];
            }

            $errorData = $response->json();
            $errorCode = $errorData['error'] ?? 'unknown_error';
            $this->logSecureError($integration, 'delegation_token', 'Falha ao solicitar token delegado: ' . json_encode($errorData));
            
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

        if (!$config || !$config->client_id || !$config->client_secret) {
            $this->logSecureError($integration, 'token_refresh', 'Faltam credenciais (client_id, client_secret) na configuração da integração.');
            throw new Exception("Faltam credenciais para renovar o token.");
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
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
     * @throws Exception
     */
    public function executeWithRetry(Integration $integration, callable $requestClosure, ?ExternalIdentity $externalIdentity = null, array $scopes = []): \Illuminate\Http\Client\Response
    {
        $accessToken = $externalIdentity 
            ? $this->getDelegatedAccessToken($integration, $externalIdentity, $scopes)
            : $this->getValidAccessToken($integration);

        $response = $requestClosure($accessToken);

        if ($response->status() === 401) {
            if ($externalIdentity) {
                // Para DWD o cache é invalidado e gera um novo JWT
                \Illuminate\Support\Facades\Cache::forget("google_delegated_token:{$integration->id}:{$externalIdentity->primary_email}");
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

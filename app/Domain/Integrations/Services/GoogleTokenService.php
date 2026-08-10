<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

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
     * @return \Illuminate\Http\Client\Response
     * @throws Exception
     */
    public function executeWithRetry(Integration $integration, callable $requestClosure): \Illuminate\Http\Client\Response
    {
        $accessToken = $this->getValidAccessToken($integration);

        $response = $requestClosure($accessToken);

        if ($response->status() === 401) {
            // O token possivelmente foi revogado ou expirou subitamente sem que soubéssemos.
            // Tenta forçar o refresh.
            Log::info("[GoogleTokenService] Recebeu 401. Tentando forçar refresh token.", ['integration_id' => $integration->id]);
            
            $newAccessToken = $this->getValidAccessToken($integration, true);
            
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

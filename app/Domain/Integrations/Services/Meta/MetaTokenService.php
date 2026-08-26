<?php

namespace App\Domain\Integrations\Services\Meta;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Identities\Exceptions\IntegrationInactiveException;
use Illuminate\Support\Facades\Log;

/**
 * Responsável por obter e validar o token OAuth da integração Meta.
 *
 * Fonte única da verdade: tabela `integration_tokens`.
 * Os tokens são decriptados automaticamente pelo cast 'encrypted' do Model.
 *
 * Meta usa tokens de longa duração (~60 dias). Refresh automático não é
 * implementado nesta fase — quando inválido, status = needs_reconnect.
 */
class MetaTokenService
{
    /** Statuses que indicam integração inoperante. */
    private const INACTIVE_STATUSES = ['not_connected', 'disconnected', 'needs_reconnect', 'revoked', 'disabled', 'error'];

    /**
     * Retorna o access_token válido da integração Meta da organização.
     *
     * @throws IntegrationInactiveException  Se a integração estiver inativa.
     * @throws \RuntimeException              Se o token não existir.
     */
    public function getValidToken(Integration $integration): string
    {
        $this->assertIntegrationActive($integration);

        $token = IntegrationToken::where('organization_id', $integration->organization_id)
            ->where('provider', 'meta')
            ->first();

        if (!$token || empty($token->access_token)) {
            $this->logSecureError($integration, 'token_missing', 'Nenhum token Meta encontrado para esta organização.');
            throw new \RuntimeException('Token Meta não encontrado. Reconecte a integração.');
        }

        // Verifica expiração se preenchida (tokens de longa duração da Meta têm expires_at)
        if ($token->expires_at && $token->expires_at->isPast()) {
            $integration->update(['status' => 'needs_reconnect']);
            $this->logSecureError($integration, 'token_expired', 'O token Meta expirou. Reconexão necessária.');
            throw new IntegrationInactiveException('O token Meta expirou. Por favor, reconecte a integração.');
        }

        return $token->access_token;
    }

    /**
     * Marca a integração como needs_reconnect — chamado pelo MetaMarketingClient
     * quando a Graph API retorna 401.
     */
    public function markAsNeedsReconnect(Integration $integration, string $reason = ''): void
    {
        $integration->update(['status' => 'needs_reconnect']);
        $this->logSecureError($integration, 'token_invalid', 'Token Meta inválido ou revogado. ' . $reason);
    }

    /**
     * Verifica se a integração está em status operacional.
     *
     * @throws IntegrationInactiveException
     */
    private function assertIntegrationActive(Integration $integration): void
    {
        if (in_array($integration->status, self::INACTIVE_STATUSES, true)) {
            throw new IntegrationInactiveException(
                "A integração Meta está desconectada ou desabilitada. Status: {$integration->status}"
            );
        }
    }

    /**
     * Registra erro sem expor tokens ou dados sensíveis.
     */
    private function logSecureError(Integration $integration, string $event, string $message): void
    {
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'event'          => $event,
            'status'         => 'error',
            'message'        => $message,
        ]);

        Log::error("[MetaTokenService] {$event}", [
            'integration_id' => $integration->id,
            'message'        => $message,
        ]);
    }
}

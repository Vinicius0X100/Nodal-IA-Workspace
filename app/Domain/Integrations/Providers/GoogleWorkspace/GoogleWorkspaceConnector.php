<?php

namespace App\Domain\Integrations\Providers\GoogleWorkspace;

use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Services\GoogleOAuthService;
use App\Domain\Organizations\Models\Organization;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceConnector implements ConnectorInterface
{
    public function __construct(
        protected GoogleOAuthService $oauthService
    ) {}

    public function getProviderName(): string
    {
        return 'google_workspace';
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function connect(Organization $organization, array $config)
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['redirect_uri'])) {
            throw new Exception("Configurações do Google Workspace incompletas (Client ID, Secret ou Redirect URI ausentes).");
        }

        $provider = $this->oauthService->buildProvider(
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri']
        );

        // Escopos exigidos na Especificação
        $scopes = [
            'https://www.googleapis.com/auth/admin.directory.user.readonly',
            'https://www.googleapis.com/auth/admin.directory.orgunit.readonly',
            'https://www.googleapis.com/auth/drive.readonly', // Pesquisar/Listar arquivos, PDFs
            'https://www.googleapis.com/auth/documents.readonly', // Ler Docs
            'https://www.googleapis.com/auth/spreadsheets.readonly', // Ler Sheets
            'https://www.googleapis.com/auth/calendar.readonly', // Consultar Agenda
        ];

        return $provider
            ->scopes($scopes)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function handleCallback(Organization $organization, array $config, array $requestData): void
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['redirect_uri'])) {
            throw new Exception("Configurações do Google Workspace incompletas.");
        }

        $provider = $this->oauthService->buildProvider(
            $config['client_id'],
            $config['client_secret'],
            $config['redirect_uri']
        );

        // Obtém o user do Socialite (Isso faz a troca do código de autorização pelos tokens)
        // Usamos stateless porque não dependemos de sessão nativa caso a requisição venha de uma API
        $googleUser = $provider->stateless()->user();

        // Salvar ou atualizar o token no banco
        \App\Domain\Integrations\Models\Integration::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $this->getProviderName(),
            ],
            [
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken ?? null,
                'token_expires_at' => now()->addSeconds($googleUser->expiresIn),
                'scope' => $googleUser->approvedScopes ?? [],
            ]
        );
        
        // Log ou atualização na tabela `integrations` para 'connected' pode ser feito fora daqui
        // ou emitindo um evento.
    }

    public function disconnect(Organization $organization): void
    {
        // Remove os tokens da base
        \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->update([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'scope' => null,
            ]);

        // Poderia acionar a API do Google para revogar o token lá também:
        // Http::post('https://oauth2.googleapis.com/revoke', ['token' => $token->access_token]);
    }

    public function refreshToken(Organization $organization): bool
    {
        // Aqui irá a lógica de refresh usando Guzzle ou HTTP facade
        // Passando client_id, client_secret e refresh_token para a url token do google
        return false;
    }

    public function getStatus(Organization $organization): string
    {
        $token = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->whereNotNull('access_token')
            ->first();

        if (!$token) {
            return 'not_connected';
        }

        if ($token->token_expires_at && $token->token_expires_at->isPast()) {
            return 'expired'; // Pode disparar a rotina de refreshToken
        }

        return 'connected';
    }

    public function health(Organization $organization): bool
    {
        // Tenta fazer um hit simples numa API base do Google para ver se o token é válido
        return $this->getStatus($organization) === 'connected';
    }
}

<?php

namespace App\Domain\Integrations\Providers\Meta;

use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class MetaConnector implements ConnectorInterface
{
    public function getProviderName(): string
    {
        return 'meta';
    }

    public function connect(Organization $organization, array $config)
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['redirect_uri'])) {
            throw new Exception("Configurações da Meta incompletas (Client ID, Secret ou Redirect URI ausentes).");
        }

        // Criar ou atualizar status para configuring na tabela principal
        Integration::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $this->getProviderName(),
            ],
            [
                'display_name' => 'Meta',
                'status' => 'configuring',
            ]
        );

        $scopes = [
            'public_profile',
            'email',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'pages_read_user_content',
            'pages_manage_ads',
            'pages_manage_posts',
            'business_management',
            'ads_management',
            'ads_read'
        ];

        return Socialite::driver('facebook')
            ->scopes($scopes)
            ->redirect();
    }

    public function handleCallback(Organization $organization, array $config, array $requestData): void
    {
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['redirect_uri'])) {
            throw new Exception("Configurações da Meta incompletas.");
        }

        if (isset($requestData['error'])) {
            throw new Exception($requestData['error_description'] ?? $requestData['error']);
        }

        // O driver stateful faz a verificação nativa do state na sessão, prevenindo CSRF
        $facebookUser = Socialite::driver('facebook')->user();

        Integration::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $this->getProviderName(),
            ],
            [
                'display_name' => 'Meta',
                'status' => 'connected',
            ]
        );

        IntegrationToken::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $this->getProviderName(),
            ],
            [
                'access_token' => $facebookUser->token,
                'refresh_token' => $facebookUser->refreshToken ?? null,
                'expires_at' => $facebookUser->expiresIn ? now()->addSeconds($facebookUser->expiresIn) : null,
                'scope' => json_encode($facebookUser->approvedScopes ?? []),
                'token_type' => 'Bearer',
            ]
        );

        IntegrationLog::create([
            'integration_id' => Integration::where('organization_id', $organization->id)->where('provider', $this->getProviderName())->first()->id,
            'user_id' => auth()->id(),
            'event' => 'oauth_callback_success',
            'status' => 'success',
            'message' => 'Integração Meta conectada com sucesso.',
        ]);
    }

    public function disconnect(Organization $organization): void
    {
        $integration = Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->first();

        if ($integration) {
            $integration->update(['status' => 'not_connected']);
            
            IntegrationToken::where('organization_id', $organization->id)
                ->where('provider', $this->getProviderName())
                ->delete();

            IntegrationLog::create([
                'integration_id' => $integration->id,
                'user_id' => auth()->id(),
                'event' => 'disconnected',
                'status' => 'success',
                'message' => 'Integração Meta desconectada e tokens revogados.',
            ]);
        }
    }

    public function refreshToken(Organization $organization): bool
    {
        // Na Fase 1, não automatizaremos o processo de refreshToken se não houver um longo
        return false;
    }

    public function getStatus(Organization $organization): string
    {
        $integration = Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->first();

        return $integration ? $integration->status : 'not_connected';
    }

    public function health(Organization $organization): bool
    {
        return $this->getStatus($organization) === 'connected';
    }
}

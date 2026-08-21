<?php

namespace App\Domain\Integrations\Providers\GoogleWorkspace;

use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Services\GoogleOAuthService;
use App\Domain\Integrations\Services\GoogleTokenService;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationWebhook;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceConnector implements ConnectorInterface
{
    public function __construct(
        protected GoogleOAuthService $oauthService,
        protected GoogleTokenService $tokenService
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
            'https://www.googleapis.com/auth/admin.directory.domain.readonly', // Para listar domínios
            'https://www.googleapis.com/auth/admin.directory.group.readonly', // Para listar grupos
            'https://www.googleapis.com/auth/drive.readonly', // Pesquisar/Listar arquivos, PDFs
            'https://www.googleapis.com/auth/documents.readonly', // Ler Docs
            'https://www.googleapis.com/auth/spreadsheets.readonly', // Ler Sheets
            'https://www.googleapis.com/auth/calendar.readonly', // Consultar Agenda
            'https://www.googleapis.com/auth/gmail.readonly', // Pesquisar e Ler Emails
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
        
        // Register Webhook channel for real-time drive updates
        try {
            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $this->getProviderName())
                ->first();
            $this->registerWebhookChannel($integration);
        } catch (\Exception $e) {
            Log::error("Failed to register webhook channel during callback: " . $e->getMessage());
        }
        
        // Log ou atualização na tabela `integrations` para 'connected' pode ser feito fora daqui
        // ou emitindo um evento.
    }

    public function disconnect(Organization $organization): void
    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->first();

        if ($integration && $integration->access_token) {
            // Stop active webhooks
            $activeWebhooks = IntegrationWebhook::where('integration_id', $integration->id)
                ->where('state', 'active')
                ->get();
            foreach ($activeWebhooks as $webhook) {
                $this->stopWebhookChannel($integration, $webhook);
            }

            // Revogar na API do Google
            $response = \Illuminate\Support\Facades\Http::post('https://oauth2.googleapis.com/revoke', [
                'token' => $integration->access_token,
            ]);

            \App\Domain\Integrations\Models\IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'disconnect',
                'status' => $response->successful() ? 'success' : 'warning',
                'message' => $response->successful() ? 'Token revogado com sucesso no Google.' : 'Falha ao revogar token no Google: ' . $response->body(),
            ]);

            // Remove os tokens da base
            $integration->update([
                'access_token'      => null,
                'refresh_token'     => null,
                'token_expires_at'  => null,
                'scope'             => null,
                'status'            => 'not_connected',
            ]);

            // Invalida tokens delegados (DWD) que possam estar em cache
            $this->googleTokenService->invalidateDelegatedTokenCache($integration);
        }
    }

    public function refreshToken(Organization $organization): bool
    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->first();

        if (!$integration || !$integration->refresh_token) {
            return false;
        }

        try {
            // Usa o serviço centralizado forçando o refresh
            $this->tokenService->getValidAccessToken($integration, true);
            return true;
        } catch (\Exception $e) {
            return false;
        }
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
        return $this->getStatus($organization) === 'connected';
    }

    /**
     * Busca informações da organização no Google Workspace.
     * 
     * @param \App\Domain\Integrations\Models\Integration $integration
     * @return array
     */
    public function getOrganizationData(\App\Domain\Integrations\Models\Integration $integration): array
    {
        // Usa o serviço centralizado que cuida de buffer e refresh automático se necessário
        $token = $this->tokenService->getValidAccessToken($integration);
        
        try {
            // 1. Obter domínios
            $domainsResponse = \Illuminate\Support\Facades\Http::withToken($token)
                ->get('https://admin.googleapis.com/admin/directory/v1/customer/my_customer/domains');
                
            if (!$domainsResponse->successful()) {
                throw new Exception("Falha ao buscar domínios da organização: " . $domainsResponse->body());
            }
            
            $domains = $domainsResponse->json('domains', []);
            $primaryDomain = null;
            
            foreach ($domains as $domain) {
                if (isset($domain['isPrimary']) && $domain['isPrimary']) {
                    $primaryDomain = $domain['domainName'];
                    break;
                }
            }
            if (!$primaryDomain && count($domains) > 0) {
                $primaryDomain = $domains[0]['domainName'];
            }

            // 2. Obter usuários (Com paginação)
            $totalUsers = 0;
            $pageToken = null;
            $customerId = null;
            $allUsers = [];

            do {
                $usersResponse = \Illuminate\Support\Facades\Http::withToken($token)
                    ->get('https://admin.googleapis.com/admin/directory/v1/users', [
                        'customer' => 'my_customer',
                        'maxResults' => 500,
                        'pageToken' => $pageToken,
                    ]);
                    
                if (!$usersResponse->successful()) {
                    throw new Exception("Falha ao buscar usuários: " . $usersResponse->body());
                }
                
                $data = $usersResponse->json();
                $users = $data['users'] ?? [];
                
                $totalUsers += count($users);
                $allUsers = array_merge($allUsers, $users);
                
                if (!$customerId && !empty($users)) {
                    $customerId = $users[0]['customerId'] ?? null;
                }

                $pageToken = $data['nextPageToken'] ?? null;
            } while ($pageToken);
            
            // Descobrir o administrador autenticado
            $adminInfoResponse = \Illuminate\Support\Facades\Http::withToken($token)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');
                
            $adminEmail = null;
            $adminName = null;
            
            if ($adminInfoResponse->successful()) {
                $adminData = $adminInfoResponse->json();
                $adminEmail = $adminData['email'] ?? null;
                $adminName = $adminData['name'] ?? null;
            }

            // 3. Obter grupos (Com Paginação)
            $totalGroups = 0;
            $groupPageToken = null;
            $allGroups = [];
            do {
                $groupsResponse = \Illuminate\Support\Facades\Http::withToken($token)
                    ->get('https://admin.googleapis.com/admin/directory/v1/groups', [
                        'customer' => 'my_customer',
                        'maxResults' => 500,
                        'pageToken' => $groupPageToken,
                    ]);
                    
                if ($groupsResponse->successful()) {
                    $groupData = $groupsResponse->json();
                    $groups = $groupData['groups'] ?? [];
                    $totalGroups += count($groups);
                    $allGroups = array_merge($allGroups, $groups);
                    $groupPageToken = $groupData['nextPageToken'] ?? null;
                } else {
                    $groupPageToken = null;
                }
            } while ($groupPageToken);
            
            $organizationName = $primaryDomain ? ucfirst(explode('.', $primaryDomain)[0]) : 'Google Workspace Organization';

            \App\Domain\Integrations\Models\IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'sync_organization',
                'status' => 'success',
                'message' => "Diretório sincronizado: {$totalUsers} usuários e {$totalGroups} grupos.",
            ]);

            return [
                'customer_id' => $customerId,
                'organization_name' => $organizationName,
                'primary_domain' => $primaryDomain,
                'customer_type' => 'google_workspace',
                'admin_email' => $adminEmail,
                'admin_name' => $adminName,
                'total_users' => $totalUsers,
                'total_groups' => $totalGroups,
                'original_response' => [
                    'users' => ['users' => $allUsers],
                    'groups' => ['groups' => $allGroups],
                ],
            ];
        } catch (\Exception $e) {
            \App\Domain\Integrations\Models\IntegrationLog::create([
                'integration_id' => $integration->id,
                'event' => 'sync_organization',
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function registerWebhookChannel(Integration $integration): void
    {
        $token = $this->tokenService->getValidAccessToken($integration);
        $channelId = (string) Str::uuid();
        
        // Using url() so it resolves to the application's base URL (e.g., HTTPS domain)
        $webhookAddress = url('/api/webhooks/google-workspace');
        
        $webhook = IntegrationWebhook::create([
            'integration_id' => $integration->id,
            'channel_id' => $channelId,
            'resource_uri' => $webhookAddress,
            'state' => 'pending',
            'expires_at' => now()->addDays(7), // Google Drive webhooks typically expire in a week or so, requiring renewal
        ]);

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post('https://www.googleapis.com/drive/v3/changes/watch?supportsAllDrives=true&includeItemsFromAllDrives=true', [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => $webhookAddress,
            ]);

        if (!$response->successful()) {
            $webhook->update(['state' => 'error']);
            throw new Exception("Falha ao registrar Webhook no Google Drive: " . $response->body());
        }

        // The initial 'sync' request might already hit the controller before this line executes,
        // but we assume it'll be updated via the controller.
        // Google returns `resourceId` in the response as well.
        $data = $response->json();
        $webhook->update([
            'resource_id' => $data['resourceId'] ?? null,
            'state' => 'active',
            'expires_at' => isset($data['expiration']) ? \Carbon\Carbon::createFromTimestampMs($data['expiration']) : now()->addDays(7),
        ]);
        
        \App\Domain\Integrations\Models\IntegrationLog::create([
            'integration_id' => $integration->id,
            'event' => 'webhook_registered',
            'status' => 'success',
            'message' => "Webhook de Push Notifications registrado com sucesso.",
        ]);
    }

    public function stopWebhookChannel(Integration $integration, IntegrationWebhook $webhook): void
    {
        if (!$webhook->resource_id) {
            $webhook->update(['state' => 'stopped']);
            return;
        }

        try {
            $token = $this->tokenService->getValidAccessToken($integration);
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->post('https://www.googleapis.com/drive/v3/channels/stop', [
                    'id' => $webhook->channel_id,
                    'resourceId' => $webhook->resource_id,
                ]);

            if ($response->successful() || $response->status() === 404) {
                $webhook->update(['state' => 'stopped']);
            } else {
                Log::warning("Failed to stop Google Drive webhook channel {$webhook->channel_id}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Error stopping webhook channel {$webhook->channel_id}: " . $e->getMessage());
        }
    }
}

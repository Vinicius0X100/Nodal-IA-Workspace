<?php

namespace App\Domain\Integrations\Providers\GoogleWorkspace;

use App\Domain\Integrations\Contracts\ConnectorInterface;
use App\Domain\Integrations\Services\GoogleOAuthService;
use App\Domain\Integrations\Services\GoogleTokenService;
use App\Domain\Organizations\Models\Organization;
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
        
        // Log ou atualização na tabela `integrations` para 'connected' pode ser feito fora daqui
        // ou emitindo um evento.
    }

    public function disconnect(Organization $organization): void
    {
        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
            ->where('provider', $this->getProviderName())
            ->first();

        if ($integration && $integration->access_token) {
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
}

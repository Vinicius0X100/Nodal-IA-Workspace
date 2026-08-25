<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IntegrationsController extends Controller
{
    /**
     * Marketplace de Integrações
     */
    public function index(Request $request)
    {
        $organizationId = session('active_organization_id');
        $dbIntegrations = \App\Domain\Integrations\Models\Integration::where('organization_id', $organizationId)->get();

        return Inertia::render('Integrations/Index', [
            'dbIntegrations' => $dbIntegrations
        ]);
    }

    /**
     * Detalhes / Configuração do Google Workspace
     */
    public function googleWorkspace(Request $request)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        
        $integration = \App\Domain\Integrations\Models\Integration::with([
            'organizationData', 
            'groups.users',
            'logs' => function($query) {
                $query->latest()->limit(50);
            }
        ])->firstOrCreate(
            ['organization_id' => $organization->id, 'provider' => 'google_workspace'],
            ['display_name' => 'Google Workspace', 'status' => 'not_connected']
        );
        
        $config = $integration->config;
        $allUsers = $organization->users()->get();

        return Inertia::render('Integrations/Providers/GoogleWorkspace', [
            'app_url' => config('app.url'),
            'integration' => $integration,
            'config' => $config,
            'all_users' => $allUsers,
            'google_service_account_client_id' => config('services.google_workspace.service_account_client_id'),
        ]);
    }

    public function googleWorkspaceUsers(Request $request)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        
        $integration = \App\Domain\Integrations\Models\Integration::with([
            'organizationData'
        ])->where('organization_id', $organization->id)
          ->where('provider', 'google_workspace')
          ->firstOrFail();

        return Inertia::render('Integrations/Providers/GoogleWorkspaceUsers', [
            'integration' => $integration,
        ]);
    }

    public function googleWorkspaceGroups(Request $request)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        
        $integration = \App\Domain\Integrations\Models\Integration::with([
            'organizationData',
            'groups.users',
        ])->where('organization_id', $organization->id)
          ->where('provider', 'google_workspace')
          ->firstOrFail();

        return Inertia::render('Integrations/Providers/GoogleWorkspaceGroups', [
            'integration' => $integration,
        ]);
    }

    public function meta(Request $request)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        
        $integration = \App\Domain\Integrations\Models\Integration::with([
            'organizationData', 
            'logs' => function($query) {
                $query->latest()->limit(50);
            }
        ])->firstOrCreate(
            ['organization_id' => $organization->id, 'provider' => 'meta'],
            ['display_name' => 'Meta', 'status' => 'not_connected']
        );
        
        $config = $integration->config;

        return Inertia::render('Integrations/Providers/Meta', [
            'app_url' => config('app.url'),
            'integration' => $integration,
            'config' => $config,
        ]);
    }

    public function saveConfig(Request $request, string $provider)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        abort_unless($organization->isOwner($request->user()), 403, 'Acesso restrito aos administradores.');

        $config = \App\Domain\Integrations\Models\IntegrationConfig::whereHas('integration', function ($query) use ($organization, $provider) {
            $query->where('organization_id', $organization->id)->where('provider', $provider);
        })->first();

        $rules = [
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
            'tenant' => 'nullable|string',
        ];

        if ($provider === 'google_workspace') {
            $rules['delegation_credentials_json'] = 'nullable|json';
        }

        $request->validate($rules, [
            'delegation_credentials_json.json' => 'O formato do JSON fornecido é inválido.'
        ]);
        
        $integration = \App\Domain\Integrations\Models\Integration::firstOrCreate(
            ['organization_id' => $organization->id, 'provider' => $provider],
            ['display_name' => ucwords(str_replace('_', ' ', $provider)), 'status' => 'configuring']
        );

        $updates = [
            'client_id' => $request->client_id,
            'client_secret' => $request->client_secret,
            'tenant' => $request->tenant,
            'redirect_uri' => config('app.url') . "/oauth/{$provider}/callback",
        ];

        if ($provider === 'google_workspace' && $request->filled('delegation_credentials_json')) {
            $json = json_decode($request->delegation_credentials_json, true);
            
            if (!is_array($json) || empty($json['client_email']) || empty($json['private_key']) || !str_starts_with(trim($json['private_key']), '-----BEGIN PRIVATE KEY-----')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'delegation_credentials_json' => 'INVALID_SERVICE_ACCOUNT_JSON'
                ]);
            }
            
            $updates['delegation_credentials_json'] = $json;
        }

        \App\Domain\Integrations\Models\IntegrationConfig::updateOrCreate(
            ['integration_id' => $integration->id],
            $updates
        );

        // Se estava não conectado, vai pra configuring para liberar o botão
        if ($integration->status === 'not_connected') {
            $integration->update(['status' => 'configuring']);
        }

        return back()->with('success', 'Configurações salvas com sucesso.');
    }

    private function getProviderConfig(string $provider, \App\Domain\Organizations\Models\Organization $organization): ?array
    {
        $globalConfig = config("services.{$provider}");
        
        if (!empty($globalConfig['client_id']) && !empty($globalConfig['client_secret'])) {
            return [
                'client_id' => $globalConfig['client_id'],
                'client_secret' => $globalConfig['client_secret'],
                'redirect_uri' => $globalConfig['redirect'] ?? config('app.url') . "/oauth/{$provider}/callback",
                'service_account_json' => $globalConfig['service_account_json'] ?? null,
            ];
        }

        $configModel = \App\Domain\Integrations\Models\IntegrationConfig::whereHas('integration', function ($query) use ($organization, $provider) {
            $query->where('organization_id', $organization->id)->where('provider', $provider);
        })->first();

        if ($configModel && $configModel->client_id && $configModel->client_secret) {
            return [
                'client_id' => $configModel->client_id,
                'client_secret' => $configModel->client_secret,
                'redirect_uri' => $configModel->redirect_uri ?: config('app.url') . "/oauth/{$provider}/callback",
            ];
        }

        return null;
    }

    /**
     * Inicia o fluxo de conexão OAuth
     */
    public function connect(Request $request, string $provider, \App\Domain\Integrations\Services\IntegrationManager $manager)
    {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        abort_unless($organization->isOwner($request->user()), 403, 'Acesso restrito aos administradores.');

        $config = $this->getProviderConfig($provider, $organization);

        if (!$config || empty($config['client_id']) || empty($config['client_secret'])) {
            return back()->with('error', 'Credenciais OAuth do app não estão configuradas.');
        }

        try {
            $connector = $manager->getConnector($provider);
            return $connector->connect($organization, $config);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Callback do OAuth (retorno após o consentimento do usuário)
     */
    public function callback(
        Request $request,
        string $provider,
        \App\Domain\Integrations\Services\IntegrationManager $manager,
        \App\Domain\AI\Services\AIToolRegistryService $aiToolRegistry
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        abort_unless($organization->isOwner($request->user()), 403, 'Acesso restrito aos administradores.');

        $config = $this->getProviderConfig($provider, $organization);

        if (!$config) {
            return redirect()->route('integrations.index')->with('error', 'Configurações de integração ausentes.');
        }

        try {
            $connector = $manager->getConnector($provider);
            $connector->handleCallback($organization, $config, $request->all());

            // Atualiza status na tabela principal
            \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->update(['status' => 'connected']);

            // Sincroniza as ferramentas de IA
            $aiToolRegistry->syncIntegrationTools($organization);

            // Inicia o processo automático de Sincronização de Recursos no servidor
            // O usuário não precisará apertar nenhum botão, os arquivos começam a baixar agora.
            $integrationModel = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->first();
            
            if ($integrationModel) {
                \App\Domain\Resources\Jobs\SyncProviderResourcesJob::dispatch($integrationModel, auth()->id());
            }

            if ($integrationModel) {
                $tools = \App\Domain\AI\Models\AITool::where('integration_id', $integrationModel->id)->get();
                
                // Envia notificação ao Owner
                $owner = $organization->users()->wherePivot('is_owner', true)->first();
                if ($owner && $tools->isNotEmpty()) {
                    $owner->notify(new \App\Notifications\IntegrationConnectedNotification($organization, $integrationModel, $tools));
                }
            }

            $routeMap = [
                'google_workspace' => 'integrations.google-workspace',
                'meta' => 'integrations.meta',
            ];
            $redirectRoute = $routeMap[$provider] ?? 'integrations.index';

            return redirect()->route($redirectRoute)->with('success', 'Integração conectada com sucesso!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro OAuth Callback {$provider}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $redirectRoute = $routeMap[$provider] ?? 'integrations.index';
            return redirect()->route($redirectRoute)->with('error', 'Falha ao conectar: ' . $e->getMessage());
        }
    }

    /**
     * Desconecta e revoga os tokens
     */
    public function disconnect(
        Request $request,
        string $provider,
        \App\Domain\Integrations\Services\IntegrationManager $manager,
        \App\Domain\AI\Services\AIToolRegistryService $aiToolRegistry
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        abort_unless($organization->isOwner($request->user()), 403, 'Acesso restrito aos administradores.');

        try {
            $connector = $manager->getConnector($provider);
            $connector->disconnect($organization);

            // Atualiza status na tabela principal
            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->first();

            if ($integration) {
                $integration->update(['status' => 'not_connected']);
                
                // Remove as ferramentas da IA para essa integração
                $aiToolRegistry->unregisterIntegrationTools($integration);
            }

            return back()->with('success', 'Integração desconectada com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao desconectar: ' . $e->getMessage());
        }
    }
}

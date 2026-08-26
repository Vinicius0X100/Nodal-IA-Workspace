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

        // Ad Accounts sincronizadas — expostas pelo uuid (nunca pelo external_id)
        $adAccounts = [];
        $facebookPages = [];
        $instagramAccounts = [];
        $campaigns = [];
        if ($integration->status === 'connected') {
            $resources = \App\Domain\Resources\Models\IntegrationResource::where('integration_id', $integration->id)
                ->whereIn('resource_type', ['ad_account', 'facebook_page', 'instagram_account', 'campaign', 'ad_set', 'ad'])
                ->get(['uuid', 'name', 'resource_type', 'external_id', 'parent_external_id', 'metadata_json', 'last_synced_at'])
                ->toArray();
            
            $adAccounts = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'ad_account'));
            $facebookPages = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'facebook_page'));
            $instagramAccounts = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'instagram_account'));
            
            // Montando a árvore de campanhas para o frontend
            $campaignsRaw = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'campaign'));
            $adSetsRaw = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'ad_set'));
            $adsRaw = array_values(array_filter($resources, fn($r) => $r['resource_type'] === 'ad'));

            // Agrupa ads por ad_set
            $adsByAdSet = [];
            foreach ($adsRaw as $ad) {
                $adsByAdSet[$ad['parent_external_id']][] = $ad;
            }

            // Agrupa ad sets (com seus ads) por campaign
            $adSetsByCampaign = [];
            foreach ($adSetsRaw as $adSet) {
                $adSet['ads'] = $adsByAdSet[$adSet['external_id']] ?? [];
                $adSetsByCampaign[$adSet['parent_external_id']][] = $adSet;
            }

            // Monta as campaigns
            foreach ($campaignsRaw as $campaign) {
                $campaign['ad_sets'] = $adSetsByCampaign[$campaign['external_id']] ?? [];
                $campaigns[] = $campaign;
            }
            
            // Remove external_ids para segurança
            $adAccounts = array_map(function($item) { unset($item['external_id']); return $item; }, $adAccounts);
            $facebookPages = array_map(function($item) { unset($item['external_id']); return $item; }, $facebookPages);
            $instagramAccounts = array_map(function($item) { unset($item['external_id']); return $item; }, $instagramAccounts);
            $campaigns = array_map(function($campaign) {
                unset($campaign['external_id']);
                $campaign['ad_sets'] = array_map(function($adSet) {
                    unset($adSet['external_id']);
                    $adSet['ads'] = array_map(function($ad) { unset($ad['external_id']); return $ad; }, $adSet['ads'] ?? []);
                    return $adSet;
                }, $campaign['ad_sets'] ?? []);
                return $campaign;
            }, $campaigns);
        }

        return Inertia::render('Integrations/Providers/Meta', [
            'app_url'            => config('app.url'),
            'integration'        => $integration,
            'config'             => $config,
            'ad_accounts'        => $adAccounts,
            'facebook_pages'     => $facebookPages,
            'instagram_accounts' => $instagramAccounts,
            'campaigns_tree'     => $campaigns,
            'is_job_running'     => \Illuminate\Support\Facades\Cache::has("meta_sync_{$integration->id}"),
        ]);
    }

    /**
     * Dispara a sincronização de todos os ativos suportados da Meta (Ad Accounts, Pages, Instagram, Campaigns, etc).
     * Segue o mesmo padrão de autorização (isOwner) de connect/disconnect.
     */
    public function syncMetaAssets(
        Request $request,
        \App\Domain\Integrations\Services\IntegrationResolver $resolver
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        abort_unless($organization->isOwner($request->user()), 403, 'Acesso restrito aos administradores.');

        try {
            $integration = $resolver->resolveOrFail($organization, 'meta');
            
            \Illuminate\Support\Facades\Cache::put("meta_sync_{$integration->id}", true, 3600);
            // Dispara o Job de forma assíncrona
            \App\Domain\Integrations\Jobs\Meta\SyncMetaAssetsJob::dispatch($integration, $request->user()->id);

            return back()->with('success', 'Sincronização iniciada. Os recursos aparecerão em breve assim que o processamento em background for concluído.');
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->with('error', 'Integração Meta não encontrada ou não está conectada.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar sincronização: ' . $e->getMessage());
        }
    }

    /**
     * Consulta Métricas e Insights da Meta (Phase 2D).
     * O backend decide se roda síncrono ou se joga pra fila baseado no level/período.
     */
    public function metaInsights(
        Request $request,
        \App\Domain\Integrations\Services\IntegrationResolver $resolver,
        \App\Domain\Integrations\Services\Meta\MetaReportRouterService $routerService
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::find(session('active_organization_id'));
        // Verifica RBAC se aplicável. No momento, todos com org.access podem ver performance.
        
        $request->validate([
            'resource_uuid' => 'required|uuid',
            'level' => 'required|string|in:account,campaign,adset,ad',
            'period' => 'required|string',
        ]);

        try {
            $integration = $resolver->resolveOrFail($organization, 'meta');
            
            $result = $routerService->dispatchInsights($integration, $request->only(['resource_uuid', 'level', 'period']));

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'async' => $result['async']
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro Meta Insights: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
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

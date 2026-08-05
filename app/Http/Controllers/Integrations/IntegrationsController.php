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
        return Inertia::render('Integrations/Index');
    }

    /**
     * Detalhes / Configuração do Google Workspace
     */
    public function googleWorkspace(Request $request)
    {
        $organization = $request->user()->organizations()->first();
        
        $integration = \App\Domain\Integrations\Models\Integration::firstOrCreate(
            ['organization_id' => $organization->id, 'provider' => 'google_workspace'],
            ['display_name' => 'Google Workspace', 'status' => 'not_connected']
        );
        
        $config = $integration->config;

        return Inertia::render('Integrations/Providers/GoogleWorkspace', [
            'app_url' => config('app.url'),
            'integration' => $integration,
            'config' => $config,
        ]);
    }

    public function saveConfig(Request $request, string $provider)
    {
        $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'tenant' => 'nullable|string',
        ]);

        $organization = $request->user()->organizations()->first();
        
        $integration = \App\Domain\Integrations\Models\Integration::firstOrCreate(
            ['organization_id' => $organization->id, 'provider' => $provider],
            ['display_name' => ucwords(str_replace('_', ' ', $provider)), 'status' => 'configuring']
        );

        \App\Domain\Integrations\Models\IntegrationConfig::updateOrCreate(
            ['integration_id' => $integration->id],
            [
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'tenant' => $request->tenant,
                'redirect_uri' => config('app.url') . "/oauth/{$provider}/callback",
            ]
        );

        // Se estava não conectado, vai pra configuring para liberar o botão
        if ($integration->status === 'not_connected') {
            $integration->update(['status' => 'configuring']);
        }

        return back()->with('success', 'Configurações salvas com sucesso.');
    }

    /**
     * Inicia o fluxo de conexão OAuth
     */
    public function connect(Request $request, string $provider, \App\Domain\Integrations\Services\IntegrationManager $manager)
    {
        $organization = $request->user()->organization;

        // Aqui nós buscaríamos da tabela `integration_configs` a configuração da organização
        $configModel = \App\Domain\Integrations\Models\IntegrationConfig::whereHas('integration', function ($query) use ($organization, $provider) {
            $query->where('organization_id', $organization->id)->where('provider', $provider);
        })->first();

        if (!$configModel || !$configModel->client_id || !$configModel->client_secret) {
            return back()->with('error', 'Por favor, salve suas configurações de Client ID e Client Secret antes de conectar.');
        }

        $config = [
            'client_id' => $configModel->client_id,
            'client_secret' => $configModel->client_secret,
            'redirect_uri' => $configModel->redirect_uri ?: config('app.url') . "/oauth/{$provider}/callback",
        ];

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
    public function callback(Request $request, string $provider, \App\Domain\Integrations\Services\IntegrationManager $manager)
    {
        $organization = $request->user()->organization;

        $configModel = \App\Domain\Integrations\Models\IntegrationConfig::whereHas('integration', function ($query) use ($organization, $provider) {
            $query->where('organization_id', $organization->id)->where('provider', $provider);
        })->first();

        $config = [
            'client_id' => $configModel->client_id ?? '',
            'client_secret' => $configModel->client_secret ?? '',
            'redirect_uri' => $configModel->redirect_uri ?: config('app.url') . "/oauth/{$provider}/callback",
        ];

        try {
            $connector = $manager->getConnector($provider);
            $connector->handleCallback($organization, $config, $request->all());

            // Atualiza status na tabela principal
            \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->update(['status' => 'connected']);

            return redirect()->route("integrations." . str_replace('_', '-', $provider))->with('success', 'Integração conectada com sucesso!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro OAuth Callback {$provider}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route("integrations." . str_replace('_', '-', $provider))->with('error', 'Falha ao conectar: ' . $e->getMessage());
        }
    }

    /**
     * Desconecta e revoga os tokens
     */
    public function disconnect(Request $request, string $provider, \App\Domain\Integrations\Services\IntegrationManager $manager)
    {
        $organization = $request->user()->organization;

        try {
            $connector = $manager->getConnector($provider);
            $connector->disconnect($organization);

            // Atualiza status na tabela principal
            \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->update(['status' => 'not_connected']);

            return back()->with('success', 'Integração desconectada com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao desconectar: ' . $e->getMessage());
        }
    }
}

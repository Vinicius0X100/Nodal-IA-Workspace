<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AITool;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;

class AIToolRegistryService
{
    /**
     * Sincroniza todas as tools de uma organização baseando-se em suas integrações ativas.
     */
    public function syncIntegrationTools(Organization $organization): void
    {
        $activeIntegrations = $organization->integrations()->where('status', 'connected')->get();

        // 1. Coletar todos os slugs que deveriam estar ativos
        $expectedSlugs = [];

        foreach ($activeIntegrations as $integration) {
            $slugs = $this->registerToolsForIntegration($organization, $integration);
            $expectedSlugs = array_merge($expectedSlugs, $slugs);
        }

        // 2. Remover tools que pertencem a integrações (integration_id != null)
        // mas cujo slug não está na lista dos esperados.
        AITool::where('organization_id', $organization->id)
            ->whereNotNull('integration_id')
            ->whereNotIn('slug', $expectedSlugs)
            ->delete();
    }

    /**
     * Desregistra (remove) todas as ferramentas atreladas a uma integração específica.
     */
    public function unregisterIntegrationTools(Integration $integration): void
    {
        AITool::where('integration_id', $integration->id)->delete();
    }

    /**
     * Registra as ferramentas para uma integração e retorna a lista de slugs criados.
     */
    private function registerToolsForIntegration(Organization $organization, Integration $integration): array
    {
        $slugs = [];

        if ($integration->provider === 'google_workspace') {
            $slugs = array_merge($slugs, $this->registerGoogleWorkspaceTools($organization, $integration));
        } elseif ($integration->provider === 'microsoft_365' || $integration->provider === 'microsoft') {
            $slugs = array_merge($slugs, $this->registerMicrosoft365Tools($organization, $integration));
        }
        
        // Futuras integrações podem ser adicionadas aqui (Slack, Jira, Notion, etc).

        return $slugs;
    }

    private function registerGoogleWorkspaceTools(Organization $organization, Integration $integration): array
    {
        $tools = [
            [
                'slug' => 'google_search_resources',
                'name' => 'Pesquisar Arquivos no Google Drive',
                'description' => 'Busca arquivos e documentos disponíveis no Google Drive da organização.',
                'endpoint' => '/api/v1/ai/resources/search?provider=google',
                'http_method' => 'GET',
                'tool_type' => 'search',
                'requires_confirmation' => false,
            ],
            [
                'slug' => 'google_calendar_events',
                'name' => 'Consultar Google Calendar',
                'description' => 'Consulta eventos futuros na agenda.',
                'endpoint' => '/api/v1/ai/calendar/google/events',
                'http_method' => 'GET',
                'tool_type' => 'search',
                'requires_confirmation' => false,
            ],
        ];

        return $this->upsertTools($organization, $integration, 'google', $tools);
    }

    private function registerMicrosoft365Tools(Organization $organization, Integration $integration): array
    {
        $tools = [
            [
                'slug' => 'microsoft_search_resources',
                'name' => 'Pesquisar Arquivos no OneDrive/SharePoint',
                'description' => 'Busca arquivos e documentos disponíveis no ambiente Microsoft da organização.',
                'endpoint' => '/api/v1/ai/resources/search?provider=microsoft',
                'http_method' => 'GET',
                'tool_type' => 'search',
                'requires_confirmation' => false,
            ],
        ];

        return $this->upsertTools($organization, $integration, 'microsoft', $tools);
    }

    /**
     * Faz o upsert das ferramentas e retorna os slugs registrados.
     */
    private function upsertTools(Organization $organization, Integration $integration, string $provider, array $tools): array
    {
        $registeredSlugs = [];

        foreach ($tools as $toolData) {
            $slug = $toolData['slug'];
            $registeredSlugs[] = $slug;

            AITool::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $slug,
                ],
                [
                    'integration_id' => $integration->id,
                    'provider' => $provider,
                    'name' => $toolData['name'],
                    'description' => $toolData['description'],
                    'endpoint' => $toolData['endpoint'],
                    'http_method' => $toolData['http_method'],
                    'tool_type' => $toolData['tool_type'],
                    'requires_confirmation' => $toolData['requires_confirmation'],
                    // fields not updated if tool exists to preserve manual disablements
                ]
            );
        }

        return $registeredSlugs;
    }
}

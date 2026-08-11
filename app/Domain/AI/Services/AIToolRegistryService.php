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

        // Registrar e manter também as ferramentas core (nativas do Nodal)
        $coreSlugs = $this->registerCoreTools($organization);
        $expectedSlugs = array_merge($expectedSlugs, $coreSlugs);

        // 2. Remover tools que pertencem a integrações (integration_id != null)
        // mas cujo slug não está na lista dos esperados.
        // mas cujo slug não está na lista dos esperados.
        // E remover tools core (integration_id == null) que não estão mais na lista de coreSlugs.
        AITool::where('organization_id', $organization->id)
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

    private function registerCoreTools(Organization $organization): array
    {
        $tools = [
            [
                'slug'                 => 'current_user',
                'name'                 => 'Consultar Usuário Atual',
                'description'          => 'Permite que a Inteligência Artificial consulte, de forma segura, os dados de identidade corporativa do usuário ativo que iniciou a conversa (como UUID, nome, e-mail, roles e contas corporativas vinculadas como Google ou Microsoft). Não aceita parâmetros e retorna sempre o usuário ativo da sessão.',
                'endpoint'             => '/api/ai/current-user',
                'http_method'          => 'GET',
                'tool_type'            => 'read',
                'requires_confirmation' => false,
                'required_permissions' => [],
                'requires_external_identity' => false,
            ],
        ];

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
                    'integration_id' => null,
                    'provider' => 'nodal',
                    'name' => $toolData['name'],
                    'description' => $toolData['description'],
                    'endpoint' => $toolData['endpoint'],
                    'http_method' => $toolData['http_method'],
                    'tool_type' => $toolData['tool_type'],
                    'requires_confirmation' => $toolData['requires_confirmation'],
                    'configuration_json' => [
                        'required_permissions' => $toolData['required_permissions'] ?? [],
                        'requires_external_identity' => $toolData['requires_external_identity'] ?? false,
                    ],
                ]
            );
        }

        return $registeredSlugs;
    }

    private function registerGoogleWorkspaceTools(Organization $organization, Integration $integration): array
    {
        $tools = [
            [
                'slug' => 'google_search_resources',
                'name' => 'Pesquisar Arquivos no Google Drive',
                'description' => 'Busca arquivos no Google Drive. Parâmetros opcionais (query string): q (busca textual), type (ex: spreadsheet, document, pdf, folder, presentation, image), provider (google), limit (padrão 50). Exemplos: ?type=spreadsheet (lista planilhas), ?q=financeiro&type=spreadsheet (planilhas sobre financeiro).',
                'endpoint' => '/api/ai/resources/search?provider=google',
                'http_method' => 'GET',
                'tool_type' => 'search',
                'requires_confirmation' => false,
                'required_permissions' => ['resources.search'],
                'requires_external_identity' => false, // O search de metadados não bate no google
            ],
            [
                'slug'                 => 'google_calendar_events_list',
                'name'                 => 'Buscar Eventos do Calendarário',
                'description'          => 'Consulta eventos do Google Calendar. Parâmetros opcionais (query string): start (RFC3339), end (RFC3339), query (busca textual), calendar_id (padrão: primary), limit (máx: 100, padrão: 20), time_zone (IANA, ex: America/Sao_Paulo). A consulta utiliza a conta corporativa (External Identity) vinculada ao usuário solicitante.',
                'endpoint'             => '/api/ai/calendar/events',
                'http_method'          => 'GET',
                'tool_type'            => 'search',
                'requires_confirmation' => false,
                'required_permissions' => ['calendar.events.read'],
                'requires_external_identity' => true,
            ],
            [
                'slug'                 => 'google_calendar_freebusy',
                'name'                 => 'Consultar Disponibilidade do Calendário',
                'description'          => 'Consulta disponibilidade (horários livres e ocupados) no Google Calendar sem revelar os detalhes ou títulos dos eventos. Aceita um JSON no body: start (RFC3339 obrigatório), end (RFC3339 obrigatório), calendar_id (opcional, padrão primary), slot_duration_minutes (opcional). A consulta utiliza a conta corporativa (External Identity) vinculada.',
                'endpoint'             => '/api/ai/calendar/freebusy',
                'http_method'          => 'POST',
                'tool_type'            => 'read',
                'requires_confirmation' => false,
                'required_permissions' => ['calendar.freebusy.read'],
                'requires_external_identity' => true,
            ],
            [
                'slug'                 => 'google_calendar_event_create',
                'name'                 => 'Criar Evento no Calendário',
                'description'          => 'Permite criar eventos no calendário autorizado através do Nodal. É necessário confirmação do usuário (requires_confirmation=true).',
                'endpoint'             => '/api/ai/calendar/events',
                'http_method'          => 'POST',
                'tool_type'            => 'write',
                'requires_confirmation' => true,
                'required_permissions' => ['calendar.events.create'],
                'requires_external_identity' => true,
            ],
            [
                'slug' => 'google_read_resource',
                'name' => 'Ler Conteúdo de Arquivo do Google Drive',
                'description' => 'Lê e extrai o texto do conteúdo de um arquivo específico do Google Drive usando seu UUID. A consulta utiliza a conta corporativa (External Identity) vinculada ao usuário solicitante (via Impersonation) garantindo que apenas arquivos que o usuário tem acesso no Google Drive possam ser lidos.',
                'endpoint' => '/api/ai/resources/{uuid}/content', // O {uuid} deve ser substituído pelo UUID do recurso
                'http_method' => 'GET',
                'tool_type' => 'action',
                'requires_confirmation' => false,
                'required_permissions' => ['resources.read'],
                'requires_external_identity' => true,
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
                'description' => 'Busca arquivos no ambiente Microsoft. Parâmetros opcionais (query string): q (busca textual), type (ex: spreadsheet, document, pdf, folder), provider (microsoft), limit (padrão 50).',
                'endpoint' => '/api/ai/resources/search?provider=microsoft',
                'http_method' => 'GET',
                'tool_type' => 'search',
                'requires_confirmation' => false,
                'required_permissions' => ['resources.search'],
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
                    'configuration_json' => [
                        'required_permissions' => $toolData['required_permissions'] ?? [],
                        'requires_external_identity' => $toolData['requires_external_identity'] ?? false,
                    ],
                ]
            );
        }

        return $registeredSlugs;
    }
}

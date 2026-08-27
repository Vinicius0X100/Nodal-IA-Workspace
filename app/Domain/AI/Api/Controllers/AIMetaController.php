<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIMetaResourceResource;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationResolver;
use App\Domain\Integrations\Services\Meta\MetaReportRouterService;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Services\AuthorizationService;
use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Repositories\ResourceRepository;
use App\Domain\Reports\Services\AsyncReportResultStorage;
use App\Http\Requests\AI\AIMetaCampaignsRequest;
use App\Http\Requests\AI\AIMetaInsightsRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AIMetaController — READ-ONLY v1
 *
 * Responsabilidades:
 *  - Validar a requisição (via FormRequests).
 *  - Autorizar o usuário (via AuthorizationService + meta.read).
 *  - Resolver a integração Meta da organização ativa.
 *  - Delegar aos Domain Services existentes.
 *  - Retornar resposta normalizada e sanitizada.
 *
 * Nunca chama a Graph API diretamente.
 * Nunca expõe tokens, external_ids ou IDs internos.
 */
class AIMetaController
{
    private const ALLOWED_RESOURCE_TYPES = [
        'ad_account', 'campaign', 'ad_set', 'ad',
        'facebook_page', 'instagram_account',
    ];

    public function __construct(
        private AuthorizationService $authorizationService,
        private IntegrationResolver $resolver,
        private ResourceRepository $repository,
        private MetaReportRouterService $reportRouter,
        private AsyncReportResultStorage $resultStorage,
    ) {}

    /**
     * GET /api/ai/meta/accounts
     *
     * Lista Ad Accounts Meta disponíveis para a organização ativa.
     * Fonte: integration_resources (dados sincronizados localmente).
     */
    public function accounts(Request $request): JsonResponse
    {
        try {
            [$organization, $user, $integration] = $this->resolveContext($request);

            $resources = IntegrationResource::where('integration_id', $integration->id)
                ->where('resource_type', 'ad_account')
                ->orderBy('name')
                ->take(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => AIMetaResourceResource::collection($resources),
            ]);
        } catch (\Exception $e) {
            return $this->handleMetaException($e);
        }
    }

    /**
     * GET /api/ai/meta/campaigns
     *
     * Lista Campaigns Meta, opcionalmente filtradas por Ad Account, nome e status.
     * Fonte: integration_resources (dados sincronizados localmente).
     */
    public function campaigns(AIMetaCampaignsRequest $request): JsonResponse
    {
        try {
            [$organization, $user, $integration] = $this->resolveContext($request);

            $query = IntegrationResource::where('integration_id', $integration->id)
                ->where('resource_type', 'campaign');

            // Filtro por Ad Account (resolve UUID → external_id para parent lookup)
            if ($adAccountUuid = $request->validated('ad_account_uuid')) {
                $adAccount = $this->repository->findByUuid($organization->id, $adAccountUuid);
                if (!$adAccount || $adAccount->integration_id !== $integration->id || ($adAccount->resource_type instanceof \BackedEnum ? $adAccount->resource_type->value : $adAccount->resource_type) !== 'ad_account') {
                    return response()->json([
                        'success' => false,
                        'code' => 'META_RESOURCE_NOT_FOUND',
                        'message' => 'Conta de anúncio não encontrada.',
                    ], 404);
                }
                $query->where('parent_external_id', $adAccount->external_id);
            }

            // Filtro por nome (search)
            if ($search = $request->validated('search')) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Filtro por status (metadata_json->effective_status)
            if ($status = $request->validated('status')) {
                $query->whereJsonContains('metadata_json->effective_status', $status);
            }

            $limit = $request->validated('limit') ?? 50;

            $campaigns = $query->orderBy('name')->take($limit)->get();

            // Enriquecer cada campaign com info compacta da Ad Account pai
            $campaigns->each(function ($campaign) use ($integration) {
                if ($campaign->parent_external_id) {
                    $parent = IntegrationResource::where('integration_id', $integration->id)
                        ->where('external_id', $campaign->parent_external_id)
                        ->first(['uuid', 'name']);
                    if ($parent) {
                        $campaign->setAttribute('_ad_account_compact', [
                            'uuid' => $parent->uuid,
                            'name' => $parent->name,
                        ]);
                    }
                }
            });

            $data = $campaigns->map(function ($campaign) {
                $resource = (new AIMetaResourceResource($campaign))->resolve(request());
                if ($campaign->getAttribute('_ad_account_compact')) {
                    $resource['ad_account'] = $campaign->getAttribute('_ad_account_compact');
                }
                return $resource;
            });

            return response()->json([
                'success' => true,
                'data' => $data->values(),
            ]);
        } catch (\Exception $e) {
            return $this->handleMetaException($e);
        }
    }

    /**
     * GET /api/ai/meta/resources/{uuid}
     *
     * Consulta um resource Meta pelo UUID interno.
     * Suporta ?children=true para incluir filhos diretos.
     */
    public function resource(Request $request, string $uuid): JsonResponse
    {
        try {
            [$organization, $user, $integration] = $this->resolveContext($request);

            $resource = $this->repository->findByUuid($organization->id, $uuid);

            if (!$resource || $resource->integration_id !== $integration->id) {
                return response()->json([
                    'success' => false,
                    'code' => 'META_RESOURCE_NOT_FOUND',
                    'message' => 'Recurso não encontrado.',
                ], 404);
            }

            $resourceType = $resource->resource_type instanceof \BackedEnum
                ? $resource->resource_type->value
                : (string) $resource->resource_type;

            if (!in_array($resourceType, self::ALLOWED_RESOURCE_TYPES, true)) {
                return response()->json([
                    'success' => false,
                    'code' => 'META_RESOURCE_TYPE_INVALID',
                    'message' => 'Tipo de recurso não suportado para consulta Meta.',
                ], 400);
            }

            // Children opcionais
            if ($request->boolean('children')) {
                $children = IntegrationResource::where('integration_id', $integration->id)
                    ->where('parent_external_id', $resource->external_id)
                    ->orderBy('name')
                    ->take(100)
                    ->get();
                $resource->setRelation('children_resources', $children);
            }

            return response()->json([
                'success' => true,
                'data' => new AIMetaResourceResource($resource),
            ]);
        } catch (\Exception $e) {
            return $this->handleMetaException($e);
        }
    }

    /**
     * POST /api/ai/meta/insights
     *
     * Consulta métricas de performance.
     * Delega inteiramente ao MetaReportRouterService (decisão sync/async backend-driven).
     */
    public function insights(AIMetaInsightsRequest $request): JsonResponse
    {
        try {
            [$organization, $user, $integration] = $this->resolveContext($request);

            // Monta o período: pode vir como preset ou date_from/date_to
            $params = $request->only(['resource_uuid', 'level']);

            if ($request->filled('period')) {
                $params['period'] = $request->input('period');
            } elseif ($request->filled('date_from') && $request->filled('date_to')) {
                $params['period'] = "custom:{$request->input('date_from')}:{$request->input('date_to')}";
            } else {
                $params['period'] = 'last_7d'; // default seguro
            }

            // Suporte a resource_uuids (lote) — itera cada um
            if ($request->filled('resource_uuids')) {
                $allData = [];
                $anyAsync = false;

                foreach ($request->input('resource_uuids') as $rUuid) {
                    $singleParams = array_merge($params, ['resource_uuid' => $rUuid]);
                    $result = $this->reportRouter->dispatchInsights($integration, $singleParams);

                    if ($result['async']) {
                        $anyAsync = true;
                        $allData[] = [
                            'resource_uuid' => $rUuid,
                            'mode' => 'async',
                            'report_uuid' => $result['data']['report_uuid'],
                            'status' => 'queued',
                        ];
                    } else {
                        foreach ($result['data'] as $item) {
                            $item['mode'] = 'sync';
                            $allData[] = $item;
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => $allData,
                    'async' => $anyAsync,
                ]);
            }

            // Single resource
            $result = $this->reportRouter->dispatchInsights($integration, $params);

            if ($result['async']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'mode' => 'async',
                        'report_uuid' => $result['data']['report_uuid'],
                        'status' => 'queued',
                    ],
                    'async' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'mode' => 'sync',
                    'items' => $result['data'],
                ],
                'async' => false,
            ]);
        } catch (\Exception $e) {
            return $this->handleMetaException($e);
        }
    }

    /**
     * GET /api/ai/reports/{uuid}
     *
     * Consulta o estado de um AsyncReport scoped pela organização ativa.
     * Coexiste com GET /api/reports/{uuid} (web, autenticado via sessão).
     */
    public function report(Request $request, string $uuid): JsonResponse
    {
        try {
            [$organization, $user] = $this->resolveContextLight($request);

            $report = AsyncReport::where('organization_id', $organization->id)
                ->where('uuid', $uuid)
                ->first();

            if (!$report) {
                return response()->json([
                    'success' => false,
                    'code' => 'META_REPORT_NOT_FOUND',
                    'message' => 'Relatório não encontrado.',
                ], 404);
            }

            $pollingIntervals = config('reports.polling_interval', []);

            $data = [
                'uuid'     => $report->uuid,
                'status'   => $report->status,
                'progress' => $report->progress ?? 0,
            ];

            // Sugestão de retry para o poller (Tool futura)
            if (isset($pollingIntervals[$report->status])) {
                $data['retry_after_seconds'] = $pollingIntervals[$report->status];
            }

            if ($report->started_at) {
                $data['started_at'] = $report->started_at->toIso8601String();
            }

            if ($report->status === 'completed') {
                $data['progress'] = 100;
                $data['completed_at'] = $report->completed_at?->toIso8601String();
                // Recupera resultado de banco ou Storage de forma transparente
                $data['result'] = $this->resultStorage->retrieve($report);
            }

            if ($report->status === 'partial') {
                $data['result'] = $this->resultStorage->retrieve($report);
                $data['partial'] = true;
            }

            if ($report->status === 'failed') {
                $data['error'] = [
                    'code'    => strtoupper($report->provider ?? 'GENERIC') . '_REPORT_FAILED',
                    'message' => 'Não foi possível concluir o relatório.',
                ];
            }

            // Observabilidade disponível para diagnóstico
            if (!empty($report->metadata)) {
                $data['_meta'] = [
                    'pages'    => $report->metadata['pages'] ?? null,
                    'records'  => $report->metadata['records'] ?? null,
                    'duration_ms' => $report->metadata['duration_ms'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return $this->handleMetaException($e);
        }
    }

    // ─── Helpers privados ───────────────────────────────────────────────

    /**
     * Resolve organização, usuário e integração Meta conectada.
     * Autoriza com meta.read.
     *
     * @return array{0: Organization, 1: \App\Domain\Identity\Models\User, 2: Integration}
     * @throws AuthorizationException|ModelNotFoundException
     */
    private function resolveContext(Request $request): array
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');

        $this->authorizationService->authorize($user, $organization, 'meta.read');

        $integration = $this->resolveMetaIntegration($organization);

        return [$organization, $user, $integration];
    }

    /**
     * Resolve organização e usuário sem integração (para reports genéricos).
     * Autoriza com meta.read.
     *
     * @return array{0: Organization, 1: \App\Domain\Identity\Models\User}
     */
    private function resolveContextLight(Request $request): array
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');

        $this->authorizationService->authorize($user, $organization, 'meta.read');

        return [$organization, $user];
    }

    /**
     * Resolve a integração Meta conectada, tratando status needs_reconnect.
     */
    private function resolveMetaIntegration(Organization $organization): Integration
    {
        // Primeiro tenta resolver qualquer integração Meta
        $integration = $this->resolver->resolve($organization, 'meta');

        if (!$integration) {
            throw new ModelNotFoundException('META_NOT_CONNECTED');
        }

        if (in_array($integration->status, ['needs_reconnect', 'revoked', 'error'], true)) {
            throw new \RuntimeException('META_NEEDS_RECONNECT');
        }

        if ($integration->status !== 'connected') {
            throw new ModelNotFoundException('META_NOT_CONNECTED');
        }

        return $integration;
    }

    /**
     * Trata exceções Meta e retorna resposta JSON normalizada.
     * Nunca expõe stack trace ou erros brutos da Graph API.
     */
    private function handleMetaException(\Exception $e): JsonResponse
    {
        // Erros de autorização (AuthorizationService)
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'code' => 'META_PERMISSION_DENIED',
                'message' => $e->getMessage(),
            ], 403);
        }

        // Integração não conectada
        if ($e instanceof ModelNotFoundException) {
            $code = $e->getMessage() === 'META_NOT_CONNECTED' ? 'META_NOT_CONNECTED' : 'META_RESOURCE_NOT_FOUND';
            $message = $code === 'META_NOT_CONNECTED'
                ? 'A integração Meta não está conectada.'
                : 'Recurso não encontrado.';

            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
            ], 404);
        }

        // Integração inativa / token expirado
        if ($e instanceof \App\Domain\Identities\Exceptions\IntegrationInactiveException) {
            return response()->json([
                'success' => false,
                'code' => 'META_NEEDS_RECONNECT',
                'message' => 'A integração Meta precisa ser reconectada.',
            ], 403);
        }

        // Needs reconnect explícito
        if ($e instanceof \RuntimeException && $e->getMessage() === 'META_NEEDS_RECONNECT') {
            return response()->json([
                'success' => false,
                'code' => 'META_NEEDS_RECONNECT',
                'message' => 'A integração Meta precisa ser reconectada.',
            ], 403);
        }

        // Rate limit
        if ($e->getCode() === 429 || str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'Rate limit')) {
            return response()->json([
                'success' => false,
                'code' => 'META_RATE_LIMITED',
                'message' => 'A Meta limitou temporariamente as consultas. Tente novamente em alguns instantes.',
            ], 429);
        }

        // Período inválido
        if ($e instanceof \InvalidArgumentException && str_contains($e->getMessage(), 'Período')) {
            return response()->json([
                'success' => false,
                'code' => 'META_INSIGHTS_INVALID_PERIOD',
                'message' => $e->getMessage(),
            ], 400);
        }

        // Outros erros de validação/request
        if ($e instanceof \InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'code' => 'META_INVALID_REQUEST',
                'message' => $e->getMessage(),
            ], 400);
        }

        // Validation exceptions
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'success' => false,
                'code' => 'META_INVALID_REQUEST',
                'message' => 'Parâmetros inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Erro genérico — nunca vaza stack trace
        \Illuminate\Support\Facades\Log::error('[AIMetaController] Erro inesperado', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'code' => 'META_INTERNAL_ERROR',
            'message' => 'Erro interno ao processar a requisição.',
        ], 500);
    }
}

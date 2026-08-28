<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Models\AIAction;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationResolver;
use App\Domain\Integrations\Services\Meta\MetaActionService;
use App\Domain\Integrations\Services\Meta\MetaBudgetService;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Services\AuthorizationService;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIMetaActionsController
{
    private const ALLOWED_STATUS = ['ACTIVE', 'PAUSED'];
    private const ALLOWED_TYPES = ['campaign', 'ad_set', 'ad'];

    public function __construct(
        private AuthorizationService $authorizationService,
        private IntegrationResolver $integrationResolver,
        private ResourceRepository $resourceRepository,
        private MetaActionService $metaActionService,
        private MetaBudgetService $metaBudgetService,
    ) {}

    public function prepareStatusUpdate(Request $request): JsonResponse
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');

        $this->authorizationService->authorize($user, $organization, 'meta.write');

        $validated = $request->validate([
            'resource_uuid' => 'required|uuid',
            'status' => 'required|string|in:' . implode(',', self::ALLOWED_STATUS),
        ]);

        $integration = $this->integrationResolver->resolveOrFail($organization, 'meta');

        $resource = $this->resourceRepository->findByUuid($organization->id, $validated['resource_uuid']);

        if (!$resource || $resource->integration_id !== $integration->id || $resource->provider->value !== 'meta') {
            return response()->json([
                'success' => false,
                'code' => 'META_RESOURCE_NOT_FOUND',
                'message' => 'O recurso alvo não foi encontrado.'
            ], 404);
        }

        $resourceType = $resource->resource_type instanceof \BackedEnum
            ? $resource->resource_type->value
            : (string) $resource->resource_type;

        if (!in_array($resourceType, self::ALLOWED_TYPES, true)) {
            return response()->json([
                'success' => false,
                'code' => 'META_INVALID_RESOURCE_TYPE',
                'message' => 'O tipo de recurso ('.$resourceType.') não suporta alteração de status por aqui.'
            ], 422);
        }

        $conversationUuid = $request->header('X-Conversation-UUID');

        // Criar ação pendente
        $action = AIAction::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'conversation_id' => $conversationUuid,
            'integration_id' => $integration->id,
            'provider' => 'meta',
            'action_type' => 'status.update',
            'target_resource_uuid' => $resource->uuid,
            'prepared_params' => ['status' => $validated['status']],
            'snapshot' => ['effective_status' => $resource->metadata_json['effective_status'] ?? null],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'prepared_at' => now(),
            // Prevenir multiple actions pending idênticas pelo mesmo usuário? (Opcional)
            'idempotency_key' => 'meta_status_update_' . $resource->uuid . '_' . $validated['status'] . '_' . Str::random(8),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'action_uuid' => $action->uuid,
                'status' => 'pending',
                'expires_at' => $action->expires_at->toIso8601String(),
                'confirmation_message' => "Você está prestes a alterar o status de {$resource->name} (tipo: {$resourceType}) para {$validated['status']}. Deseja confirmar?",
                'prepared_params' => $action->prepared_params,
            ]
        ]);
    }

    public function prepareBudgetUpdate(Request $request): JsonResponse
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');

        $this->authorizationService->authorize($user, $organization, 'meta.write');

        $validated = $request->validate([
            'resource_uuid' => 'required|uuid',
            'budget' => 'required|numeric|min:0.01',
            'budget_type' => 'nullable|string|in:daily,lifetime',
        ]);

        $integration = $this->integrationResolver->resolveOrFail($organization, 'meta');
        $conversationUuid = $request->header('X-Conversation-UUID');

        try {
            $data = $this->metaBudgetService->prepareBudgetUpdate(
                $organization,
                $user,
                $integration,
                $validated['resource_uuid'],
                (float) $validated['budget'],
                $validated['budget_type'] ?? null,
                $conversationUuid
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'code' => 'META_ACTION_INVALID',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function executeAction(Request $request, string $actionUuid): JsonResponse
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');

        $this->authorizationService->authorize($user, $organization, 'meta.write');

        $action = AIAction::where('uuid', $actionUuid)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$action) {
            return response()->json([
                'success' => false,
                'code' => 'META_ACTION_NOT_FOUND',
                'message' => 'Ação não encontrada ou você não tem permissão para acessá-la.'
            ], 404);
        }

        if ($action->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'code' => 'META_ACTION_UNAUTHORIZED',
                'message' => 'Você não pode confirmar uma ação preparada por outro usuário.'
            ], 403);
        }

        if ($action->isExpired()) {
            if ($action->isPending()) {
                $action->update(['status' => 'expired']);
            }
            return response()->json([
                'success' => false,
                'code' => 'META_ACTION_EXPIRED',
                'message' => 'A validade desta ação já expirou.'
            ], 400);
        }

        try {
            $this->integrationResolver->resolveOrFail($organization, 'meta');

            if ($action->action_type === 'budget.update') {
                $result = $this->metaBudgetService->executeBudgetUpdate($action->uuid);
            } else {
                $result = $this->metaActionService->executeStatusUpdate($action->uuid);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'action_uuid' => $action->uuid,
                    'status' => 'executed',
                    'result' => $result,
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'code' => 'META_ACTION_INVALID',
                'message' => $e->getMessage()
            ], 400);
        } catch (\App\Domain\Integrations\Services\Meta\MetaRateLimitException $e) {
            return response()->json([
                'success' => false,
                'code' => 'META_RATE_LIMITED',
                'message' => 'Muitas requisições para a Meta. Tente novamente mais tarde.'
            ], 429);
        } catch (\App\Domain\Integrations\Exceptions\IntegrationNeedsReconnectException $e) {
            return response()->json([
                'success' => false,
                'code' => 'META_NEEDS_RECONNECT',
                'message' => $e->getMessage()
            ], 403);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'inválido') || str_contains($e->getMessage(), 'reconectada') || str_contains($e->getMessage(), 'expi')) {
                return response()->json([
                    'success' => false,
                    'code' => 'META_NEEDS_RECONNECT',
                    'message' => $e->getMessage()
                ], 403);
            }
            return response()->json([
                'success' => false,
                'code' => 'META_API_ERROR',
                'message' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'META_API_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getPendingAction(Request $request): JsonResponse
    {
        $organization = $request->get('_active_organization');
        $user = $request->get('_active_user');
        $conversationUuid = $request->header('X-Conversation-UUID');

        $this->authorizationService->authorize($user, $organization, 'meta.write');

        if (!$conversationUuid) {
            return response()->json([
                'success' => false,
                'code' => 'META_MISSING_CONVERSATION',
                'message' => 'O cabeçalho X-Conversation-UUID é obrigatório para resolver ações pendentes.'
            ], 400);
        }

        $pendingActions = AIAction::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('conversation_id', $conversationUuid)
            ->where('provider', 'meta')
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get();

        if ($pendingActions->isEmpty()) {
            return response()->json([
                'success' => false,
                'code' => 'META_PENDING_ACTION_NOT_FOUND',
                'message' => 'Nenhuma ação pendente válida foi encontrada para esta conversa.'
            ], 404);
        }

        if ($pendingActions->count() > 1) {
            return response()->json([
                'success' => false,
                'code' => 'META_PENDING_ACTION_AMBIGUOUS',
                'message' => 'Múltiplas ações pendentes foram encontradas nesta conversa. Não é possível determinar qual executar.',
                'data' => [
                    'count' => $pendingActions->count()
                ]
            ], 409);
        }

        $action = $pendingActions->first();
        $resource = $this->resourceRepository->findByUuid($organization->id, $action->target_resource_uuid);

        $resourceType = $resource->resource_type instanceof \BackedEnum
            ? $resource->resource_type->value
            : (string) $resource->resource_type;

        $responseData = [
            'action_uuid' => $action->uuid,
            'action_type' => str_replace('.', '_', $action->action_type),
            'resource' => [
                'uuid' => $resource->uuid,
                'type' => $resourceType,
                'name' => $resource->name,
            ],
            'expires_at' => $action->expires_at->toIso8601String(),
        ];

        if ($action->action_type === 'budget.update') {
            $proposedDecimal = $action->prepared_params['budget'] ?? 0;
            $currentDecimal = $action->snapshot['effective_budget'] ?? 0;
            $currency = $action->snapshot['currency'] ?? 'USD';
            $budgetType = $action->snapshot['budget_type'] ?? 'daily_budget';

            $differenceDecimal = $proposedDecimal - $currentDecimal;
            $differencePercent = $currentDecimal > 0 
                ? round(($differenceDecimal / $currentDecimal) * 100, 2) 
                : 100.0;

            if (isset($action->prepared_params['budget']) && is_int($action->prepared_params['budget'])) {
                // If stored subunit
                $proposedDecimal = \App\Domain\Financial\Services\CurrencyHelper::toDecimal($action->prepared_params['budget'], $currency);
                $differenceDecimal = $proposedDecimal - $currentDecimal;
                $differencePercent = $currentDecimal > 0 
                    ? round(($differenceDecimal / $currentDecimal) * 100, 2) 
                    : 100.0;
            }

            $responseData['budget'] = [
                'type' => str_replace('_budget', '', $budgetType),
                'currency' => $currency,
                'current' => $currentDecimal,
                'proposed' => $proposedDecimal,
                'difference' => round($differenceDecimal, 2),
                'difference_percent' => $differencePercent,
            ];
        } else {
            $responseData['from_status'] = $action->snapshot['effective_status'] ?? 'UNKNOWN';
            $responseData['to_status'] = $action->prepared_params['status'] ?? 'UNKNOWN';
        }

        return response()->json([
            'success' => true,
            'data' => $responseData
        ]);
    }
}

<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Models\AIAction;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\IntegrationResolver;
use App\Domain\Integrations\Services\Meta\MetaActionService;
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

    public function executeStatusUpdate(Request $request, string $actionUuid): JsonResponse
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

            $result = $this->metaActionService->executeStatusUpdate($action->uuid);

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

        return response()->json([
            'success' => true,
            'data' => [
                'action_uuid' => $action->uuid,
                'action_type' => 'status_update',
                'resource' => [
                    'uuid' => $resource->uuid,
                    'type' => $resourceType,
                    'name' => $resource->name,
                ],
                'from_status' => $action->snapshot['effective_status'] ?? 'UNKNOWN',
                'to_status' => $action->prepared_params['status'] ?? 'UNKNOWN',
                'expires_at' => $action->expires_at->toIso8601String(),
            ]
        ]);
    }
}

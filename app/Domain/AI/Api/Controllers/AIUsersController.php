<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIUserResource;
use App\Domain\AI\Api\Services\AIUsersService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIUsersController
{
    public function __construct(
        private AIUsersService $service,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $this->authorizationService->authorize($user, $organization, 'directory.users.read');

            $users = $this->service->getOrganizationUsers($organization);

            return response()->json([
                'success' => true,
                'data' => AIUserResource::collection($users),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }

    public function groups(Request $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            // Require both capabilities
            $this->authorizationService->authorize($user, $organization, 'directory.users.read');
            $this->authorizationService->authorize($user, $organization, 'directory.groups.read');

            $result = $this->service->getUserGroups($organization, $user, $uuid);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User not found in the active organization.'
                ], 404);
            }

            // Extract target user
            $targetUser = $result['user'];

            // Audit
            \App\Domain\Audit\Models\AuditLog::create([
                'action' => 'ai_read_user_groups',
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'entity_type' => \App\Domain\Identity\Models\User::class,
                'entity_id' => $targetUser->id,
                'metadata' => [
                    'target_user_uuid' => $uuid,
                    'conversation_uuid' => $request->header('X-Conversation-UUID'), // Optional, depending on gateway
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Format response
            $groups = $result['groups']->map(fn ($g) => [
                'uuid' => $g->uuid,
                'name' => $g->name,
                'email' => $g->email,
                'provider' => $g->integration ? $g->integration->provider : null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'uuid' => $targetUser->uuid,
                        'name' => $targetUser->name,
                        'email' => $targetUser->email,
                    ],
                    'groups' => $groups,
                    'total' => $groups->count(),
                ]
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user groups: ' . $e->getMessage()
            ], 500);
        }
    }
}

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
}

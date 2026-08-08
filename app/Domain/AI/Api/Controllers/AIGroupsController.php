<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIGroupResource;
use App\Domain\AI\Api\Services\AIGroupsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIGroupsController
{
    public function __construct(
        private AIGroupsService $service,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $this->authorizationService->authorize($user, $organization, 'directory.groups.read');

            $groups = $this->service->getOrganizationGroups($organization);

            return response()->json([
                'success' => true,
                'data' => AIGroupResource::collection($groups),
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
                'message' => 'Error fetching groups: ' . $e->getMessage()
            ], 500);
        }
    }
}

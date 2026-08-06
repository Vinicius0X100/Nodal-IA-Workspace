<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIUserResource;
use App\Domain\AI\Api\Services\AIUsersService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIUsersController
{
    public function __construct(private AIUsersService $service) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $users = $this->service->getOrganizationUsers($organization);

            return response()->json([
                'success' => true,
                'data' => AIUserResource::collection($users),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching users: ' . $e->getMessage()
            ], 500);
        }
    }
}

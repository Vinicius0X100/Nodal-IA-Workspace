<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIGroupResource;
use App\Domain\AI\Api\Services\AIGroupsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIGroupsController
{
    public function __construct(private AIGroupsService $service) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $groups = $this->service->getOrganizationGroups($organization);

            return response()->json([
                'success' => true,
                'data' => AIGroupResource::collection($groups),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching groups: ' . $e->getMessage()
            ], 500);
        }
    }
}

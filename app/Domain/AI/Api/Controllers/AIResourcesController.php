<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIResourceResource;
use App\Domain\AI\Api\Services\AIResourcesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIResourcesController
{
    public function __construct(private AIResourcesService $service) {}

    public function search(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $query = $request->query('q', '');

            $resources = $this->service->search($organization, $query);

            return response()->json([
                'success' => true,
                'data' => AIResourceResource::collection($resources),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching resources: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $resource = $this->service->findByUuid($organization, $uuid);

            if (!$resource) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AIResourceResource($resource),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching resource: ' . $e->getMessage()
            ], 500);
        }
    }
}

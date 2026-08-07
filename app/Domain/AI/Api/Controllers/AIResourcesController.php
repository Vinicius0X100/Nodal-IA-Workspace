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
            $provider = $request->query('provider');

            $resources = $this->service->search($organization, $query, $provider);

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

    public function content(Request $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            
            $contentData = $this->service->getContent($organization, $uuid, auth()->id());
            
            return response()->json([
                'success' => true,
                'data' => $contentData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching resource content: ' . $e->getMessage()
            ], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }

    public function file(Request $request, string $uuid)
    {
        try {
            $organization = $request->get('_active_organization');
            
            return $this->service->getFileStream($organization, $uuid, auth()->id());
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching resource file: ' . $e->getMessage()
            ], $e->getCode() >= 400 ? $e->getCode() : 500);
        }
    }
}

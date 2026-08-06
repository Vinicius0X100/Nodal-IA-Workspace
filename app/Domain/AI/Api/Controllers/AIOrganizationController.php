<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIOrganizationResource;
use App\Domain\AI\Api\Services\AIOrganizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIOrganizationController
{
    public function __construct(private AIOrganizationService $service) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $details = $this->service->getOrganizationDetails($organization);

            return response()->json([
                'success' => true,
                'data' => new AIOrganizationResource($details),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching organization details: ' . $e->getMessage()
            ], 500);
        }
    }
}

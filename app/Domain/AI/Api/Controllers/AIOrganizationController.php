<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIOrganizationResource;
use App\Domain\AI\Api\Services\AIOrganizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIOrganizationController
{
    public function __construct(
        private AIOrganizationService $service,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $this->authorizationService->authorize($user, $organization, 'organization.read');

            $details = $this->service->getOrganizationDetails($organization);

            return response()->json([
                'success' => true,
                'data' => new AIOrganizationResource($details),
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
                'message' => 'Error fetching organization details: ' . $e->getMessage()
            ], 500);
        }
    }
}

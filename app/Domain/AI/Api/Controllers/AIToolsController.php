<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIToolResource;
use App\Domain\AI\Services\AIToolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AIToolsController extends Controller
{
    public function __construct(
        private AIToolService $toolService,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    /**
     * Lista todas as ferramentas ativas para a organização atual, filtradas pelas permissões do usuário.
     */
    public function index(Request $request)
    {
        try {
            // Note: Middleware merges into input, but also app()->instance(). 
            // In other controllers we used $request->get('_active_organization')
            $organization = $request->get('_active_organization') ?? $request->attributes->get('_active_organization');
            $user = $request->get('_active_user');

            if (!$organization || !$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Context missing.'
                ], 500);
            }

            $this->authorizationService->authorize($user, $organization, 'tools.read');

            $tools = $this->toolService->getActiveToolsForOrganization($organization->id);

            // Auto-sync fallback
            if ($tools->isEmpty() && $organization->integrations()->where('status', 'connected')->exists()) {
                app(\App\Domain\AI\Services\AIToolRegistryService::class)->syncIntegrationTools($organization);
                $tools = $this->toolService->getActiveToolsForOrganization($organization->id);
            }

            // Filtragem de Tools por capabilities
            $tools = $tools->filter(function ($tool) use ($user, $organization) {
                $config = $tool->configuration_json ?? [];
                $requiredPermissions = $config['required_permissions'] ?? [];

                foreach ($requiredPermissions as $perm) {
                    if (!$this->authorizationService->can($user, $organization, $perm)) {
                        return false;
                    }
                }
                return true;
            })->values();

            return response()->json([
                'success' => true,
                'data' => AIToolResource::collection($tools)
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
                'message' => 'Error fetching tools: ' . $e->getMessage()
            ], 500);
        }
    }
}

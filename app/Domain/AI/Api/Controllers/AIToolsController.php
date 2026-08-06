<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIToolResource;
use App\Domain\AI\Services\AIToolService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AIToolsController extends Controller
{
    private AIToolService $toolService;

    public function __construct(AIToolService $toolService)
    {
        $this->toolService = $toolService;
    }

    /**
     * Lista todas as ferramentas ativas para a organização atual.
     */
    public function index(Request $request)
    {
        $organization = $request->attributes->get('_active_organization');

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found in request context.'
            ], 500);
        }

        $tools = $this->toolService->getActiveToolsForOrganization($organization->id);

        return response()->json([
            'success' => true,
            'data' => AIToolResource::collection($tools)
        ]);
    }
}

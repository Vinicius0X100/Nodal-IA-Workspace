<?php

namespace App\Http\Controllers\Api\V1\Artifacts;

use App\Http\Controllers\Controller;
use App\Domain\Artifacts\Services\ArtifactCommitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommitController extends Controller
{
    private ArtifactCommitService $commitService;

    public function __construct(ArtifactCommitService $commitService)
    {
        $this->commitService = $commitService;
    }

    public function commit(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $result = $this->commitService->commit($uuid, $organizationId);

        $status = $result['status'] === 'committed' ? 200 : 202;

        return response()->json([
            'success' => true,
            'data' => $result
        ], $status);
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $result = $this->commitService->getStatus($uuid, $organizationId);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}

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
        $organizationId = session('active_organization_id');

        if (!$organizationId) {
            abort(403, 'No active organization found in session.');
        }

        $result = $this->commitService->commit($uuid, $organizationId);

        $status = $result['status'] === 'committed' ? 200 : 202;

        return response()->json([
            'success' => true,
            'data' => $result
        ], $status);
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $organizationId = session('active_organization_id');

        if (!$organizationId) {
            abort(403, 'No active organization found in session.');
        }

        $result = $this->commitService->getStatus($uuid, $organizationId);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}

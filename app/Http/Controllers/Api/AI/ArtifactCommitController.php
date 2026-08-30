<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Domain\Artifacts\Services\ArtifactCommitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Domain\Artifacts\Models\ArtifactDraft;

class ArtifactCommitController extends Controller
{
    private ArtifactCommitService $commitService;

    public function __construct(ArtifactCommitService $commitService)
    {
        $this->commitService = $commitService;
    }

    public function commit(Request $request, string $uuid): JsonResponse
    {
        // For AI Gateway, organization is resolved from the artifact itself
        $draft = ArtifactDraft::where('uuid', $uuid)->firstOrFail();
        $organizationId = $draft->organization_id;

        $result = $this->commitService->commit($uuid, $organizationId);

        $status = $result['status'] === 'committed' ? 200 : 202;

        return response()->json([
            'success' => true,
            'data' => $result
        ], $status);
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $draft = ArtifactDraft::where('uuid', $uuid)->firstOrFail();
        $organizationId = $draft->organization_id;

        $result = $this->commitService->getStatus($uuid, $organizationId);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}

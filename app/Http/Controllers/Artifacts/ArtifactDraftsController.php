<?php

namespace App\Http\Controllers\Artifacts;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Services\SpreadsheetViewportService;
use App\Domain\Artifacts\Services\UpdateSpreadsheetFormatService;
use App\Domain\Artifacts\Services\UpdateSpreadsheetValuesService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artifacts\Spreadsheets\SpreadsheetDraftViewportRequest;
use App\Http\Requests\Artifacts\Spreadsheets\UpdateSpreadsheetDraftFormatRequest;
use App\Http\Requests\Artifacts\Spreadsheets\UpdateSpreadsheetDraftValuesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtifactDraftsController extends Controller
{
    private function getActiveOrganizationId(): int
    {
        $orgId = session('active_organization_id');
        if (!$orgId) {
            abort(403, 'Organização não selecionada.');
        }
        return $orgId;
    }

    public function getArtifact(string $artifactUuid, Request $request): JsonResponse
    {
        $orgId = $this->getActiveOrganizationId();
        
        $draft = ArtifactDraft::where('uuid', $artifactUuid)
            ->where('organization_id', $orgId)
            ->with(['sheets'])
            ->firstOrFail();
            
        return response()->json([
            'success' => true,
            'data' => [
                'artifact_uuid' => $draft->uuid,
                'type' => $draft->type,
                'status' => $draft->status->value,
                'title' => $draft->title,
                'revision' => $draft->revision,
                'sheets' => $draft->sheets->map(fn ($s) => [
                    'uuid' => $s->uuid,
                    'index' => $s->index,
                    'title' => $s->title,
                ])
            ]
        ]);
    }

    public function getSpreadsheetViewport(
        string $artifactUuid,
        SpreadsheetDraftViewportRequest $request,
        SpreadsheetViewportService $service
    ): JsonResponse {
        \Illuminate\Support\Facades\Log::info('[DRAFT_VIEWPORT_DEBUG] controller reached', [
            'artifact_uuid' => $artifactUuid,
            'user_uuid' => auth()->user()?->uuid,
            'request_path' => request()->path(),
            'query' => request()->query(),
        ]);
        
        $orgId = $this->getActiveOrganizationId();
        
        \Illuminate\Support\Facades\Log::info('[DRAFT_VIEWPORT_DEBUG] context resolved', [
            'active_organization_id' => $orgId,
            'searched_artifact_uuid' => $artifactUuid,
        ]);
        
        $data = $service->getViewport($artifactUuid, $orgId, $request->input('sheet'), $request->input('range'));
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function updateSpreadsheetValues(
        string $artifactUuid,
        UpdateSpreadsheetDraftValuesRequest $request,
        UpdateSpreadsheetValuesService $service
    ): JsonResponse {
        $orgId = $this->getActiveOrganizationId();
        
        $data = $service->execute(
            $artifactUuid,
            $orgId,
            $request->input('sheet_uuid'),
            $request->input('expected_revision'),
            $request->input('updates')
        );
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function updateSpreadsheetFormat(
        string $artifactUuid,
        UpdateSpreadsheetDraftFormatRequest $request,
        UpdateSpreadsheetFormatService $service
    ): JsonResponse {
        $orgId = $this->getActiveOrganizationId();
        
        $data = $service->execute(
            $artifactUuid,
            $orgId,
            $request->input('sheet_uuid'),
            $request->input('expected_revision'),
            $request->input('operations')
        );
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

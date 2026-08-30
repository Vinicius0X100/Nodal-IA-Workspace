<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactDraftChange;
use App\Domain\Artifacts\Services\CreateSpreadsheetDraftService;
use App\Domain\Artifacts\Services\SpreadsheetViewportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artifacts\Spreadsheets\CreateSpreadsheetDraftRequest;
use App\Http\Requests\Artifacts\Spreadsheets\SpreadsheetDraftViewportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIArtifactDraftsController extends Controller
{
    public function createSpreadsheetDraft(
        CreateSpreadsheetDraftRequest $request,
        CreateSpreadsheetDraftService $service
    ): JsonResponse {
        $orgUuid = $request->header('X-Organization-UUID');
        $userUuid = $request->header('X-User-UUID');
        $conversationUuid = $request->header('X-Conversation-UUID');
        
        $orgId = \App\Domain\Organizations\Models\Organization::where('uuid', $orgUuid)->value('id');
        $userId = $userUuid ? \App\Domain\Identity\Models\User::where('uuid', $userUuid)->value('id') : null;
        
        if (!$orgId) {
            abort(404, 'Organization not found');
        }
        
        $draft = $service->execute($orgId, $userId, $conversationUuid, $request->validated());
        
        return response()->json([
            'success' => true,
            'data' => [
                'artifact_uuid' => $draft->uuid,
                'type' => $draft->type,
                'status' => $draft->status->value,
                'title' => $draft->title,
                'revision' => $draft->revision,
            ]
        ], 201);
    }

    public function getSpreadsheetViewport(
        string $artifactUuid,
        SpreadsheetDraftViewportRequest $request,
        SpreadsheetViewportService $service
    ): JsonResponse {
        $orgUuid = $request->header('X-Organization-UUID');
        $orgId = \App\Domain\Organizations\Models\Organization::where('uuid', $orgUuid)->value('id');
        
        if (!$orgId) {
            abort(404, 'Organization not found');
        }
        
        $data = $service->getViewport($artifactUuid, $orgId, $request->input('sheet'), $request->input('range'));
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getChanges(string $artifactUuid, Request $request): JsonResponse
    {
        $orgUuid = $request->header('X-Organization-UUID');
        $orgId = \App\Domain\Organizations\Models\Organization::where('uuid', $orgUuid)->value('id');
        
        if (!$orgId) {
            abort(404, 'Organization not found');
        }
        
        $draft = ArtifactDraft::where('uuid', $artifactUuid)
            ->where('organization_id', $orgId)
            ->firstOrFail();
            
        $sinceRevision = (int) $request->query('since_revision', 1);
        
        $changes = ArtifactDraftChange::where('artifact_draft_id', $draft->id)
            ->where('revision', '>', $sinceRevision)
            ->orderBy('revision', 'asc')
            ->get()
            ->map(function ($change) {
                return [
                    'revision' => $change->revision,
                    'type' => $change->type,
                    'sheet_uuid' => $change->sheet_uuid,
                    'range' => $change->range,
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'artifact_uuid' => $draft->uuid,
                'from_revision' => $sinceRevision,
                'to_revision' => $draft->revision,
                'changes' => $changes,
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Domain\Reports\Models\AsyncReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function show(Request $request, string $uuid): JsonResponse
    {
        $organizationId = session('active_organization_id') ?? $request->user()->currentOrganization?->id;
        
        $report = AsyncReport::where('organization_id', $organizationId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'report_uuid' => $report->uuid,
                'status' => $report->status,
                'progress' => $report->progress,
                'result' => $report->result,
                'error_message' => $report->error_message,
                'started_at' => $report->started_at,
                'completed_at' => $report->completed_at,
            ]
        ]);
    }
}

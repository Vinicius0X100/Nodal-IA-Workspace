<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organizations\Models\CompanyVerification;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationRejectedEmail;

class CompanyVerificationApiController extends Controller
{
    /**
     * List all pending verifications.
     */
    public function pending(Request $request)
    {
        $verifications = CompanyVerification::with('organization')
            ->where('verification_status', 'under_review')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $verifications->map(function ($v) {
                return [
                    'uuid' => $v->uuid,
                    'organization_uuid' => $v->organization->uuid,
                    'organization_name' => $v->organization->name,
                    'document_type' => $v->document_type,
                    'submitted_at' => $v->submitted_at,
                ];
            })
        ]);
    }

    /**
     * Get details of a specific verification, including document download URL.
     */
    public function show($uuid)
    {
        $verification = CompanyVerification::findByUuidOrFail($uuid);
        $verification->load('organization');

        // URL segura que passa pela mesma API para garantir que o SaaS envie o Header X-System-Api-Key
        $documentUrl = null;
        if ($verification->document_path) {
            $documentUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'api.verifications.document', 
                now()->addMinutes(60), 
                ['uuid' => $verification->uuid]
            );
        }

        return response()->json([
            'uuid' => $verification->uuid,
            'organization_uuid' => $verification->organization->uuid,
            'organization_name' => $verification->organization->name,
            'company_name' => $verification->company_name,
            'trade_name' => $verification->trade_name,
            'cnpj' => $verification->cnpj,
            'website' => $verification->website,
            'linkedin' => $verification->linkedin,
            'responsible_name' => $verification->responsible_name,
            'responsible_position' => $verification->responsible_position,
            'corporate_email' => $verification->corporate_email,
            'phone' => $verification->phone,
            'document_type' => $verification->document_type,
            'document_url' => $documentUrl,
            'status' => $verification->verification_status,
            'notes' => $verification->review_notes,
            'submitted_at' => $verification->submitted_at,
            'reviewed_at' => $verification->verified_at,
        ]);
    }

    /**
     * Download a secure document from the private storage.
     */
    public function downloadDocument($uuid)
    {
        $verification = CompanyVerification::findByUuidOrFail($uuid);

        if (!$verification->document_path || !Storage::disk('private')->exists($verification->document_path)) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $originalName = $verification->document_original_name ?? 'document.pdf';
        
        return Storage::disk('private')->download($verification->document_path, $originalName);
    }

    /**
     * Approve a verification.
     */
    public function approve(Request $request, $uuid)
    {
        $verification = CompanyVerification::findByUuidOrFail($uuid);

        if ($verification->verification_status !== 'under_review') {
            return response()->json(['message' => 'Verification is not pending.'], 400);
        }

        $verification->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'review_notes' => $request->input('notes'),
        ]);

        return response()->json([
            'message' => 'Verification approved successfully.',
            'uuid' => $verification->uuid,
        ]);
    }

    /**
     * Reject a verification.
     */
    public function reject(Request $request, $uuid)
    {
        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        $verification = CompanyVerification::findByUuidOrFail($uuid);
        $verification->load('organization.users');

        if ($verification->verification_status !== 'under_review') {
            return response()->json(['message' => 'Verification is not pending.'], 400);
        }

        $reason = $request->input('reason');

        $verification->update([
            'verification_status' => 'rejected',
            'verified_at' => now(),
            'review_notes' => $reason,
        ]);

        // Send email to all owners/admins of the organization
        $owners = $verification->organization->users()->wherePivot('is_owner', true)->get();
        
        foreach ($owners as $owner) {
            Mail::to($owner->email)->queue(
                new VerificationRejectedEmail($reason, $verification->organization->name)
            );
        }

        return response()->json([
            'message' => 'Verification rejected successfully.',
            'uuid' => $verification->uuid,
        ]);
    }
}

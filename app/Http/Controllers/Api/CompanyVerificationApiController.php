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
        $verification = CompanyVerification::with('organization')->findByUuidOrFail($uuid);

        // Generate a temporary signed URL if using S3, or a full URL if local
        $documentUrl = null;
        if ($verification->document_path) {
            $disk = Storage::disk(config('filesystems.default'));
            if (config('filesystems.default') === 's3') {
                $documentUrl = $disk->temporaryUrl($verification->document_path, now()->addMinutes(60));
            } else {
                // If local, we return the direct url (assuming it's symlinked to public)
                $documentUrl = url(Storage::url($verification->document_path));
            }
        }

        return response()->json([
            'uuid' => $verification->uuid,
            'organization_uuid' => $verification->organization->uuid,
            'organization_name' => $verification->organization->name,
            'document_type' => $verification->document_type,
            'document_url' => $documentUrl,
            'status' => $verification->verification_status,
            'notes' => $verification->review_notes,
            'submitted_at' => $verification->submitted_at,
            'reviewed_at' => $verification->verified_at,
        ]);
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

        $verification = CompanyVerification::with('organization.users')->findByUuidOrFail($uuid);

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

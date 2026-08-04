<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DeleteOrganizationAction
{
    public function execute(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            // Delete logo from storage if exists
            if ($organization->logo) {
                Storage::disk('public')->delete($organization->logo);
            }

            // Also delete any avatars of users that exclusively belong to this organization?
            // Actually, for a multi-tenant SaaS, deleting the organization deletes its pivot (organization_users).
            // But if a user only belongs to this organization, we might want to delete them too.
            // For now, let's just delete the organization (and its related records via cascades/soft-deletes).
            
            $organization->delete();
        });
    }
}

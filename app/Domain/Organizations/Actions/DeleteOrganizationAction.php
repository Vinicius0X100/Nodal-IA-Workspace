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

            // Exclui completamente todos os usuários associados à organização do banco de dados
            $users = $organization->users()->get();
            foreach ($users as $user) {
                // Delete avatar from storage if exists
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $user->delete();
            }
            
            $organization->delete();
        });
    }
}

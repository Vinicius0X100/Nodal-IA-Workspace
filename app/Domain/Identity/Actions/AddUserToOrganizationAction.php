<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Mail\UserAddedToOrganizationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AddUserToOrganizationAction
{
    public function execute(Organization $organization, array $data, array $roleIds = []): User
    {
        return DB::transaction(function () use ($organization, $data, $roleIds) {
            
            // Gera senha temporária obrigatória
            $temporaryPassword = Str::password(12);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'position' => $data['position'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($temporaryPassword),
            ]);

            // Vincula à organização
            $organization->users()->attach($user->id, [
                'is_owner' => false,
                'joined_at' => now(),
            ]);

            // Associa as roles (Grupos) selecionados
            if (!empty($roleIds)) {
                $user->roles()->syncWithPivotValues($roleIds, ['organization_id' => $organization->id]);
            }

            // Envia o E-mail com a senha temporária
            Mail::to($user->email)->send(new UserAddedToOrganizationMail(
                user: $user,
                organization: $organization,
                temporaryPassword: $temporaryPassword
            ));

            return $user;
        });
    }
}

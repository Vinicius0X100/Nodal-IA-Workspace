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
            
            // 1. Verifica se usuário já existe globalmente no Nodal
            $user = User::where('email', $data['email'])->first();
            $isNewUser = !$user;
            $temporaryPassword = null;

            if ($isNewUser) {
                // Gera senha temporária somente para usuários novos
                $temporaryPassword = Str::password(12);

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'position' => $data['position'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make($temporaryPassword),
                ]);
            }

            // 2. Vincula à organização se ainda não estiver vinculado
            if (!$organization->hasMember($user)) {
                $organization->users()->attach($user->id, [
                    'is_owner' => false,
                    'joined_at' => now(),
                ]);
            }

            // 3. Associa as roles (Grupos) selecionados
            if (!empty($roleIds)) {
                $user->roles()->syncWithPivotValues($roleIds, ['organization_id' => $organization->id]);
            }

            // 4. Envia o E-mail sempre que for adicionado à organização
            // Se já era usuário do sistema, temporaryPassword vai como null e a blade lida com isso.
            Mail::to($user->email)->send(new UserAddedToOrganizationMail(
                user: $user,
                organization: $organization,
                temporaryPassword: $temporaryPassword
            ));

            return $user;
        });
    }
}

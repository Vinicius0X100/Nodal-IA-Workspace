<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateUserAction
{
    public function execute(User $user, Organization $organization, array $data, array $roleIds = [], ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $organization, $data, $roleIds, $avatar) {
            
            // 1. Atualizar dados básicos
            $user->name = $data['name'] ?? $user->name;
            $user->position = $data['position'] ?? $user->position;
            $user->phone = $data['phone'] ?? $user->phone;
            
            // 2. Upload de Avatar
            if ($avatar) {
                // Se já tinha avatar, remove o antigo
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                
                // Salva a nova imagem no disco public/avatars
                $path = $avatar->store('avatars', 'public');
                $user->avatar = $path;
            }
            
            $user->save();

            // 3. Atualizar Grupos (Roles) na organização específica
            // O syncWithPivotValues vai sobrescrever as roles apenas dessa org.
            // Para não apagar roles de outras organizações, precisamos ter cuidado.
            // O ideal é apagar todas as roles do usuário que pertencem a essa org e depois anexar as novas.
            
            $user->roles()->wherePivot('organization_id', $organization->id)->detach();
            
            if (!empty($roleIds)) {
                $user->roles()->attach(
                    collect($roleIds)->mapWithKeys(function ($roleId) use ($organization) {
                        return [$roleId => ['organization_id' => $organization->id]];
                    })->toArray()
                );
            }

            return $user;
        });
    }
}

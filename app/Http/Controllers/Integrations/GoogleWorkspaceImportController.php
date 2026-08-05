<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Roles\Models\Role;
use App\Http\Controllers\Controller;
use App\Mail\GoogleWorkspaceUserImported;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GoogleWorkspaceImportController extends Controller
{
    /**
     * Retorna as listas de usuários e grupos para o wizard de importação no frontend.
     */
    public function preview(Request $request, $integrationId)
    {
        $organizationId = session('active_organization_id');
        
        $integration = Integration::where('id', $integrationId)
            ->where('organization_id', $organizationId)
            ->where('provider', 'google_workspace')
            ->with('organizationData')
            ->firstOrFail();

        $orgData = $integration->organizationData;

        if (!$orgData || !$orgData->organization_json) {
            return response()->json(['error' => 'Nenhum dado sincronizado encontrado.'], 404);
        }

        $json = $orgData->organization_json;

        // Formatar usuários
        $users = [];
        if (isset($json['users']['users'])) {
            foreach ($json['users']['users'] as $googleUser) {
                $users[] = [
                    'id' => $googleUser['id'],
                    'primaryEmail' => $googleUser['primaryEmail'],
                    'name' => $googleUser['name']['fullName'] ?? $googleUser['primaryEmail'],
                    'isAdmin' => $googleUser['isAdmin'] ?? false,
                    'suspended' => $googleUser['suspended'] ?? false,
                ];
            }
        }

        // Formatar grupos
        $groups = [];
        if (isset($json['groups']['groups'])) {
            foreach ($json['groups']['groups'] as $googleGroup) {
                $groups[] = [
                    'id' => $googleGroup['id'],
                    'email' => $googleGroup['email'],
                    'name' => $googleGroup['name'] ?? $googleGroup['email'],
                    'description' => $googleGroup['description'] ?? '',
                ];
            }
        }

        return response()->json([
            'users' => $users,
            'groups' => $groups,
        ]);
    }

    /**
     * Executa a importação dos usuários e grupos selecionados.
     */
    public function import(Request $request, $integrationId)
    {
        $request->validate([
            'users' => 'array',
            'users.*' => 'string', // IDs do google
            'groups' => 'array',
            'groups.*' => 'string', // IDs do google
        ]);

        $organizationId = session('active_organization_id');
        $organization = Organization::findOrFail($organizationId);
        
        $integration = Integration::where('id', $integrationId)
            ->where('organization_id', $organizationId)
            ->where('provider', 'google_workspace')
            ->with('organizationData')
            ->firstOrFail();

        $orgData = $integration->organizationData;
        if (!$orgData || !$orgData->organization_json) {
            return back()->with('error', 'Nenhum dado sincronizado encontrado.');
        }

        $json = $orgData->organization_json;
        $selectedUsers = $request->input('users', []);
        $selectedGroups = $request->input('groups', []);

        $importedUsersCount = 0;
        $importedGroupsCount = 0;

        // 1. Importar Grupos como Roles
        if (isset($json['groups']['groups'])) {
            foreach ($json['groups']['groups'] as $googleGroup) {
                if (in_array($googleGroup['id'], $selectedGroups)) {
                    $slug = Str::slug($googleGroup['email']);
                    
                    Role::firstOrCreate(
                        [
                            'organization_id' => $organizationId,
                            'slug' => $slug,
                        ],
                        [
                            'name' => $googleGroup['name'] ?? $googleGroup['email'],
                            'description' => $googleGroup['description'] ?? 'Importado do Google Workspace',
                            'is_system' => false,
                        ]
                    );
                    $importedGroupsCount++;
                }
            }
        }

        // 2. Importar Usuários
        if (isset($json['users']['users'])) {
            foreach ($json['users']['users'] as $googleUser) {
                if (in_array($googleUser['id'], $selectedUsers)) {
                    $email = $googleUser['primaryEmail'];
                    $name = $googleUser['name']['fullName'] ?? $email;
                    
                    $existingUser = User::where('email', $email)->first();
                    $isNewUser = false;
                    $tempPassword = '';

                    if (!$existingUser) {
                        $isNewUser = true;
                        $tempPassword = Str::random(12);
                        
                        $existingUser = User::create([
                            'email' => $email,
                            'name' => $name,
                            'password' => Hash::make($tempPassword),
                            'email_verified_at' => now(), // Assume verificado por vir do Workspace
                            'status' => ($googleUser['suspended'] ?? false) ? 'inactive' : 'active',
                        ]);
                    }

                    // Vincular à organização
                    $existingUser->organizations()->syncWithoutDetaching([
                        $organizationId => ['joined_at' => now()]
                    ]);

                    // Se for novo, enviar email
                    if ($isNewUser) {
                        Mail::to($existingUser->email)->send(
                            new GoogleWorkspaceUserImported($existingUser, $tempPassword, $organization->name)
                        );
                    }

                    $importedUsersCount++;
                }
            }
        }

        // Log
        IntegrationLog::create([
            'integration_id' => $integration->id,
            'user_id' => auth()->id(),
            'event' => 'directory_import',
            'status' => 'success',
            'message' => "Importação concluída: {$importedUsersCount} usuários e {$importedGroupsCount} grupos.",
        ]);

        return redirect()->back()->with('success', "Importação concluída com sucesso! {$importedUsersCount} usuários e {$importedGroupsCount} grupos de acesso (roles) foram criados.");
    }
}

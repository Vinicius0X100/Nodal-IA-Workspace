<?php

namespace App\Http\Controllers\Directory;

use App\Domain\Identity\Actions\AddUserToOrganizationAction;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Roles\Actions\CreateRoleAction;
use App\Domain\Roles\Actions\SyncRolePermissionsAction;
use App\Domain\Roles\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = session('active_organization_id');
        $organization = Organization::find($organizationId);

        // Aba 1: Usuários com seus Roles
        $users = $organization->users()->with(['roles' => function ($query) use ($organizationId) {
            $query->where('user_roles.organization_id', $organizationId);
        }])->get();

        // Aba 2: Roles (Grupos) e Matrix de Permissões
        $roles = $organization->roles()->with('permissions')->get();
        
        // Todas as permissões agrupadas para montar a matriz de Checkboxes
        $permissionsGrouped = Permission::all()->groupBy('group');

        return Inertia::render('Directory/Index', [
            'users' => $users,
            'roles' => $roles,
            'permissionsGrouped' => $permissionsGrouped,
        ]);
    }

    public function addUser(Request $request, AddUserToOrganizationAction $action)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_ids' => ['array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $organizationId = session('active_organization_id');
        $organization = Organization::find($organizationId);

        $action->execute($organization, $validated, $validated['role_ids'] ?? []);

        return back()->with('success', 'Usuário adicionado com sucesso. Ele receberá um e-mail em breve.');
    }

    public function createRole(Request $request, CreateRoleAction $action)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $organizationId = session('active_organization_id');
        $organization = Organization::find($organizationId);

        $action->execute($organization, $validated);

        return back()->with('success', 'Grupo criado com sucesso.');
    }

    public function syncPermissions(Request $request, Role $role, SyncRolePermissionsAction $action)
    {
        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        // Verifica se a role pertence à organização atual
        if ($role->organization_id !== session('active_organization_id')) {
            abort(403);
        }

        $action->execute($role, $validated['permission_ids'] ?? []);

        return back()->with('success', 'Permissões salvas com sucesso.');
    }
}

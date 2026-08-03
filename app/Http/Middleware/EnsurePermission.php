<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $organizationId = session('active_organization_id');

        if (!$user || !$organizationId) {
            abort(403, 'Acesso negado.');
        }

        // Busca as roles do usuário na organização atual
        $roles = $user->roles()->wherePivot('organization_id', $organizationId)->with('permissions')->get();

        $hasPermission = false;
        foreach ($roles as $role) {
            if ($role->permissions->contains('slug', $permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            abort(403, "Você não tem permissão para acessar este recurso ({$permission}).");
        }

        return $next($request);
    }
}

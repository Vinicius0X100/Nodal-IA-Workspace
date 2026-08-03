<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Se houver uma organização selecionada na sessão, verifica acesso
        $organizationId = session('active_organization_id');

        if (!$organizationId) {
            // Se não tem, pega a primeira organização do usuário (se existir)
            $organization = $user->organizations()->first();
            
            if ($organization) {
                session(['active_organization_id' => $organization->id]);
                $organizationId = $organization->id;
            } else {
                // Usuário sem organização, idealmente redirecionar para tela de onboarding/criar org
                // Por enquanto, apenas abortamos ou redirecionamos para rota específica (a criar)
                abort(403, 'Você não pertence a nenhuma organização.');
            }
        } else {
            // Verifica se o usuário ainda pertence a esta organização
            $hasAccess = $user->organizations()->where('organizations.id', $organizationId)->exists();
            
            if (!$hasAccess) {
                session()->forget('active_organization_id');
                return redirect()->route('dashboard')->with('error', 'Acesso à organização revogado.');
            }
        }

        // Opcional: Compartilhar a organização atual globalmente na request
        $request->attributes->set('organization_id', $organizationId);

        return $next($request);
    }
}

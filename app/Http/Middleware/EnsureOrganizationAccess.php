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
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return redirect()->route('login')->with('error', 'Você não possui acesso a nenhuma organização. Contate o administrador.');
            }
        } else {
            // Verifica se o usuário ainda pertence a esta organização
            $hasAccess = $user->organizations()->where('organizations.id', $organizationId)->exists();
            
            if (!$hasAccess) {
                session()->forget('active_organization_id');
                // Se perder acesso, redireciona para tentar achar outra org ou deslogar na próxima checagem
                return redirect()->route('dashboard');
            }
        }

        // Opcional: Compartilhar a organização atual globalmente na request
        $request->attributes->set('organization_id', $organizationId);

        return $next($request);
    }
}

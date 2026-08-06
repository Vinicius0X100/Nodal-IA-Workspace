<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Domain\Organizations\Models\Organization;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = session('active_organization_id');
        $user = $request->user();
        
        $organization = Organization::withCount(['users', 'integrations'])
            ->with('verification')
            ->find($organizationId);

        $verification = $organization->verification;
        $verificationStatus = $verification?->verification_status ?? 'pending';

        // Auto-sync AI Tools (Retro-compatibility for orgs that connected before AI Tools existed)
        if ($organization->integrations()->where('status', 'connected')->exists()) {
            if (\App\Domain\AI\Models\AITool::where('organization_id', $organization->id)->count() === 0) {
                app(\App\Domain\AI\Services\AIToolRegistryService::class)->syncIntegrationTools($organization);
            }
        }

        // Alertas para o dashboard e navbar
        $alerts = [];

        if (!$user->hasVerifiedEmail()) {
            $alerts[] = [
                'type'    => 'email_unverified',
                'level'   => 'warning',
                'title'   => 'E-mail não verificado',
                'message' => 'Verifique seu e-mail para garantir a segurança da sua conta.',
            ];
        }

        if ($verificationStatus === 'pending') {
            $alerts[] = [
                'type'    => 'org_unverified',
                'level'   => 'info',
                'title'   => 'Empresa não verificada',
                'message' => 'Solicite a verificação da empresa para ter acesso a todos os recursos.',
            ];
        } elseif ($verificationStatus === 'rejected') {
            $alerts[] = [
                'type'    => 'org_rejected',
                'level'   => 'error',
                'title'   => 'Verificação reprovada',
                'message' => 'Sua solicitação foi reprovada. Revise os documentos e reenvie.',
            ];
        }

        return Inertia::render('Dashboard/Index', [
            'organization' => [
                'name'                => $organization->name,
                'logo'                => $organization->logo,
                'users_count'         => $organization->users_count,
                'integrations_count'  => $organization->integrations_count,
                'verification_status' => $verificationStatus,
            ],
            'integrations_status' => $organization->integrations()->pluck('status', 'provider'),
            'alerts'              => $alerts,
            'email_verified'      => !is_null($user->email_verified_at),
        ]);
    }
}

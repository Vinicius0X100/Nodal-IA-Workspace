<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar ?? null,
                    'email_verified_at' => $request->user()->email_verified_at,
                ] : null,
            ],
            'organization' => fn () => $request->user() 
                ? \App\Domain\Organizations\Models\Organization::with('verification')->find(session('active_organization_id'))
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info'    => fn () => $request->session()->get('info'),
            ],
            // Alertas globais para navbar/notificações
            'notifications' => fn () => $this->buildNotifications($request),
            'connected_integrations' => fn () => $request->user() && session('active_organization_id')
                ? \App\Domain\Integrations\Models\Integration::where('organization_id', session('active_organization_id'))
                        ->where('status', 'connected')
                        ->select('id', 'provider', 'display_name', 'status')
                        ->get()
                : [],
        ];
    }

    private function buildNotifications(Request $request): array
    {
        $user = $request->user();
        if (!$user) return [];

        $notifications = [];

        if (!$user->hasVerifiedEmail()) {
            $notifications[] = [
                'type'    => 'email_unverified',
                'level'   => 'warning',
                'title'   => 'E-mail não verificado',
                'message' => 'Verifique seu e-mail para garantir a segurança da sua conta.',
            ];
        }

        $orgId = session('active_organization_id');
        if ($orgId) {
            $org = \App\Domain\Organizations\Models\Organization::with('verification')->find($orgId);
            $status = $org?->verification?->verification_status ?? 'pending';

            if ($status === 'pending') {
                $notifications[] = [
                    'type'    => 'org_unverified',
                    'level'   => 'info',
                    'title'   => 'Empresa não verificada',
                    'message' => 'Solicite a verificação para ter acesso a todos os recursos.',
                ];
            } elseif ($status === 'rejected') {
                $notifications[] = [
                    'type'    => 'org_rejected',
                    'level'   => 'error',
                    'title'   => 'Verificação reprovada',
                    'message' => 'Sua solicitação foi reprovada. Revise os documentos e reenvie.',
                ];
            }
        }

        return $notifications;
    }
}


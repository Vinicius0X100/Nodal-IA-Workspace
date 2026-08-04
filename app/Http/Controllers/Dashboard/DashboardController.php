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
        
        $organization = Organization::withCount(['users', 'integrations'])
            ->find($organizationId);

        return Inertia::render('Dashboard/Index', [
            'organization' => [
                'name' => $organization->name,
                'logo' => $organization->logo,
                'users_count' => $organization->users_count,
                'integrations_count' => $organization->integrations_count,
            ],
            // Em uma fase futura, buscaremos os status reais das integrações
            'integrations_status' => $organization->integrations()->pluck('status', 'provider'),
        ]);
    }
}

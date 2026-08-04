<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organizations\Actions\UpdateOrganizationAction;
use App\Domain\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $organizationId = session('active_organization_id');
        $organization = Organization::find($organizationId);

        return Inertia::render('Settings/Index', [
            'organization' => $organization,
        ]);
    }

    public function update(Request $request, UpdateOrganizationAction $action)
    {
        // Apenas admins ou owners deveriam atualizar isso (EnsurePermission middleware resolve isso depois,
        // mas vamos fazer uma validação básica aqui caso não tenha o middleware na rota por enquanto).
        $organizationId = session('active_organization_id');
        $organization = Organization::find($organizationId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:10240'], // Max 10MB
        ]);

        $action->execute($organization, $validated, $request->file('logo'));

        return back()->with('success', 'Configurações atualizadas com sucesso.');
    }
}

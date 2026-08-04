<?php

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Actions\RegisterUserAction;
use App\Domain\Organizations\Actions\CreateOrganizationAction;
use App\Domain\Organizations\DTOs\CreateOrganizationData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProvisioningController extends Controller
{
    public function provisionOrganization(
        Request $request,
        RegisterUserAction $registerUserAction,
        CreateOrganizationAction $createOrganizationAction
    ) {
        $validated = $request->validate([
            'organization.name' => ['required', 'string', 'max:255'],
            'organization.slug' => ['nullable', 'string', 'max:255'],
            'organization.cnpj' => ['nullable', 'string', 'max:255'],
            'organization.address' => ['nullable', 'string', 'max:500'],
            'organization.industry' => ['nullable', 'string', 'max:255'],
            'owner.name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner.password' => ['required', 'string', 'min:8'],
        ]);

        // 1. Cria usuário dono
        $user = $registerUserAction->execute([
            'name' => $validated['owner']['name'],
            'email' => $validated['owner']['email'],
            'password' => $validated['owner']['password'],
        ]);

        // 2. Cria organização
        $orgData = new CreateOrganizationData(
            name: $validated['organization']['name'],
            slug: $validated['organization']['slug'] ?? Str::slug($validated['organization']['name']),
            cnpj: $validated['organization']['cnpj'] ?? null,
            address: $validated['organization']['address'] ?? null,
            industry: $validated['organization']['industry'] ?? null,
        );
        
        $organization = $createOrganizationAction->execute($orgData, $user);

        return response()->json([
            'message' => 'Organization provisioned successfully.',
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'login_url' => route('login'),
        ], 201);
    }

    public function updateOrganization(
        Request $request,
        $id,
        \App\Domain\Organizations\Actions\UpdateOrganizationAction $updateOrganizationAction
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::findOrFail($id);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'industry' => ['nullable', 'string', 'max:255'],
        ]);

        $organization = $updateOrganizationAction->execute($organization, $validated);

        return response()->json([
            'message' => 'Organization updated successfully.',
            'organization' => $organization,
        ]);
    }

    public function deleteOrganization(
        $id,
        \App\Domain\Organizations\Actions\DeleteOrganizationAction $deleteOrganizationAction
    ) {
        $organization = \App\Domain\Organizations\Models\Organization::findOrFail($id);
        
        $deleteOrganizationAction->execute($organization);

        return response()->json([
            'message' => 'Organization deleted successfully.',
        ]);
    }
}

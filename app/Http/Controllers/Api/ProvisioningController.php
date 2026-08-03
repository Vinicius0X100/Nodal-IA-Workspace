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
            slug: $validated['organization']['slug'] ?? Str::slug($validated['organization']['name'])
        );
        
        $organization = $createOrganizationAction->execute($orgData, $user);

        // TODO: Em uma fase futura, poderíamos disparar um e-mail de "Bem-vindo" caso desejado,
        // mas o usuário indicou que outro SaaS pode fazer isso, ou podemos usar o Brevo se necessário.

        return response()->json([
            'message' => 'Organization provisioned successfully.',
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'login_url' => route('login'),
        ], 201);
    }
}

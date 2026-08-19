<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\RegisterUserAction;
use App\Domain\Organizations\Actions\CreateOrganizationAction;
use App\Domain\Organizations\DTOs\CreateOrganizationData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            // Define a org ativa
            $user = Auth::user();
            $org = $user->organizations()->first();
            if ($org) {
                session(['active_organization_id' => $org->id]);
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'As credenciais fornecidas não correspondem aos nossos registros.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request, RegisterUserAction $registerUserAction, CreateOrganizationAction $createOrganizationAction)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:255'],
        ]);

        // 1. Cria usuário
        $user = $registerUserAction->execute([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // 2. Cria organização e associa
        $orgData = new CreateOrganizationData(
            name: $validated['organization_name'],
            slug: \Illuminate\Support\Str::slug($validated['organization_name'])
        );
        
        $organization = $createOrganizationAction->execute($orgData, $user);

        // 3. Autentica
        Auth::login($user);
        session(['active_organization_id' => $organization->id]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

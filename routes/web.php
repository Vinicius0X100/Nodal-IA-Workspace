<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Rotas web do Nodal, renderizadas via Inertia.js.
|--------------------------------------------------------------------------
*/

// Public
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    });

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'update'])->name('password.store');
});

// Authenticated
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Requer acesso à organização ativa
    Route::middleware(['org.access'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        
        // Diretório (RBAC)
        Route::get('/directory', [\App\Http\Controllers\Directory\DirectoryController::class, 'index'])->name('directory.index');
        Route::post('/directory/users', [\App\Http\Controllers\Directory\DirectoryController::class, 'addUser'])->name('directory.users.store');
        Route::post('/directory/users/{user}', [\App\Http\Controllers\Directory\DirectoryController::class, 'updateUser'])->name('directory.users.update');
        Route::delete('/directory/users/{user}', [\App\Http\Controllers\Directory\DirectoryController::class, 'removeUser'])->name('directory.users.destroy');
        Route::post('/directory/roles', [\App\Http\Controllers\Directory\DirectoryController::class, 'createRole'])->name('directory.roles.store');
        Route::post('/directory/roles/{role}/permissions', [\App\Http\Controllers\Directory\DirectoryController::class, 'syncPermissions'])->name('directory.roles.permissions.sync');
        
        // Perfil do Usuário
        Route::get('/profile', [\App\Http\Controllers\Profile\ProfileController::class, 'index'])->name('profile.index');
        Route::post('/profile', [\App\Http\Controllers\Profile\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password', [\App\Http\Controllers\Profile\ProfileController::class, 'updatePassword'])->name('profile.password');

        // Settings da Organização
        Route::get('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'update'])->name('settings.update');

        // As demais rotas serão implementadas nos respectivos controllers depois:
        // Integrations, Audit
    });
});

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
        Route::post('/directory/roles', [\App\Http\Controllers\Directory\DirectoryController::class, 'createRole'])->name('directory.roles.store');
        Route::post('/directory/roles/{role}/permissions', [\App\Http\Controllers\Directory\DirectoryController::class, 'syncPermissions'])->name('directory.roles.permissions.sync');
        
        // As demais rotas serão implementadas nos respectivos controllers depois:
        // Integrations, Settings, Audit
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Integrations\IntegrationsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Rotas web do Nodal, renderizadas via Inertia.js.
|--------------------------------------------------------------------------
*/

// Landing page (pública)
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Legal (Público)
Route::inertia('/termos-de-uso', 'Legal/Terms')->name('terms');
Route::inertia('/politica-de-privacidade', 'Legal/Privacy')->name('privacy');
Route::inertia('/exclusao-de-dados', 'Legal/DataDeletion')->name('data-deletion');

// Páginas institucionais (Público)
Route::inertia('/produto', 'Produto')->name('produto');
Route::inertia('/servicos', 'Servicos')->name('servicos');
Route::get('/contato', [\App\Http\Controllers\Contact\ContactController::class, 'show'])->name('contato');
Route::post('/contato', [\App\Http\Controllers\Contact\ContactController::class, 'send'])->name('contato.send');



// Public
Route::middleware('guest')->group(function () {

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

    // Verificação de E-mail
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'send'])->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])->name('verification.verify');

    // Requer acesso à organização ativa
    Route::middleware(['org.access'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        
        // Diretório (RBAC)
        Route::get('/directory', [\App\Http\Controllers\Directory\DirectoryController::class, 'index'])->name('directory.index');
        Route::get('/directory/permissions', [\App\Http\Controllers\Directory\DirectoryController::class, 'permissions'])->name('directory.permissions');
        Route::post('/directory/users', [\App\Http\Controllers\Directory\DirectoryController::class, 'addUser'])->name('directory.users.store');
        Route::post('/directory/users/{user}', [\App\Http\Controllers\Directory\DirectoryController::class, 'updateUser'])->name('directory.users.update');
        Route::delete('/directory/users/{user}', [\App\Http\Controllers\Directory\DirectoryController::class, 'removeUser'])->name('directory.users.destroy');
        Route::post('/directory/roles', [\App\Http\Controllers\Directory\DirectoryController::class, 'createRole'])->name('directory.roles.store');
        Route::delete('/directory/roles/{role}', [\App\Http\Controllers\Directory\DirectoryController::class, 'destroyRole'])->name('directory.roles.destroy');
        Route::post('/directory/roles/{role}/permissions', [\App\Http\Controllers\Directory\DirectoryController::class, 'syncPermissions'])->name('directory.roles.permissions.sync');
        
        // Integrações
        Route::prefix('integrations')->name('integrations.')->group(function () {
            Route::get('/', [IntegrationsController::class, 'index'])->name('index');
            Route::get('/google-workspace', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'googleWorkspace'])->name('google-workspace');
            Route::get('/google-workspace/users', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'googleWorkspaceUsers'])->name('google-workspace.users');
            Route::get('/google-workspace/groups', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'googleWorkspaceGroups'])->name('google-workspace.groups');
            Route::get('/meta', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'meta'])->name('meta');
            Route::get('/oauth/{provider}/redirect', [IntegrationsController::class, 'redirect'])->name('oauth.redirect');
            
            // OAuth Genérico (Connect, Callback, Disconnect, Config)
            Route::post('/meta/sync-assets', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'syncMetaAssets'])->name('meta.sync-assets');
            Route::post('/meta/insights', [\App\Http\Controllers\Integrations\IntegrationsController::class, 'metaInsights'])->name('meta.insights');
            Route::post('/{provider}/config', [IntegrationsController::class, 'saveConfig'])->name('config');
            Route::get('/{provider}/connect', [IntegrationsController::class, 'connect'])->name('connect');
            Route::post('/{provider}/disconnect', [IntegrationsController::class, 'disconnect'])->name('disconnect');
            
            // Organização Específico (Google Workspace)
            Route::post('/google-workspace/organization/{integrationId}/sync', [\App\Http\Controllers\Integrations\GoogleOrganizationController::class, 'sync'])->name('google-workspace.organization.sync');
            Route::get('/google-workspace/{integrationId}/import/preview', [\App\Http\Controllers\Integrations\GoogleWorkspaceImportController::class, 'preview'])->name('google-workspace.import.preview');
            Route::post('/google-workspace/{integrationId}/import/execute', [\App\Http\Controllers\Integrations\GoogleWorkspaceImportController::class, 'import'])->name('google-workspace.import.execute');
        });

        // O callback do OAuth fica fora do prefix 'integrations' por clareza (ex: /oauth/google/callback)
        Route::get('/oauth/{provider}/callback', [IntegrationsController::class, 'callback'])->name('oauth.callback');

        // Perfil do Usuário
        Route::get('/profile', [\App\Http\Controllers\Profile\ProfileController::class, 'index'])->name('profile.index');
        Route::post('/profile', [\App\Http\Controllers\Profile\ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/password', [\App\Http\Controllers\Profile\ProfileController::class, 'updatePassword'])->name('profile.password');

        // Settings da Organização
        Route::get('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\Settings\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/verification', [\App\Http\Controllers\Settings\SettingsController::class, 'storeVerification'])->name('settings.verification.store');

        // Resources
        Route::get('/resources', [\App\Http\Controllers\Resources\ResourceExplorerController::class, 'index'])->name('resources.index');
        Route::post('/resources/sync', [\App\Http\Controllers\Resources\ResourceExplorerController::class, 'sync'])->name('resources.sync');

        // Reports Genéricos (Async)
        Route::get('/api/reports/{uuid}', [\App\Http\Controllers\Reports\ReportsController::class, 'show'])->name('reports.show');

        // AI Assistant
        Route::prefix('assistant')->name('assistant.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AI\ConversationController::class, 'index'])->name('index');
            Route::post('/conversations', [\App\Http\Controllers\AI\ConversationController::class, 'store'])->name('store');
            Route::get('/{uuid}', [\App\Http\Controllers\AI\ConversationController::class, 'show'])->name('show');
            Route::patch('/{uuid}', [\App\Http\Controllers\AI\ConversationController::class, 'update'])->name('update');
            Route::delete('/{uuid}', [\App\Http\Controllers\AI\ConversationController::class, 'destroy'])->name('destroy');
            Route::post('/{uuid}/messages', [\App\Http\Controllers\AI\MessageController::class, 'store'])->name('messages.store');
        });

        // Downloads Temporários
        Route::get('/downloads/{uuid}', [\App\Http\Controllers\Downloads\DownloadController::class, 'show'])->name('downloads.show');

        // As demais rotas serão implementadas nos respectivos controllers depois:
        // Audit
    });
});

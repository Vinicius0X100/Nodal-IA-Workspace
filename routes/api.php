<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProvisioningController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aqui registramos as rotas de API do Nodal. A rota de provisionamento
| é protegida pela chave de sistema (Server-to-Server).
|
*/

Route::prefix('v1')->group(function () {
    Route::middleware('system.api')->group(function () {
        Route::post('/provision/organization', [ProvisioningController::class, 'provisionOrganization']);
        Route::put('/organizations/{uuid}', [ProvisioningController::class, 'updateOrganization']);
        Route::delete('/organizations/{uuid}', [ProvisioningController::class, 'deleteOrganization']);

        // Endpoints de Verificação de Empresas
        Route::get('/verifications/pending', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'pending']);
        Route::get('/verifications/{uuid}', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'show']);
        Route::post('/verifications/{uuid}/approve', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'approve']);
        Route::post('/verifications/{uuid}/reject', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'reject']);
    });

    // Rota pública e temporária de download (autenticada por assinatura na URL)
    Route::get('/verifications/{uuid}/document', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'downloadDocument'])
        ->middleware('signed')
        ->name('api.verifications.document');
});

// AI Gateway API (n8n)
Route::prefix('ai')->middleware('ai.gateway')->group(function () {
    Route::get('/organization', [\App\Domain\AI\Api\Controllers\AIOrganizationController::class, 'index']);
    Route::get('/users', [\App\Domain\AI\Api\Controllers\AIUsersController::class, 'index']);
    Route::get('/groups', [\App\Domain\AI\Api\Controllers\AIGroupsController::class, 'index']);
    Route::get('/resources/search', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'search']);
    Route::get('/resources/{uuid}', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'show']);
    Route::get('/tools', [\App\Domain\AI\Api\Controllers\AIToolsController::class, 'index']);
});

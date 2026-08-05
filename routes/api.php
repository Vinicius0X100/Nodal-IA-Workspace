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
        Route::get('/verifications/{uuid}/document', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'downloadDocument']);
        Route::post('/verifications/{uuid}/approve', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'approve']);
        Route::post('/verifications/{uuid}/reject', [\App\Http\Controllers\Api\CompanyVerificationApiController::class, 'reject']);
    });
});

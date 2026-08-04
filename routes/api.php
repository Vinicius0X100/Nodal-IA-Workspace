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
        Route::put('/organizations/{id}', [ProvisioningController::class, 'updateOrganization']);
        Route::delete('/organizations/{id}', [ProvisioningController::class, 'deleteOrganization']);
    });
});

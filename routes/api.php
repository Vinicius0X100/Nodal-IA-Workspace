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
    Route::get('/current-user', [\App\Domain\AI\Api\Controllers\AIUsersController::class, 'currentUser']);
    Route::get('/users', [\App\Domain\AI\Api\Controllers\AIUsersController::class, 'index']);
    Route::get('/users/{uuid}/groups', [\App\Domain\AI\Api\Controllers\AIUsersController::class, 'groups']);
    Route::get('/groups', [\App\Domain\AI\Api\Controllers\AIGroupsController::class, 'index']);
    Route::get('/groups/with-members', [\App\Domain\AI\Api\Controllers\AIGroupsController::class, 'withMembers']);
    Route::get('/groups/{uuid}/members', [\App\Domain\AI\Api\Controllers\AIGroupsController::class, 'members']);
    Route::post('/resources/folders', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'createFolder']);
    Route::post('/resources/upload', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'upload']);
    Route::get('/attachments/{uuid}/download', [\App\Domain\AI\Api\Controllers\AIAttachmentsController::class, 'download'])->whereUuid('uuid');
    Route::patch('/resources/{uuid}/rename', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'rename'])->whereUuid('uuid');
    Route::patch('/resources/{uuid}/move', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'move'])->whereUuid('uuid');
    Route::get('/resources/search', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'search']);
    Route::post('/resources/read-multiple', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'readMultiple']);
    Route::post('/resources/file', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'generateFileUrl']);
    Route::get('/resources/file/download/{temporary_uuid}', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'downloadTemporaryFile'])->withoutMiddleware('ai.gateway');
    Route::get('/resources/{uuid}', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'show'])->whereUuid('uuid');
    Route::get('/resources/{uuid}/content', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'content'])->whereUuid('uuid');
    Route::get('/resources/{uuid}/file', [\App\Domain\AI\Api\Controllers\AIResourcesController::class, 'file'])->whereUuid('uuid');
    Route::get('/tools', [\App\Domain\AI\Api\Controllers\AIToolsController::class, 'index']);

    // Calendar (read-only v1 + write v1)
    Route::get('/calendar/events', [\App\Domain\AI\Api\Controllers\AICalendarController::class, 'events']);
    Route::post('/calendar/events', [\App\Domain\AI\Api\Controllers\AICalendarController::class, 'createEvent']);
    Route::patch('/calendar/events/{eventId}', [\App\Domain\AI\Api\Controllers\AICalendarController::class, 'updateEvent']);
    Route::delete('/calendar/events/{eventId}', [\App\Domain\AI\Api\Controllers\AICalendarController::class, 'deleteEvent']);
    Route::post('/calendar/freebusy', [\App\Domain\AI\Api\Controllers\AICalendarController::class, 'freebusy']);

    // Gmail (read-only v1)
    Route::get('/gmail/messages', [\App\Domain\AI\Api\Controllers\AIGmailController::class, 'index']);
    Route::get('/gmail/messages/{messageId}', [\App\Domain\AI\Api\Controllers\AIGmailController::class, 'show']);
    Route::get('/gmail/messages/{messageId}/attachments/{attachmentId}', [\App\Domain\AI\Api\Controllers\AIGmailController::class, 'readAttachment']);
    Route::post('/gmail/messages/{messageId}/attachments/download-link', [\App\Domain\AI\Api\Controllers\AIGmailController::class, 'downloadLink']);
});

// Webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('/google-workspace', [\App\Http\Controllers\Webhooks\GoogleWorkspaceWebhookController::class, 'handle'])->name('webhooks.google_workspace');
});

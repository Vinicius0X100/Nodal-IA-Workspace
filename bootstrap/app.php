<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: [
        __DIR__.'/../app/Domain/Billing/Listeners',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'org.access'    => \App\Http\Middleware\EnsureOrganizationAccess::class,
            'permission'    => \App\Http\Middleware\EnsurePermission::class,
            'system.api'    => \App\Http\Middleware\VerifySystemApiKey::class,
            'ai.gateway'    => \App\Http\Middleware\AIGatewayMiddleware::class,
            'asaas.webhook' => \App\Http\Middleware\VerifyAsaasWebhookToken::class,
        ]);


        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Mapeamento de Exceptions de Domínio
        $exceptions->render(function (\App\Domain\Artifacts\Exceptions\ArtifactDraftBaseException $e, Request $request) {
            $code = 'INTERNAL_ERROR';
            $status = 500;
            
            if ($e instanceof \App\Domain\Artifacts\Exceptions\DraftRevisionConflictException) {
                $status = 409; $code = 'DRAFT_REVISION_CONFLICT';
            } elseif ($e instanceof \App\Domain\Artifacts\Exceptions\ArtifactDraftNotEditableException) {
                $status = 422; $code = 'DRAFT_NOT_EDITABLE';
            } elseif ($e instanceof \App\Domain\Artifacts\Exceptions\ArtifactDraftNotFoundException) {
                $status = 404; $code = 'ARTIFACT_DRAFT_NOT_FOUND';
            } elseif ($e instanceof \App\Domain\Artifacts\Exceptions\SpreadsheetSheetNotFoundException) {
                $status = 404; $code = 'SPREADSHEET_SHEET_NOT_FOUND';
            } elseif ($e instanceof \App\Domain\Artifacts\Exceptions\SpreadsheetDraftInvalidRangeException) {
                $status = 422; $code = 'INVALID_RANGE';
            } elseif ($e instanceof \App\Domain\Artifacts\Exceptions\SpreadsheetMutationTooLargeException) {
                $status = 413; $code = 'MUTATION_TOO_LARGE';
            }

            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $e->getMessage() ?: class_basename($e)
            ], $status);
        });

        // Renderiza erros HTTP como página Inertia (elimina comportamento de "modal")
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (
                ! $request->is('api/*')
                && in_array($status, [404, 403, 500, 503])
                && $request->header('X-Inertia')
            ) {
                file_put_contents(public_path('error.txt'), $exception->getMessage() . "\n" . $exception->getTraceAsString());
                return \Inertia\Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            if (
                ! $request->is('api/*')
                && in_array($status, [404, 403, 500, 503])
                && ! $request->header('X-Inertia')
                && ! $request->expectsJson()
            ) {
                return response()->view("errors.{$status}", ['status' => $status], $status);
            }

            return $response;
        });
    })->create();


<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
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
            'org.access' => \App\Http\Middleware\EnsureOrganizationAccess::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'system.api' => \App\Http\Middleware\VerifySystemApiKey::class,
            'ai.gateway' => \App\Http\Middleware\AIGatewayMiddleware::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Renderiza erros HTTP como página Inertia (elimina comportamento de "modal")
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (
                ! $request->is('api/*')
                && in_array($status, [404, 403, 500, 503])
                && $request->header('X-Inertia')
            ) {
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


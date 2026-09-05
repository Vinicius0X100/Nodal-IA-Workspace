<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAsaasWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.asaas.webhook_token');

        if (empty($expectedToken)) {
            abort(500, 'ASAAS_WEBHOOK_TOKEN is not configured on the server.');
        }

        $providedToken = $request->header('asaas-access-token');

        if (!$providedToken || !hash_equals($expectedToken, (string) $providedToken)) {
            return response()->json(['message' => 'Unauthorized. Invalid webhook token.'], 401);
        }

        return $next($request);
    }
}

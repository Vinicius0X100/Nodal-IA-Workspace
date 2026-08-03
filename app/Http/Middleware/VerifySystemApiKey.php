<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySystemApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.system.api_key') ?? env('SYSTEM_API_KEY');

        if (empty($apiKey)) {
            abort(500, 'SYSTEM_API_KEY is not configured on the server.');
        }

        $providedKey = $request->bearerToken();

        if (!hash_equals($apiKey, (string) $providedKey)) {
            return response()->json(['message' => 'Unauthorized. Invalid API Key.'], 401);
        }

        return $next($request);
    }
}

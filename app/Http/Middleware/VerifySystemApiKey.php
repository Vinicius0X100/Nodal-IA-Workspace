<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySystemApiKey
{
    /**
     * Valida requisições Server-to-Server.
     * Suporta escopos específicos (ex: 'integer') para aplicar o princípio do menor privilégio (least privilege).
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $providedKey = $request->header('X-System-Api-Key') ?? $request->bearerToken();

        if (empty($providedKey)) {
            return response()->json(['message' => 'Unauthorized. Missing API Key.'], 401);
        }

        // ── Escopo Dedicado: Integer ─────────────────────────────────────
        if ($scope === 'integer') {
            $integerKey = config('services.system.integer_api_key') ?: env('INTEGER_SYSTEM_API_KEY');
            $masterKey  = config('services.system.api_key') ?: env('SYSTEM_API_KEY');

            $valid = false;
            if (!empty($integerKey) && hash_equals($integerKey, (string) $providedKey)) {
                $valid = true;
            } elseif (empty($integerKey) && !empty($masterKey) && hash_equals($masterKey, (string) $providedKey)) {
                // Fallback seguro caso a chave dedicada do Integer ainda não tenha sido definida no .env
                $valid = true;
            }

            if (!$valid) {
                return response()->json(['message' => 'Unauthorized. Invalid API Key for Integer scope.'], 401);
            }

            return $next($request);
        }

        // ── Escopo Padrão do Sistema (Provisioning, Verificações) ─────────
        $masterKey = config('services.system.api_key') ?: env('SYSTEM_API_KEY');

        if (empty($masterKey)) {
            abort(500, 'SYSTEM_API_KEY is not configured on the server.');
        }

        if (!hash_equals($masterKey, (string) $providedKey)) {
            return response()->json(['message' => 'Unauthorized. Invalid API Key.'], 401);
        }

        return $next($request);
    }
}

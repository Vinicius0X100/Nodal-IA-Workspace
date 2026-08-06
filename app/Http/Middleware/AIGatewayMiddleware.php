<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AIGatewayMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $expectedToken = config('services.ai_gateway.token');

        if (empty($expectedToken) || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid AI Gateway Token'
            ], 401);
        }

        $organizationUuid = $request->header('X-Organization-UUID');

        if (empty($organizationUuid)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Organization-UUID header'
            ], 400);
        }

        $organization = Organization::where('uuid', $organizationUuid)->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        // Bind the organization to the container or request so controllers can use it
        $request->merge(['_active_organization' => $organization]);
        
        // Alternatively we can use Laravel's container:
        app()->instance(Organization::class, $organization);

        return $next($request);
    }
}

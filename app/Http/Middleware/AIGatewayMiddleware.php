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

        $userUuid = $request->header('X-User-UUID');

        if (empty($userUuid)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-User-UUID header'
            ], 400);
        }

        $user = \App\Domain\Identity\Models\User::where('uuid', $userUuid)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ], 404);
        }

        if (!$organization->hasMember($user)) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => 'User does not belong to this organization'
            ], 403);
        }

        // Bind the organization and user to the container or request so controllers can use it
        $request->merge([
            '_active_organization' => $organization,
            '_active_user' => $user,
        ]);
        
        // Alternatively we can use Laravel's container:
        app()->instance(Organization::class, $organization);
        app()->instance(\App\Domain\Identity\Models\User::class, $user);

        return $next($request);
    }
}

<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIResourceResource;
use App\Domain\AI\Api\Services\AIResourcesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIResourcesController
{
    public function __construct(
        private AIResourcesService $service,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function search(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            // ── Resolve Contexto de Acesso & Identidade ──────────────────
            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $request->query('provider', 'google_workspace')) // Ajuste provisório
                ->first();
                
            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.search',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $query = $request->query('q', '');
            $provider = $request->query('provider');
            $type = $request->query('type');
            $limit = (int) $request->query('limit', 50);

            $resources = $this->service->search($organization, $query, $provider, $type, $limit);

            // Filtragem de recursos (Segurança em Nível de Recurso)
            $resources = $resources->filter(function ($resource) use ($user, $organization) {
                return $this->authorizationService->canAccessResource($user, $organization, $resource);
            })->values();

            return response()->json([
                'success' => true,
                'data' => AIResourceResource::collection($resources),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching resources: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace') // Por enquanto apenas Google
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.read',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $resource = $this->service->findByUuid($organization, $uuid);

            if (!$resource) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar este recurso.");
            }

            return response()->json([
                'success' => true,
                'data' => new AIResourceResource($resource),
            ]);
        } catch (\App\Domain\Identities\Exceptions\ExternalIdentityRequiredException $e) {
            return response()->json([
                'success' => false,
                'code' => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching resource: ' . $e->getMessage()
            ], 500);
        }
    }

    public function content(Request $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.read',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $resource = $this->service->findByUuid($organization, $uuid);
            if (!$resource) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar o conteúdo deste recurso.");
            }
            
            // O serviço antigo usa auth()->id() que pode ser nulo se não houver Sanctum cookie.
            // Para AI API, o dono da ação é o $user->id
            $contentData = $this->service->getContent($organization, $uuid, $user->id, $accessContext->getResolvedIdentity());
            
            return response()->json([
                'success' => true,
                'data' => $contentData,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching resource content');
        }
    }

    public function file(Request $request, string $uuid)
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.read',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $resource = $this->service->findByUuid($organization, $uuid);
            if (!$resource) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found'
                ], 404);
            }

            if (!$this->authorizationService->canAccessResource($user, $organization, $resource)) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão para acessar o arquivo deste recurso.");
            }
            
            return $this->service->getFileStream($organization, $uuid, $user->id, $accessContext->getResolvedIdentity());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching resource file');
        }
    }

    private function handleException(\Exception $e, string $defaultMessage): JsonResponse
    {
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => $e->getMessage()
            ], 403);
        }

        if ($e instanceof \App\Domain\Identities\Exceptions\ExternalIdentityRequiredException) {
            return response()->json([
                'success' => false,
                'code' => 'EXTERNAL_IDENTITY_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        }

        if ($e instanceof \App\Domain\Identities\Exceptions\ProviderDelegationRequiredException) {
            return response()->json([
                'success' => false,
                'code' => 'PROVIDER_DELEGATION_REQUIRED',
                'message' => $e->getMessage()
            ], 403);
        }

        $errorCode = $e->getCode();
        $status = 500;
        $appCode = 'INTERNAL_ERROR';

        if (is_numeric($errorCode)) {
            $statusInt = (int)$errorCode;
            if ($statusInt >= 400 && $statusInt < 600) {
                $status = $statusInt;
            }
            if ($status === 404) {
                $appCode = 'RESOURCE_NOT_FOUND';
            } elseif ($status === 403) {
                $appCode = 'ACCESS_DENIED';
            } elseif ($status === 400) {
                $appCode = 'BAD_REQUEST';
            }
        } elseif (is_string($errorCode) && !empty($errorCode)) {
            $appCode = $errorCode;
            $status = 400; // default for unknown custom string codes

            if (in_array($appCode, ['EXTERNAL_IDENTITY_REQUIRED', 'ACCESS_DENIED', 'PROVIDER_DELEGATION_REQUIRED'])) {
                $status = 403;
            } elseif (str_ends_with($appCode, 'NOT_FOUND')) {
                $status = 404;
            }
        }

        return response()->json([
            'success' => false,
            'code' => $appCode,
            'message' => $defaultMessage . ': ' . $e->getMessage()
        ], $status);
    }
}

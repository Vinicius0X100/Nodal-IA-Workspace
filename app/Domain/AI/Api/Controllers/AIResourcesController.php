<?php

namespace App\Domain\AI\Api\Controllers;

use App\Domain\AI\Api\Resources\AIResourceResource;
use App\Domain\AI\Api\Services\AIResourcesService;
use App\Domain\Resources\Models\TemporaryResourceDownload;
use App\Http\Requests\AI\ReadMultipleResourcesRequest;
use App\Http\Requests\AI\ReadResourceFileRequest;
use App\Http\Requests\AI\CreateFolderRequest;
use App\Http\Requests\AI\MoveResourceRequest;
use App\Http\Requests\AI\RenameResourceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIResourcesController
{
    public function __construct(
        private AIResourcesService $service,
        private \App\Domain\Permissions\Services\AuthorizationService $authorizationService
    ) {}

    public function rename(RenameResourceRequest $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->where('is_enabled', true)
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.write',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $name = $request->input('name');

            $data = $this->service->rename($organization, $user, $accessContext, $uuid, $name);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error renaming resource');
        }
    }

    public function move(MoveResourceRequest $request, string $uuid): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->where('is_enabled', true)
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.write',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $destinationFolderUuid = $request->input('destination_folder_resource_uuid');

            $data = $this->service->move($organization, $user, $accessContext, $uuid, $destinationFolderUuid);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error moving resource');
        }
    }

    public function createFolder(CreateFolderRequest $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->where('is_enabled', true)
                ->first();

            $accessContext = $this->authorizationService->resolveAccessContext(
                $user,
                $organization,
                'resources.write',
                $integration,
                $integration ? $integration->provider : 'google_workspace'
            );

            $name = $request->input('name');
            $parentResourceUuid = $request->input('parent_resource_uuid');

            $data = $this->service->createFolder($organization, $user, $accessContext, $name, $parentResourceUuid);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error creating folder');
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            // ── Resolve Contexto de Acesso & Identidade ──────────────────
            $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $organization->id)
                ->where('provider', $request->query('provider', 'google_workspace'))
                ->where('status', 'connected')
                ->where('is_enabled', true)
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
                ->where('provider', 'google_workspace')
                ->where('status', 'connected')
                ->where('is_enabled', true)
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
                ->where('status', 'connected')
                ->where('is_enabled', true)
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

    public function generateFileUrl(ReadResourceFileRequest $request): JsonResponse
    {
        try {
            $organization = $request->get('_active_organization');
            $user = $request->get('_active_user');

            $resourceUuid = $request->input('resource_uuid');

            $result = $this->service->readResourceFile($resourceUuid, $organization, $user);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'code' => $result['code'],
                    'message' => 'Não foi possível gerar a URL.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error generating file url');
        }
    }

    public function downloadTemporaryFile(Request $request, string $temporaryUuid)
    {
        try {
            $temporaryDownload = TemporaryResourceDownload::where('uuid', $temporaryUuid)->first();

            if (!$temporaryDownload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de download inválido ou expirado.'
                ], 404);
            }

            if (now()->greaterThan($temporaryDownload->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Link de download expirado.'
                ], 403);
            }

            $organization = $temporaryDownload->organization;
            $user = $temporaryDownload->user;
            $resource = $temporaryDownload->integrationResource;
            $uuid = $resource->uuid;

            // O dono do link pode não ser o usuário ativo se n8n chamar direto sem Auth
            // Mas usamos o Identity e as credenciais do usuário que GEROU o link.
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
            
            return $this->service->getFileStream($organization, $uuid, $user->id, $accessContext->getResolvedIdentity());
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching temporary resource file');
        }
    }

    public function readMultiple(ReadMultipleResourcesRequest $request): JsonResponse
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

            $resourceUuids = $request->input('resource_uuids', []);

            $results = $this->service->readMultipleResources($resourceUuids, $organization, $user, $accessContext);

            return response()->json([
                'success' => true,
                'data' => [
                    'resources' => $results
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching multiple resources content');
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

        if ($e instanceof \App\Domain\Identities\Exceptions\IntegrationInactiveException) {
            return response()->json([
                'success' => false,
                'code' => 'PROVIDER_REAUTH_REQUIRED',
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

            if (in_array($appCode, ['EXTERNAL_IDENTITY_REQUIRED', 'ACCESS_DENIED', 'PROVIDER_DELEGATION_REQUIRED', 'PROVIDER_REAUTH_REQUIRED'])) {
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

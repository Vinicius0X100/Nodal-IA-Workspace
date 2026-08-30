<?php

namespace App\Domain\AI\Services;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\Permissions\Services\AuthorizationService;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AIArtifactNormalizer
{
    public function __construct(
        private AuthorizationService $authService
    ) {}

    public function normalize(array $artifacts, int $organizationId, User $user, ?string $conversationUuid = null): array
    {
        $valid = [];
        $organization = Organization::find($organizationId);

        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) continue;

            $status = $artifact['status'] ?? null;
            $type = $artifact['type'] ?? null;

            if ($status === 'draft') {
                $artifactUuid = $artifact['artifact_uuid'] ?? null;
                
                Log::info('[ARTIFACT_OBSERVABILITY] Received draft artifact', [
                    'type' => $type,
                    'artifact_uuid' => $artifactUuid,
                ]);

                if (!$artifactUuid || !Str::isUuid($artifactUuid)) {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded draft artifact', [
                        'artifact_uuid' => $artifactUuid,
                        'discard_reason' => 'INVALID_UUID',
                    ]);
                    continue;
                }

                // Locate ArtifactDraft
                $draft = ArtifactDraft::where('uuid', $artifactUuid)
                    ->where('organization_id', $organizationId)
                    ->first();

                if (!$draft) {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded draft artifact', [
                        'artifact_uuid' => $artifactUuid,
                        'discard_reason' => 'DRAFT_NOT_FOUND',
                    ]);
                    continue;
                }

                Log::info('[ARTIFACT_OBSERVABILITY] Draft artifact accepted', [
                    'artifact_uuid' => $artifactUuid,
                ]);

                $valid[] = [
                    'type' => $draft->type,
                    'status' => 'draft',
                    'artifact_uuid' => $draft->uuid,
                    'title' => $draft->title,
                ];

            } else {
                // Legacy / Resource handling
                $uuid = $artifact['resource_uuid'] ?? null;
                
                Log::info('[ARTIFACT_OBSERVABILITY] Received artifact', [
                    'type' => $type,
                    'resource_uuid' => $uuid,
                ]);
                
                if (!$uuid || !Str::isUuid($uuid)) {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'INVALID_UUID_OR_TYPE',
                    ]);
                    continue;
                }

                $resource = IntegrationResource::where('uuid', $uuid)
                    ->whereHas('integration', function ($query) use ($organizationId) {
                        $query->where('organization_id', $organizationId);
                    })
                    ->first();

                if (!$resource) {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'RESOURCE_NOT_FOUND_OR_TENANT_MISMATCH',
                    ]);
                    continue;
                }
                
                $providerString = $resource->provider instanceof \App\Domain\Resources\Enums\Provider ? $resource->provider->value : clone $resource->provider;
                if (!is_string($providerString)) {
                    $providerString = (string) $resource->provider;
                }
                
                $accessContext = $this->authService->resolveAccessContext($user, $organization, 'resources.read', $resource->integration, $providerString);
                
                $canAccess = $this->authService->canAccessResource($user, $organization, $resource, $accessContext);

                Log::info('[ARTIFACT_OBSERVABILITY] Authorization result', [
                    'canAccessResource' => $canAccess,
                    'resource_uuid' => $uuid,
                ]);

                if (!$canAccess) {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'ACCESS_DENIED',
                    ]);
                    continue;
                }
                
                $actualResourceType = $resource->resource_type instanceof \App\Domain\Resources\Enums\ResourceType ? $resource->resource_type->value : $resource->resource_type;
                Log::info('[ARTIFACT_OBSERVABILITY] Type validation', [
                    'received_type' => $type,
                    'actual_type' => $actualResourceType,
                ]);

                if ($actualResourceType !== 'spreadsheet') {
                    Log::warning('[ARTIFACT_OBSERVABILITY] Discarded artifact', [
                        'resource_uuid' => $uuid,
                        'discard_reason' => 'TYPE_MISMATCH',
                    ]);
                    continue;
                }

                Log::info('[ARTIFACT_OBSERVABILITY] Artifact accepted', [
                    'resource_uuid' => $uuid,
                ]);

                $valid[] = [
                    'type' => 'spreadsheet',
                    'status' => 'committed',
                    'resource_uuid' => $resource->uuid,
                    'title' => $resource->name,
                ];
            }
        }

        return $valid;
    }
}

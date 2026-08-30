<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Services\AIArtifactNormalizer;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIArtifactNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_legacy_resource_artifact()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-test']);
        $user = User::create([
            'organization_id' => $org->id,
            'name' => 'User',
            'email' => 'user1@test.com',
            'password' => 'password',
            'uuid' => (string) Str::uuid()
        ]);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'is_enabled' => true
        ]);
        
        $resource = IntegrationResource::create([
            'uuid' => (string) Str::uuid(),
            'integration_id' => $integration->id,
            'provider' => 'google_workspace',
            'external_id' => '123',
            'name' => 'Planilha Legado',
            'resource_type' => 'spreadsheet',
        ]);

        $artifactsPayload = [
            [
                'type' => 'spreadsheet',
                'resource_uuid' => $resource->uuid,
                'title' => 'Titulo que veio do bot',
            ]
        ];

        // Mock authorization
        $authService = \Mockery::mock(\App\Domain\Permissions\Services\AuthorizationService::class);
        $mockContext = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
        $authService->shouldReceive('resolveAccessContext')->andReturn($mockContext);
        $authService->shouldReceive('canAccessResource')->andReturn(true);

        $normalizer = new AIArtifactNormalizer($authService);
        $result = $normalizer->normalize($artifactsPayload, $org->id, $user);

        $this->assertCount(1, $result);
        $this->assertEquals('spreadsheet', $result[0]['type']);
        $this->assertEquals('committed', $result[0]['status']);
        $this->assertEquals($resource->uuid, $result[0]['resource_uuid']);
        $this->assertEquals('Planilha Legado', $result[0]['title']); // Authorized by backend title
    }

    public function test_it_normalizes_draft_artifact()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-test-2']);
        $user = User::create([
            'organization_id' => $org->id,
            'name' => 'User2',
            'email' => 'user2@test.com',
            'password' => 'password',
            'uuid' => (string) Str::uuid()
        ]);

        $draft = ArtifactDraft::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Rascunho Oficial Backend',
            'status' => 'draft',
            'revision' => 1
        ]);

        $artifactsPayload = [
            [
                'type' => 'spreadsheet',
                'status' => 'draft',
                'artifact_uuid' => $draft->uuid,
                'title' => 'Titulo Inventado pelo Bot',
            ]
        ];

        $authService = \Mockery::mock(\App\Domain\Permissions\Services\AuthorizationService::class);
        
        $normalizer = new AIArtifactNormalizer($authService);
        $result = $normalizer->normalize($artifactsPayload, $org->id, $user);

        $this->assertCount(1, $result);
        $this->assertEquals('spreadsheet', $result[0]['type']);
        $this->assertEquals('draft', $result[0]['status']);
        $this->assertEquals($draft->uuid, $result[0]['artifact_uuid']);
        $this->assertEquals('Rascunho Oficial Backend', $result[0]['title']); // Picks backend title
    }

    public function test_it_rejects_draft_from_another_tenant()
    {
        $org1 = Organization::create(['name' => 'Org1', 'slug' => 'org1']);
        $org2 = Organization::create(['name' => 'Org2', 'slug' => 'org2']);
        $user = User::create([
            'organization_id' => $org1->id,
            'name' => 'User3',
            'email' => 'user3@test.com',
            'password' => 'password',
            'uuid' => (string) Str::uuid()
        ]);

        $draft = ArtifactDraft::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $org2->id, // Belongs to Org2
            'type' => 'spreadsheet',
            'title' => 'Rascunho Vazado',
            'status' => 'draft',
            'revision' => 1
        ]);

        $artifactsPayload = [
            [
                'type' => 'spreadsheet',
                'status' => 'draft',
                'artifact_uuid' => $draft->uuid,
            ]
        ];

        $authService = \Mockery::mock(\App\Domain\Permissions\Services\AuthorizationService::class);
        $normalizer = new AIArtifactNormalizer($authService);
        
        // Passing org1 context
        $result = $normalizer->normalize($artifactsPayload, $org1->id, $user);

        $this->assertEmpty($result);
    }
}

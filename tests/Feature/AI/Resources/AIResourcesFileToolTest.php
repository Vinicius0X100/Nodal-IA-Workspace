<?php

namespace Tests\Feature\AI\Resources;

use App\Domain\AI\Api\Services\AIResourcesService;
use App\Domain\AI\Services\AIToolRegistryService;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Services\AuthorizationService;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Models\TemporaryResourceDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class AIResourcesFileToolTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $user;
    private Integration  $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name'   => 'Test Corp',
            'slug'   => 'test-corp',
            'active' => true,
        ]);

        $this->user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $this->integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider'        => 'google_workspace',
            'display_name'    => 'Google Workspace',
            'status'          => 'connected',
            'credentials'     => [],
        ]);
    }

    private function actAsAI()
    {
        config(['services.ai_gateway.token' => 'valid-ai-token']);
        return $this->withHeaders([
            'Authorization'         => 'Bearer valid-ai-token',
            'X-Organization-UUID'   => $this->organization->uuid,
            'X-User-UUID'           => $this->user->uuid,
        ]);
    }

    public function test_it_registers_google_read_resource_file_tool()
    {
        $registry = new AIToolRegistryService();
        $registry->syncIntegrationTools($this->organization);

        $this->assertDatabaseHas('ai_tools', [
            'organization_id' => $this->organization->id,
            'slug'            => 'google_read_resource_file',
            'tool_type'       => 'read',
        ]);
    }

    public function test_it_generates_temporary_file_url_successfully()
    {
        $resource = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'provider'       => 'google_workspace',
            'resource_type'  => 'document',
            'external_id'    => '12345',
            'name'           => 'Contrato.pdf',
            'mime_type'      => 'application/pdf',
            'size'           => 123456,
        ]);

        $mockAuthService = Mockery::mock(AuthorizationService::class);
        $mockAuthService->shouldReceive('canAccessResource')
            ->once()
            ->andReturn(true);

        $serviceMock = Mockery::mock(AIResourcesService::class, [
            $this->app->make(\App\Domain\Integrations\Services\IntegrationManager::class),
            $this->app->make(\App\Domain\Audit\Actions\LogAuditAction::class),
            $this->app->make(\App\Domain\Integrations\Services\GoogleTokenService::class),
            $mockAuthService,
        ])->makePartial();
        $serviceMock->shouldReceive('findByUuid')
            ->once()
            ->andReturn($resource);
            
        // We set the authorization service on the instance manually or via app container if needed.
        $this->app->instance(AuthorizationService::class, $mockAuthService);
        $this->app->instance(AIResourcesService::class, $serviceMock);

        $response = $this->actAsAI()->postJson('/api/ai/resources/file', [
            'resource_uuid' => $resource->uuid,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.resource_uuid', $resource->uuid)
                 ->assertJsonPath('data.filename', 'Contrato.pdf')
                 ->assertJsonPath('data.mime_type', 'application/pdf')
                 ->assertJsonPath('data.size', 123456);

        $this->assertStringContainsString('/api/ai/resources/file/download/', $response->json('data.file_url'));

        $this->assertDatabaseHas('temporary_resource_downloads', [
            'organization_id' => $this->organization->id,
            'user_id'         => $this->user->id,
            'integration_resource_id' => $resource->id,
        ]);
    }

    public function test_it_returns_access_denied_if_unauthorized()
    {
        $resource = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'provider'       => 'google_workspace',
            'resource_type'  => 'document',
            'external_id'    => '12345',
            'name'           => 'Secret.pdf',
        ]);

        $mockAuthService = Mockery::mock(AuthorizationService::class);
        $mockAuthService->shouldReceive('canAccessResource')
            ->once()
            ->andReturn(false);

        $this->app->instance(AuthorizationService::class, $mockAuthService);

        $response = $this->actAsAI()->postJson('/api/ai/resources/file', [
            'resource_uuid' => $resource->uuid,
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'code'    => 'ACCESS_DENIED',
                 ]);
    }

    public function test_it_returns_resource_not_found()
    {
        $response = $this->actAsAI()->postJson('/api/ai/resources/file', [
            'resource_uuid' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'code'    => 'RESOURCE_NOT_FOUND',
                 ]);
    }

    public function test_temporary_download_endpoint_validates_expiration()
    {
        $resource = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'provider'       => 'google_workspace',
            'resource_type'  => 'document',
            'external_id'    => '12345',
            'name'           => 'Contrato.pdf',
        ]);

        $download = TemporaryResourceDownload::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->user->id,
            'integration_resource_id' => $resource->id,
            'expires_at'      => now()->subMinute(), // Expirado
        ]);

        $response = $this->getJson('/api/ai/resources/file/download/' . $download->uuid);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Link de download expirado.',
                 ]);
    }

    public function test_temporary_download_endpoint_returns_404_for_invalid_uuid()
    {
        $response = $this->getJson('/api/ai/resources/file/download/' . \Illuminate\Support\Str::uuid()->toString());

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Link de download inválido ou expirado.',
                 ]);
    }
}

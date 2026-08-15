<?php

namespace Tests\Feature\AI\Resources;

use App\Domain\AI\Api\Services\AIResourcesService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;
use Mockery;

class AIResourcesReadMultipleTest extends TestCase
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
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'status' => 'connected',
            'access_token' => 'valid-token'
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            
            // By default, allow access to all resources. We will override this in specific tests if needed.
            // Using Mockery's passthru or just returning true for the default test cases.
            $mock->shouldReceive('canAccessResource')->andReturn(true)->byDefault();
        });
    }

    private function actAsAI()
    {
        config(['services.ai_gateway.token' => 'test-token']);
        return $this->withHeaders([
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID'         => $this->user->uuid,
            'Authorization'       => 'Bearer test-token'
        ]);
    }

    private function createResource(string $uuid): IntegrationResource
    {
        return IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'provider'       => 'google_workspace',
            'uuid'           => $uuid,
            'external_id'    => 'ext-' . $uuid,
            'name'           => 'File ' . $uuid,
            'mime_type'      => 'text/plain',
            'resource_type'  => 'document',
        ]);
    }

    public function test_can_read_multiple_authorized_resources_successfully()
    {
        $this->createResource('11111111-1111-1111-1111-111111111111');
        $this->createResource('22222222-2222-2222-2222-222222222222');

        $mock = Mockery::mock(AIResourcesService::class, [
            app(\App\Domain\Integrations\Services\IntegrationManager::class),
            app(\App\Domain\Audit\Actions\LogAuditAction::class),
            app(\App\Domain\Integrations\Services\GoogleTokenService::class),
            app(\App\Domain\Permissions\Services\AuthorizationService::class)
        ])->makePartial();
        
        $mock->shouldReceive('getContent')->andReturn(
                [
                    'uuid' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'File 11111111-1111-1111-1111-111111111111',
                    'mime_type' => 'text/plain',
                    'content_type' => 'text/plain',
                    'content' => 'Content 1',
                    'truncated' => false,
                ],
                [
                    'uuid' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'File 22222222-2222-2222-2222-222222222222',
                    'mime_type' => 'text/plain',
                    'content_type' => 'text/plain',
                    'content' => 'Content 2',
                    'truncated' => false,
                ]
            );
            
        $this->app->instance(AIResourcesService::class, $mock);

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => [
                '11111111-1111-1111-1111-111111111111',
                '22222222-2222-2222-2222-222222222222'
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resources.0.success', true)
            ->assertJsonPath('data.resources.0.resource_uuid', '11111111-1111-1111-1111-111111111111')
            ->assertJsonPath('data.resources.0.content', 'Content 1')
            ->assertJsonPath('data.resources.1.success', true)
            ->assertJsonPath('data.resources.1.resource_uuid', '22222222-2222-2222-2222-222222222222')
            ->assertJsonPath('data.resources.1.content', 'Content 2');
    }

    public function test_removes_duplicate_uuids_and_preserves_order()
    {
        $this->createResource('11111111-1111-1111-1111-111111111111');

        $mock = Mockery::mock(AIResourcesService::class, [
            app(\App\Domain\Integrations\Services\IntegrationManager::class),
            app(\App\Domain\Audit\Actions\LogAuditAction::class),
            app(\App\Domain\Integrations\Services\GoogleTokenService::class),
            app(\App\Domain\Permissions\Services\AuthorizationService::class)
        ])->makePartial();
        
        $mock->shouldReceive('getContent')->once()->andReturn([
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'name' => 'File 11111111-1111-1111-1111-111111111111',
                'mime_type' => 'text/plain',
                'content_type' => 'text/plain',
                'content' => 'Content 1',
                'truncated' => false,
            ]);
        $this->app->instance(AIResourcesService::class, $mock);

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => [
                '11111111-1111-1111-1111-111111111111',
                '11111111-1111-1111-1111-111111111111'
            ]
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.resources');
    }

    public function test_fails_validation_with_invalid_uuid()
    {
        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => ['not-a-uuid']
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resource_uuids.0']);
    }

    public function test_fails_validation_with_more_than_10_uuids()
    {
        $uuids = [];
        for ($i = 0; $i < 11; $i++) {
            $uuids[] = '11111111-1111-1111-1111-11111111111' . sprintf('%x', $i);
        }

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => $uuids
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resource_uuids']);
    }

    public function test_returns_structured_error_for_non_existent_resource()
    {
        $this->createResource('11111111-1111-1111-1111-111111111111');

        $mock = Mockery::mock(AIResourcesService::class, [
            app(\App\Domain\Integrations\Services\IntegrationManager::class),
            app(\App\Domain\Audit\Actions\LogAuditAction::class),
            app(\App\Domain\Integrations\Services\GoogleTokenService::class),
            app(\App\Domain\Permissions\Services\AuthorizationService::class)
        ])->makePartial();
        
        $mock->shouldReceive('getContent')->once()->andReturn([
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'name' => 'File 1',
                'mime_type' => 'text/plain',
                'content_type' => 'text/plain',
                'content' => 'Content 1',
                'truncated' => false,
            ]);
        $this->app->instance(AIResourcesService::class, $mock);

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => [
                '11111111-1111-1111-1111-111111111111',
                '99999999-9999-9999-9999-999999999999'
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.resources.0.success', true)
            ->assertJsonPath('data.resources.1.success', false)
            ->assertJsonPath('data.resources.1.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('data.resources.1.message', 'Recurso não encontrado.');
    }

    public function test_returns_structured_error_for_unauthorized_resource()
    {
        $this->createResource('11111111-1111-1111-1111-111111111111');

        // Override authorization mock to fail
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(false);
        });

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => [
                '11111111-1111-1111-1111-111111111111'
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.resources.0.success', false)
            ->assertJsonPath('data.resources.0.code', 'ACCESS_DENIED')
            ->assertJsonPath('data.resources.0.message', 'Você não possui permissão para acessar este recurso.');
    }

    public function test_provider_failure_on_one_resource_does_not_affect_others()
    {
        $this->createResource('11111111-1111-1111-1111-111111111111');
        $this->createResource('22222222-2222-2222-2222-222222222222');

        $mock = Mockery::mock(AIResourcesService::class, [
            app(\App\Domain\Integrations\Services\IntegrationManager::class),
            app(\App\Domain\Audit\Actions\LogAuditAction::class),
            app(\App\Domain\Integrations\Services\GoogleTokenService::class),
            app(\App\Domain\Permissions\Services\AuthorizationService::class)
        ])->makePartial();
        
        $mock->shouldReceive('getContent')
                ->with(Mockery::any(), '11111111-1111-1111-1111-111111111111', Mockery::any(), Mockery::any())
                ->once()
                ->andThrow(new Exception('Provider API Error: Google rate limit', 429));
            
            $mock->shouldReceive('getContent')
                ->with(Mockery::any(), '22222222-2222-2222-2222-222222222222', Mockery::any(), Mockery::any())
                ->once()
                ->andReturn([
                    'uuid' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'File 2',
                    'mime_type' => 'text/plain',
                    'content_type' => 'text/plain',
                    'content' => 'Content 2',
                    'truncated' => false,
                ]);
                
        $this->app->instance(AIResourcesService::class, $mock);

        $response = $this->actAsAI()->postJson('/api/ai/resources/read-multiple', [
            'resource_uuids' => [
                '11111111-1111-1111-1111-111111111111',
                '22222222-2222-2222-2222-222222222222'
            ]
        ]);

        $response->assertOk()
            ->assertJsonPath('data.resources.0.success', false)
            ->assertJsonPath('data.resources.0.code', '429')
            ->assertJsonPath('data.resources.1.success', true)
            ->assertJsonPath('data.resources.1.content', 'Content 2');
    }
}

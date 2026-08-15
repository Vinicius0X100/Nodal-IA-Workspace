<?php

namespace Tests\Feature\AI\Resources;

use App\Domain\AI\Api\Services\AIResourcesService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Identities\Exceptions\ExternalIdentityRequiredException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;

class AIResourcesContentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $user;

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

        Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'status' => 'connected',
            'access_token' => 'valid-token'
        ]);
        
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(true);
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

    public function test_can_read_resource_content_successfully()
    {
        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(new IntegrationResource());
            $mock->shouldReceive('getContent')->andReturn(['content' => 'Hello World']);
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'data' => ['content' => 'Hello World']]);
    }

    public function test_returns_404_when_resource_not_found()
    {
        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(null);
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(404);
        $response->assertJson(['success' => false, 'message' => 'Resource not found']);
    }

    public function test_returns_403_when_authorization_exception_thrown()
    {
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(false);
        });

        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(new IntegrationResource());
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'code' => 'ACCESS_DENIED']);
    }

    public function test_returns_403_when_external_identity_required_string_code()
    {
        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(new IntegrationResource());
            $mock->shouldReceive('getContent')->andThrow(new ExternalIdentityRequiredException("Missing external identity"));
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'code' => 'EXTERNAL_IDENTITY_REQUIRED']);
    }

    public function test_handles_string_exception_codes_gracefully()
    {
        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(new IntegrationResource());
            
            $exception = new Exception("Something went wrong");
            // Reflection to set string code which simulates custom exception
            $reflection = new \ReflectionClass($exception);
            $property = $reflection->getProperty('code');
            $property->setAccessible(true);
            $property->setValue($exception, 'CUSTOM_STRING_ERROR');

            $mock->shouldReceive('getContent')->andThrow($exception);
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(400); // Defaults to 400 for unknown string codes
        $response->assertJson(['success' => false, 'code' => 'CUSTOM_STRING_ERROR']);
    }

    public function test_handles_numeric_exception_codes_gracefully()
    {
        $this->mock(AIResourcesService::class, function ($mock) {
            $mock->shouldReceive('findByUuid')->andReturn(new IntegrationResource());
            $mock->shouldReceive('getContent')->andThrow(new Exception("Not acceptable", 406));
        });

        $response = $this->actAsAI()->getJson("/api/ai/resources/uuid-123/content");
        
        $response->assertStatus(406); 
    }
}

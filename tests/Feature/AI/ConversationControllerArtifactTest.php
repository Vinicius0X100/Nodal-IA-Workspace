<?php

namespace Tests\Feature\AI;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Contracts\AIProviderInterface;
use App\Domain\AI\Contracts\AIChatResult;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationControllerArtifactTest extends TestCase
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
            'access_token' => 'valid-token',
            'is_enabled' => true,
        ]);

        session(['active_organization_id' => $this->organization->id]);
    }

    private function mockProvider(string $content, array $artifacts = [])
    {
        $mock = \Mockery::mock(AIProviderInterface::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('chat')->andReturn(new AIChatResult($content, $artifacts));
        $this->app->instance(AIProviderInterface::class, $mock);
    }

    public function test_persists_valid_artifact()
    {
        $resource = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'spreadsheet',
            'external_id' => '123',
            'name' => 'Real Title From DB', // Mocking title
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);

        $this->mockProvider('Criei a planilha', [
            [
                'type' => 'spreadsheet',
                'resource_uuid' => $resource->uuid,
                'title' => 'Title Ignored from N8N', // Will be ignored
                'external_id' => 'abc-123-leak', // Should be stripped out by validateAndNormalizeArtifacts
            ]
        ]);

        // Mock auth to allow access
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

        $response = $this->actingAs($this->user)->post(route('assistant.store'), [
            'message' => 'Criar planilha'
        ]);

        $conversation = Conversation::first();
        $response->assertRedirect(route('assistant.show', $conversation->uuid));

        $assistantMessage = $conversation->messages()->where('role', 'assistant')->first();
        
        $artifacts = $assistantMessage->metadata_json['artifacts'] ?? [];
        $this->assertCount(1, $artifacts);
        
        $persistedArtifact = $artifacts[0];
        $this->assertEquals('spreadsheet', $persistedArtifact['type']);
        $this->assertEquals($resource->uuid, $persistedArtifact['resource_uuid']);
        $this->assertEquals('Real Title From DB', $persistedArtifact['title']);
        $this->assertArrayNotHasKey('external_id', $persistedArtifact);
    }

    public function test_discards_invalid_resource_uuid()
    {
        $this->mockProvider('Criei a planilha', [
            [
                'type' => 'spreadsheet',
                'resource_uuid' => 'non-existent-uuid',
                'title' => 'Invalid'
            ]
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

        $this->actingAs($this->user)->post(route('assistant.store'), [
            'message' => 'Criar planilha'
        ]);

        $assistantMessage = Conversation::first()->messages()->where('role', 'assistant')->first();
        
        $this->assertNull($assistantMessage->metadata_json);
    }

    public function test_discards_artifacts_from_another_tenant()
    {
        $otherOrg = Organization::create([
            'name' => 'Other',
            'slug' => 'other',
            'active' => true,
        ]);

        $otherIntegration = Integration::create([
            'organization_id' => $otherOrg->id,
            'provider' => 'google_workspace',
            'display_name' => 'GWS',
            'status' => 'connected',
            'access_token' => 't',
            'is_enabled' => true,
        ]);

        $otherResource = IntegrationResource::create([
            'integration_id' => $otherIntegration->id,
            'uuid' => (string) Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'spreadsheet',
            'external_id' => '123',
            'name' => 'Other Title',
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);

        $this->mockProvider('Criei a planilha', [
            [
                'type' => 'spreadsheet',
                'resource_uuid' => $otherResource->uuid,
                'title' => 'Other Title'
            ]
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

        $this->actingAs($this->user)->post(route('assistant.store'), [
            'message' => 'Criar planilha'
        ]);

        $assistantMessage = Conversation::first()->messages()->where('role', 'assistant')->first();
        
        // Deve ser ignorado porque não pertence ao tenant atual
        $this->assertNull($assistantMessage->metadata_json);
    }

    public function test_show_route_propagates_artifacts()
    {
        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'uuid' => (string) Str::uuid(),
            'title' => 'Test',
            'status' => 'active'
        ]);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Test content',
            'metadata_json' => [
                'artifacts' => [
                    [
                        'type' => 'spreadsheet',
                        'resource_uuid' => 'fake-uuid',
                        'title' => 'Fake'
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($this->user)->get(route('assistant.show', $conversation->uuid));
        
        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('messages', 1)
            ->where('messages.0.artifacts.0.type', 'spreadsheet')
            ->where('messages.0.artifacts.0.resource_uuid', 'fake-uuid')
        );
    }
}

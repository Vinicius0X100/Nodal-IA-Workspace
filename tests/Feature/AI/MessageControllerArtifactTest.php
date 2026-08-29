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
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class MessageControllerArtifactTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $user;
    private Integration  $integration;
    private Conversation $conversation;

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

        $this->conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'uuid' => (string) Str::uuid(),
            'title' => 'Test',
            'status' => 'active'
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

    public function test_message_store_with_artifacts_persists_correctly()
    {
        $resource = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'spreadsheet',
            'external_id' => '123',
            'name' => 'Teste Artifact Nodal',
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);

        $this->mockProvider('A planilha foi criada e preenchida com sucesso...', [
            [
                'type' => 'spreadsheet',
                'resource_uuid' => $resource->uuid,
                'title' => 'Teste Artifact Nodal',
            ]
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->user)->post(route('assistant.messages.store', $this->conversation->uuid), [
            'content' => 'Criar planilha Teste Artifact Nodal'
        ]);

        $response->assertRedirect();
        
        $assistantMessage = $this->conversation->messages()->where('role', 'assistant')->first();
        
        $this->assertNotNull($assistantMessage, "A mensagem do assistente não foi persistida.");
        
        $artifacts = $assistantMessage->metadata_json['artifacts'] ?? [];
        $this->assertCount(1, $artifacts);
        
        $persistedArtifact = $artifacts[0];
        $this->assertEquals('spreadsheet', $persistedArtifact['type']);
        $this->assertEquals($resource->uuid, $persistedArtifact['resource_uuid']);
    }
}

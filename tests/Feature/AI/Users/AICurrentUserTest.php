<?php

namespace Tests\Feature\AI\Users;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AICurrentUserTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->organization = clone Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization-' . Str::random(5),
            'active' => true,
        ]);
        
        $this->user = clone User::create([
            'name' => 'Test User',
            'email' => 'test-' . Str::random(5) . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->organizations()->attach($this->organization->id, ['joined_at' => now(), 'is_owner' => false]);
    }

    protected function withAIGatewayHeaders(User $user, Organization $organization)
    {
        config(['services.ai_gateway.token' => 'test-token']);
        return $this->withHeaders([
            'X-Organization-UUID' => $organization->uuid,
            'X-User-UUID' => $user->uuid,
            'Authorization' => 'Bearer test-token',
        ]);
    }

    public function test_user_can_read_own_identity_without_extra_capabilities()
    {
        $response = $this->withAIGatewayHeaders($this->user, $this->organization)
            ->getJson('/api/ai/current-user');

        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.uuid', $this->user->uuid)
            ->assertJsonPath('data.user.name', $this->user->name)
            ->assertJsonPath('data.user.email', $this->user->email)
            ->assertJsonPath('data.organization.uuid', $this->organization->uuid)
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.external_identities', []);
    }

    public function test_owner_receives_is_owner_flag_true()
    {
        $owner = clone User::create([
            'name' => 'Owner',
            'email' => 'owner-' . Str::random(5) . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $owner->organizations()->attach($this->organization->id, ['joined_at' => now(), 'is_owner' => true]);

        $response = $this->withAIGatewayHeaders($owner, $this->organization)
            ->getJson('/api/ai/current-user');

        $response->assertStatus(200)
            ->assertJsonPath('data.is_owner', true);
    }

    public function test_user_with_single_external_identity()
    {
        $integration = \App\Domain\Integrations\Models\Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
        ]);

        ExternalIdentity::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'integration_id' => $integration->id,
            'external_id' => Str::random(10),
            'provider' => 'google_workspace',
            'primary_email' => 'user@google.com',
            'display_name' => 'Test',
            'status' => 'linked',
            'linked_at' => now(),
            'last_synced_at' => now(),
        ]);

        $response = $this->withAIGatewayHeaders($this->user, $this->organization)
            ->getJson('/api/ai/current-user');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.external_identities')
            ->assertJsonPath('data.external_identities.0.provider', 'google_workspace')
            ->assertJsonPath('data.external_identities.0.primary_email', 'user@google.com');
    }

    public function test_user_with_multiple_external_identities()
    {
        $integrationGoogle = \App\Domain\Integrations\Models\Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
        ]);

        $integrationMs = \App\Domain\Integrations\Models\Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'microsoft_365',
            'display_name' => 'Microsoft',
            'status' => 'connected',
        ]);

        ExternalIdentity::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'integration_id' => $integrationGoogle->id,
            'external_id' => Str::random(10),
            'provider' => 'google_workspace',
            'primary_email' => 'user@google.com',
            'display_name' => 'Test',
            'status' => 'linked',
            'linked_at' => now(),
            'last_synced_at' => now(),
        ]);

        ExternalIdentity::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'integration_id' => $integrationMs->id,
            'external_id' => Str::random(10),
            'provider' => 'microsoft_365',
            'primary_email' => 'user@microsoft.com',
            'display_name' => 'Test MS',
            'status' => 'linked',
            'linked_at' => now(),
            'last_synced_at' => now(),
        ]);

        $response = $this->withAIGatewayHeaders($this->user, $this->organization)
            ->getJson('/api/ai/current-user');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.external_identities');
    }

    public function test_never_returns_internal_ids_or_secrets()
    {
        $response = $this->withAIGatewayHeaders($this->user, $this->organization)
            ->getJson('/api/ai/current-user');

        $json = $response->json('data');

        $this->assertArrayNotHasKey('id', $json['user']);
        $this->assertArrayNotHasKey('password', $json['user']);
        $this->assertArrayNotHasKey('id', $json['organization']);
    }

    public function test_does_not_accept_arbitrary_user_in_url()
    {
        // Enviar id no corpo não deve afetar a resposta (vai retornar o usuário autenticado)
        $response = $this->withAIGatewayHeaders($this->user, $this->organization)
            ->json('GET', '/api/ai/current-user', ['user_uuid' => Str::uuid()->toString()]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.uuid', $this->user->uuid); // Sempre retorna o usuário logado
    }
}

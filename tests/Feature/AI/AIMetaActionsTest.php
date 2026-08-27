<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Models\AIAction;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Permissions\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIMetaActionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $ownerA;
    private User $memberA;
    private User $ownerB;
    private Integration $integrationA;
    private Integration $integrationB;
    private IntegrationResource $campaignA;
    private IntegrationResource $adSetA;
    private IntegrationResource $adAccountA;
    private \App\Domain\AI\Models\Conversation $conversationA;
    private string $gatewayToken = 'test-ai-gateway-token-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_gateway.token' => $this->gatewayToken]);

        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);

        $this->ownerA = User::create(['name' => 'Owner A', 'email' => 'owner-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->ownerA->id, ['is_owner' => true]);

        $this->memberA = User::create(['name' => 'Member A', 'email' => 'member-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->memberA->id, ['is_owner' => false]);
        
        // Add meta.write permission to a role for member A if we want to test that
        $permission = Permission::firstOrCreate(['slug' => 'meta.write', 'name' => 'Meta Write']);
        $role = \App\Domain\Roles\Models\Role::create(['name' => 'Editor', 'slug' => 'editor', 'organization_id' => $this->orgA->id]);
        $role->permissions()->attach($permission->id);
        $this->memberA->roles()->attach($role->id, ['organization_id' => $this->orgA->id]);

        $this->ownerB = User::create(['name' => 'Owner B', 'email' => 'owner-b@test.com', 'password' => bcrypt('pw')]);
        $this->orgB->users()->attach($this->ownerB->id, ['is_owner' => true]);

        $this->integrationA = Integration::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta A',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_A'
        ]);

        $this->integrationB = Integration::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta B',
        ]);

        $this->adAccountA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Account A',
            'external_id' => 'act_111',
            'metadata_json' => ['timezone_name' => 'UTC'],
        ]);

        $this->campaignA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Camp A',
            'external_id' => 'camp_111',
            'parent_external_id' => 'act_111',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        $this->adSetA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_set',
            'name' => 'AdSet A',
            'external_id' => 'adset_111',
            'parent_external_id' => 'camp_111',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        $this->conversationA = \App\Domain\AI\Models\Conversation::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->ownerA->id,
            'title' => 'Test Convo',
        ]);
    }

    private function aiPost(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Organization-UUID' => $this->orgA->uuid,
            'X-User-UUID' => $this->ownerA->uuid,
        ];
        return $this->postJson('/api/ai' . $uri, $data, array_merge($defaultHeaders, $headers));
    }

    /** @test 1 - prepare não chama meta */
    public function test_prepare_does_not_call_meta_and_returns_confirmation()
    {
        Http::fake(); // Ensure no HTTP calls

        $response = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.status', 'pending');

        $this->assertNotNull($response->json('data.action_uuid'));
        $this->assertStringContainsString('Deseja confirmar?', $response->json('data.confirmation_message'));
        $this->assertEquals('PAUSED', $response->json('data.prepared_params.status'));

        // Validate DB
        $action = AIAction::first();
        $this->assertNotNull($action);
        $this->assertEquals('pending', $action->status);
        $this->assertEquals('meta', $action->provider);
        $this->assertEquals('status.update', $action->action_type);
        $this->assertEquals($this->campaignA->uuid, $action->target_resource_uuid);
        $this->assertEquals('ACTIVE', $action->snapshot['effective_status']);

        Http::assertNothingSent();
    }

    /** @test 2 - resource_type inválido */
    public function test_prepare_rejects_invalid_resource_type()
    {
        $response = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->adAccountA->uuid, // ad_account
            'status' => 'PAUSED',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('code', 'META_INVALID_RESOURCE_TYPE');
    }

    /** @test 3 - missing permissions */
    public function test_user_without_permission_cannot_prepare()
    {
        // member without role
        $memberB = User::create(['name' => 'Member B', 'email' => 'member-b@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($memberB->id, ['is_owner' => false]);

        $response = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ], [
            'X-User-UUID' => $memberB->uuid,
        ]);

        $response->assertStatus(403);
    }

    /** @test 4 - execute calls meta once and updates local state */
    public function test_execute_calls_meta_and_updates_local_state()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200)
        ]);

        // 1. Prepare
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');

        // 2. Execute
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");

        $execute->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.status', 'executed');

        // Assert Graph API was called
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'camp_111') &&
                   $request->method() === 'POST' &&
                   $request['status'] === 'PAUSED';
        });

        // Assert Local DB updated
        $this->campaignA->refresh();
        $this->assertEquals('PAUSED', $this->campaignA->metadata_json['effective_status']);

        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('executed', $action->status);
    }

    /** @test 5 - Org B cannot access action/resource from Org A */
    public function test_org_b_cannot_execute_org_a_action()
    {
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');

        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute", [], [
            'X-Organization-UUID' => $this->orgB->uuid,
            'X-User-UUID' => $this->ownerB->uuid,
        ]);

        $execute->assertStatus(404)
                ->assertJsonPath('code', 'META_ACTION_NOT_FOUND');
    }

    /** @test 6 - Action prepared by User A cannot be executed by User B */
    public function test_cannot_execute_action_prepared_by_another_user()
    {
        // Owner A prepares
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');

        // Member A tries to execute
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute", [], [
            'X-User-UUID' => $this->memberA->uuid,
        ]);

        $execute->assertStatus(403)
                ->assertJsonPath('code', 'META_ACTION_UNAUTHORIZED');
    }

    /** @test 7 - Execute action already executed (idempotency) */
    public function test_cannot_execute_already_executed_action()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200)
        ]);

        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');

        // Execute first time
        $this->aiPost("/meta/actions/{$actionUuid}/execute")->assertStatus(200);

        // Execute second time
        $execute2 = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute2->assertStatus(400)
                 ->assertJsonPath('code', 'META_ACTION_INVALID');
        
        Http::assertSentCount(1);
    }

    /** @test 8 - Action expired */
    public function test_expired_action_cannot_be_executed()
    {
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');

        // Force expiration
        AIAction::where('uuid', $actionUuid)->update(['expires_at' => now()->subMinute()]);

        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute->assertStatus(400)
                ->assertJsonPath('message', 'A validade desta ação já expirou.');
                
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('expired', $action->status);
    }
    
    /** @test 9 - Action já executing */
    public function test_cannot_execute_while_executing()
    {
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        // Simula que uma thread já pegou a action
        AIAction::where('uuid', $actionUuid)->update(['status' => 'executing']);
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute->assertStatus(400)
                ->assertJsonPath('message', 'Esta ação já está sendo executada no momento.');
    }
    
    /** @test 10 - Provider failure (500) */
    public function test_provider_failure_marks_action_as_failed_and_keeps_local_state()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Internal Error', 'code' => 1]], 500)
        ]);

        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        
        $execute->assertStatus(500);
        
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('failed', $action->status);
        
        // Resource manteve estado original
        $this->campaignA->refresh();
        $this->assertEquals('ACTIVE', $this->campaignA->metadata_json['effective_status']);
    }
    
    /** @test 11 - Rate Limit (429) */
    public function test_rate_limit_handled_correctly()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Rate Limit', 'code' => 17]], 403)
        ]);

        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        
        $execute->assertStatus(429)
                ->assertJsonPath('code', 'META_RATE_LIMITED');
                
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('failed', $action->status);
    }
    
    /** @test 12 - Invalid token (needs_reconnect) */
    public function test_invalid_token_marks_needs_reconnect()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth', 'code' => 190]], 401)
        ]);

        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        
        $execute->assertStatus(403)
                ->assertJsonPath('code', 'META_NEEDS_RECONNECT');
                
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('failed', $action->status);
    }
    
    /** @test 13 - Snapshot conflict */
    public function test_snapshot_conflict_rejects_execute()
    {
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        // Simula mudança de estado do resource na base local ANTES de confirmar
        $meta = $this->campaignA->metadata_json;
        $meta['effective_status'] = 'PAUSED';
        $this->campaignA->update(['metadata_json' => $meta]);
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        
        $execute->assertStatus(400)
                ->assertJsonPath('message', 'Conflito de estado do recurso.');
                
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('failed', $action->status);
    }
    
    /** @test 14 - Already desired state */
    public function test_already_desired_state_rejects_execute()
    {
        // Força snapshot já PAUSED e update para PAUSED
        $meta = $this->campaignA->metadata_json;
        $meta['effective_status'] = 'PAUSED';
        $this->campaignA->update(['metadata_json' => $meta]);
        
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        
        $execute->assertStatus(400)
                ->assertJsonPath('message', 'O recurso já se encontra no estado desejado.');
                
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('failed', $action->status);
    }

    /** @test 15 - Find pending action in the same conversation */
    public function test_get_pending_action_returns_correctly()
    {
        $conversationUuid = $this->conversationA->uuid;
        
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ], ['X-Conversation-UUID' => $conversationUuid]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        $get = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationUuid]);
        
        $get->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action_uuid', $actionUuid)
            ->assertJsonPath('data.action_type', 'status_update')
            ->assertJsonPath('data.resource.uuid', $this->campaignA->uuid)
            ->assertJsonPath('data.resource.type', 'campaign')
            ->assertJsonPath('data.from_status', 'ACTIVE')
            ->assertJsonPath('data.to_status', 'PAUSED')
            ->assertJsonMissing(['external_id']);
    }
    
    /** @test 16 - Missing conversation header */
    public function test_get_pending_action_requires_conversation_header()
    {
        $get = $this->aiGet('/meta/actions/pending');
        $get->assertStatus(400)
            ->assertJsonPath('code', 'META_MISSING_CONVERSATION');
    }
    
    /** @test 17 - Not found */
    public function test_get_pending_action_returns_404_if_none()
    {
        $conversationUuid = $this->conversationA->uuid;
        $get = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationUuid]);
        $get->assertStatus(404)
            ->assertJsonPath('code', 'META_PENDING_ACTION_NOT_FOUND');
    }
    
    /** @test 18 - Ambiguous (Multiple pending) */
    public function test_get_pending_action_returns_ambiguous_if_multiple()
    {
        $conversationUuid = $this->conversationA->uuid;
        
        $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ], ['X-Conversation-UUID' => $conversationUuid]);
        
        $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->adSetA->uuid,
            'status' => 'PAUSED',
        ], ['X-Conversation-UUID' => $conversationUuid]);
        
        $get = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationUuid]);
        
        $get->assertStatus(409)
            ->assertJsonPath('code', 'META_PENDING_ACTION_AMBIGUOUS')
            ->assertJsonPath('data.count', 2);
    }
    
    /** @test 19 - Cross user/org isolation */
    public function test_get_pending_action_isolated_by_user_org_conversation()
    {
        $conversationUuid = $this->conversationA->uuid;
        
        $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ], ['X-Conversation-UUID' => $conversationUuid]);
        
        // Outra conversa
        $conversationB = \App\Domain\AI\Models\Conversation::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->ownerA->id,
            'title' => 'Convo B',
        ]);
        $get = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationB->uuid]);
        $get->assertStatus(404);
        
        // Outro user/org com a mesma conversation
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Organization-UUID' => $this->orgB->uuid,
            'X-User-UUID' => $this->ownerB->uuid,
            'X-Conversation-UUID' => $conversationUuid,
        ];
        $get2 = $this->getJson('/api/ai/meta/actions/pending', $defaultHeaders);
        $get2->assertStatus(404);
    }
    
    /** @test 20 - Ignores expired or non-pending */
    public function test_get_pending_ignores_expired_or_executed()
    {
        $conversationUuid = $this->conversationA->uuid;
        
        $prepare = $this->aiPost('/meta/actions/status/prepare', [
            'resource_uuid' => $this->campaignA->uuid,
            'status' => 'PAUSED',
        ], ['X-Conversation-UUID' => $conversationUuid]);
        
        $actionUuid = $prepare->json('data.action_uuid');
        
        // Marca como expirado
        \App\Domain\AI\Models\AIAction::where('uuid', $actionUuid)->update(['expires_at' => now()->subMinute()]);
        
        $get = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationUuid]);
        $get->assertStatus(404);
        
        // Marca como executado
        \App\Domain\AI\Models\AIAction::where('uuid', $actionUuid)->update(['status' => 'executed', 'expires_at' => now()->addMinutes(30)]);
        
        $get2 = $this->aiGet('/meta/actions/pending', ['X-Conversation-UUID' => $conversationUuid]);
        $get2->assertStatus(404);
    }
    
    protected function aiGet(string $uri, array $headers = [])
    {
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Organization-UUID' => $this->orgA->uuid,
            'X-User-UUID' => $this->ownerA->uuid,
        ];

        return $this->getJson('/api/ai' . $uri, array_merge($defaultHeaders, $headers));
    }
}

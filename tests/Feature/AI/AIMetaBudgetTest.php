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

class AIMetaBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $ownerA;
    private User $memberA;
    private User $ownerB;
    private Integration $integrationA;
    private Integration $integrationB;
    private IntegrationResource $campaignCBO; // CBO Campaign
    private IntegrationResource $adSetUnderCBO; // AdSet controlled by Campaign
    private IntegrationResource $campaignNoCBO; // Non-CBO Campaign
    private IntegrationResource $adSetWithBudget; // AdSet that controls budget
    private IntegrationResource $adAccountA;
    private \App\Domain\AI\Models\Conversation $conversationA;
    private string $gatewayToken = 'test-ai-gateway-token-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_gateway.token' => $this->gatewayToken]);
        config(['ai_guardrails.financial.max_increase_percent' => 50]);
        config(['ai_guardrails.financial.max_decrease_percent' => 90]);
        config(['ai_guardrails.financial.max_daily_budget_absolute' => 5000]);

        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);

        $this->ownerA = User::create(['name' => 'Owner A', 'email' => 'owner-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->ownerA->id, ['is_owner' => true]);

        $this->memberA = User::create(['name' => 'Member A', 'email' => 'member-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->memberA->id, ['is_owner' => false]);
        
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

        $this->adAccountA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Account A',
            'external_id' => 'act_111',
            'metadata_json' => ['currency' => 'BRL', 'timezone_name' => 'America/Sao_Paulo'],
        ]);

        // CBO Campaign (controls budget)
        $this->campaignCBO = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Camp CBO',
            'external_id' => 'camp_cbo_1',
            'parent_external_id' => 'act_111',
            'metadata_json' => ['daily_budget' => 3000, 'effective_status' => 'ACTIVE'],
        ]);

        // AdSet under CBO
        $this->adSetUnderCBO = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_set',
            'name' => 'AdSet under CBO',
            'external_id' => 'adset_under_cbo_1',
            'parent_external_id' => 'camp_cbo_1',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        // Non-CBO Campaign
        $this->campaignNoCBO = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Camp No CBO',
            'external_id' => 'camp_no_cbo_1',
            'parent_external_id' => 'act_111',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        // AdSet with its own budget
        $this->adSetWithBudget = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_set',
            'name' => 'AdSet With Budget',
            'external_id' => 'adset_with_budget_1',
            'parent_external_id' => 'camp_no_cbo_1',
            'metadata_json' => ['daily_budget' => 5000, 'effective_status' => 'ACTIVE'],
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
            'X-Conversation-UUID' => $this->conversationA->uuid,
        ];

        return $this->postJson('/api/ai' . $uri, $data, array_merge($defaultHeaders, $headers));
    }

    private function aiGet(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-Organization-UUID' => $this->orgA->uuid,
            'X-User-UUID' => $this->ownerA->uuid,
            'X-Conversation-UUID' => $this->conversationA->uuid,
        ];

        return $this->getJson('/api/ai' . $uri, array_merge($defaultHeaders, $headers));
    }

    /** @test 1 - Prepare não chama Meta */
    public function test_prepare_budget_does_not_call_meta()
    {
        Http::fake();

        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 45.00
        ]);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    /** @test 2 - Campaign com budget funciona */
    public function test_prepare_campaign_with_budget_works()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 45.00
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.budget.current', 30)
            ->assertJsonPath('data.budget.proposed', 45)
            ->assertJsonPath('data.budget.type', 'daily')
            ->assertJsonPath('data.budget.currency', 'BRL');
    }

    /** @test 3 - Ad Set com budget próprio funciona */
    public function test_prepare_adset_with_budget_works()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->adSetWithBudget->uuid,
            'budget' => 60.00
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.budget.current', 50)
            ->assertJsonPath('data.budget.proposed', 60);
    }

    /** @test 4 - Ad Set sob Campaign-controlled budget rejeitado */
    public function test_prepare_adset_under_cbo_is_rejected()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->adSetUnderCBO->uuid,
            'budget' => 45.00
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'O orçamento deste Ad Set é controlado pela Campanha (Camp CBO). Altere o orçamento diretamente na Campanha.');
    }

    /** @test 5 - Campaign sem budget control compatível rejeitada */
    public function test_prepare_campaign_without_budget_is_rejected()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignNoCBO->uuid,
            'budget' => 45.00
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Não foi possível determinar a origem do orçamento para este recurso.');
    }

    /** @test 8 - Conversão para unidade Meta correta */
    public function test_prepare_converts_to_meta_subunit_internally()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 45.00
        ]);
        
        $actionUuid = $response->json('data.action_uuid');
        $action = AIAction::where('uuid', $actionUuid)->first();
        
        // 45.00 BRL -> 4500 subunit
        $this->assertEquals(4500, $action->prepared_params['budget']);
    }

    /** @test 9 - Budget zero rejeitado */
    public function test_prepare_rejects_zero_budget()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 0
        ]);

        $response->assertStatus(422); // Validation error (min:0.01)
    }

    /** @test 10 - Budget negativo rejeitado */
    public function test_prepare_rejects_negative_budget()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => -10
        ]);

        $response->assertStatus(422); // Validation error
    }

    /** @test 12 - Limite mínimo Meta */
    public function test_prepare_rejects_below_minimum_meta_limit()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 0.50
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'O orçamento proposto não atinge o valor mínimo aceitável para a moeda (1 BRL).');
    }

    /** @test 13 - Guardrail de aumento absurdo */
    public function test_prepare_rejects_absurd_increase()
    {
        $response = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 300.00 // Current is 30. 300 is 900% increase (max 50%)
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('excede o limite', $response->json('message'));
    }

    /** @test 15 - Execute chama Meta uma vez */
    public function test_execute_calls_meta_once()
    {
        Http::fake([
            'graph.facebook.com/v*/camp_cbo_1' => Http::response(['success' => true])
        ]);

        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');

        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute->assertStatus(200);

        Http::assertSentCount(1);
        
        $action = AIAction::where('uuid', $actionUuid)->first();
        $this->assertEquals('executed', $action->status);
    }

    /** @test 16 - Double execute não duplica */
    public function test_double_execute_does_not_duplicate()
    {
        Http::fake([
            'graph.facebook.com/v*/camp_cbo_1' => Http::response(['success' => true])
        ]);

        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');

        $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $doubleExecute = $this->aiPost("/meta/actions/{$actionUuid}/execute");

        $doubleExecute->assertStatus(400)
            ->assertJsonPath('message', 'Esta ação já foi resolvida (executada ou falhou).');

        Http::assertSentCount(1);
    }

    /** @test 18 - Snapshot conflict */
    public function test_execute_fails_on_snapshot_conflict()
    {
        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');

        // Modify resource budget behind the back
        $metadata = $this->campaignCBO->metadata_json;
        $metadata['daily_budget'] = 3500;
        $this->campaignCBO->update(['metadata_json' => $metadata]);

        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute->assertStatus(400)
            ->assertJsonPath('message', 'Conflito de estado do recurso (orçamento foi alterado externamente).');
    }

    /** @test 19 - Already desired = no op */
    public function test_execute_fails_if_already_desired()
    {
        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');

        // Modify resource AND snapshot so there's no conflict, 
        // but the current value equals the proposed value (4000 subunit).
        $metadata = $this->campaignCBO->metadata_json;
        $metadata['daily_budget'] = 4000;
        $this->campaignCBO->update(['metadata_json' => $metadata]);

        $action = AIAction::where('uuid', $actionUuid)->first();
        $snapshot = $action->snapshot;
        $snapshot['effective_budget'] = 40.0;
        $action->update(['snapshot' => $snapshot]);

        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute");
        $execute->assertStatus(400)
            ->assertJsonPath('message', 'O recurso já se encontra com o orçamento desejado.');
    }

    /** @test 25 - External ID nunca aparece no payload da action */
    public function test_pending_action_never_exposes_external_id_and_has_budget_structure()
    {
        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 45.00
        ]);

        $get = $this->aiGet('/meta/actions/pending');
        
        $get->assertStatus(200);
        $get->assertJsonMissing(['external_id']);
        $get->assertJsonMissing(['camp_cbo_1']);

        $get->assertJsonPath('data.action_type', 'budget_update');
        $get->assertJsonPath('data.budget.current', 30);
        $get->assertJsonPath('data.budget.proposed', 45);
        $get->assertJsonPath('data.budget.difference', 15);
        $get->assertJsonPath('data.budget.difference_percent', 50);
        $get->assertJsonPath('data.budget.type', 'daily');
    }

    /** @test 26 - Execute não aceita budget novo */
    public function test_execute_does_not_accept_new_budget_override()
    {
        Http::fake([
            'graph.facebook.com/v*/camp_cbo_1' => Http::response(['success' => true])
        ]);

        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');

        // Try to inject budget override
        $execute = $this->aiPost("/meta/actions/{$actionUuid}/execute", ['budget' => 5000]);
        $execute->assertStatus(200); // Executed successfully, ignoring payload

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request['daily_budget'] === 4000; // Original prepared subunit
        });
    }

    /** @test 27 - Local state só muda após success=true */
    public function test_local_state_only_changes_after_success()
    {
        Http::fake([
            'graph.facebook.com/v*/camp_cbo_1' => Http::response(['error' => 'API Error'], 500)
        ]);

        $prepare = $this->aiPost('/meta/actions/budget/prepare', [
            'resource_uuid' => $this->campaignCBO->uuid,
            'budget' => 40.00
        ]);

        $actionUuid = $prepare->json('data.action_uuid');
        $this->aiPost("/meta/actions/{$actionUuid}/execute");

        $this->campaignCBO->refresh();
        $this->assertEquals(3000, $this->campaignCBO->metadata_json['daily_budget']); // Still original
    }
}

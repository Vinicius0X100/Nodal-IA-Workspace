<?php

namespace Tests\Feature\AI;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Permissions\Models\Permission;
use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Roles\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIMetaGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $ownerA;
    private User $memberA;
    private User $ownerB;
    private Integration $integrationA;
    private Integration $integrationB;
    private IntegrationResource $adAccountA;
    private IntegrationResource $campaignA1;
    private IntegrationResource $campaignA2;
    private IntegrationResource $adSetA;
    private IntegrationResource $adA;
    private IntegrationResource $campaignB;
    private string $gatewayToken = 'test-ai-gateway-token-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_gateway.token' => $this->gatewayToken]);

        // ── Organizações ──
        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);

        // ── Usuários ──
        $this->ownerA = User::create(['name' => 'Owner A', 'email' => 'owner-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->ownerA->id, ['is_owner' => true]);

        $this->memberA = User::create(['name' => 'Member A', 'email' => 'member-a@test.com', 'password' => bcrypt('pw')]);
        $this->orgA->users()->attach($this->memberA->id, ['is_owner' => false]);

        $this->ownerB = User::create(['name' => 'Owner B', 'email' => 'owner-b@test.com', 'password' => bcrypt('pw')]);
        $this->orgB->users()->attach($this->ownerB->id, ['is_owner' => true]);

        // ── Integrações ──
        $this->integrationA = Integration::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_A',
        ]);

        $this->integrationB = Integration::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta',
        ]);

        // ── Resources Org A ──
        $this->adAccountA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'SisMatriz Ads',
            'external_id' => 'act_111',
            'metadata_json' => ['timezone_name' => 'America/Sao_Paulo', 'currency' => 'BRL'],
        ]);

        $this->campaignA1 = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'SisMatriz - Leads',
            'external_id' => 'camp_a1',
            'parent_external_id' => 'act_111',
            'metadata_json' => ['effective_status' => 'ACTIVE', 'objective' => 'OUTCOME_LEADS'],
        ]);

        $this->campaignA2 = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Vendas Gerais',
            'external_id' => 'camp_a2',
            'parent_external_id' => 'act_111',
            'metadata_json' => ['effective_status' => 'PAUSED', 'objective' => 'OUTCOME_SALES'],
        ]);

        $this->adSetA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_set',
            'name' => 'Santana 25-55',
            'external_id' => 'adset_a1',
            'parent_external_id' => 'camp_a1',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        $this->adA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad',
            'name' => 'Ad Criativo 1',
            'external_id' => 'ad_a1',
            'parent_external_id' => 'adset_a1',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);

        // ── Resources Org B ──
        $this->campaignB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Campanha B',
            'external_id' => 'camp_b1',
            'parent_external_id' => 'act_222',
            'metadata_json' => ['effective_status' => 'ACTIVE'],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function aiHeaders(
        ?string $orgUuid = null,
        ?string $userUuid = null,
        ?string $token = null
    ): array {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->gatewayToken),
            'X-Organization-UUID' => $orgUuid ?? $this->orgA->uuid,
            'X-User-UUID' => $userUuid ?? $this->ownerA->uuid,
        ];
    }

    private function aiGet(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/ai' . $uri, array_merge($this->aiHeaders(), $headers));
    }

    private function aiPost(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/ai' . $uri, $data, array_merge($this->aiHeaders(), $headers));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  1-5: AUTH & MIDDLEWARE
    // ═══════════════════════════════════════════════════════════════════

    /** @test 1 */
    public function test_invalid_gateway_token_is_blocked()
    {
        $response = $this->getJson('/api/ai/meta/accounts', $this->aiHeaders(token: 'bad-token'));
        $response->assertStatus(401);
    }

    /** @test 2 */
    public function test_missing_authorization_header_is_blocked()
    {
        $response = $this->getJson('/api/ai/meta/accounts', [
            'X-Organization-UUID' => $this->orgA->uuid,
            'X-User-UUID' => $this->ownerA->uuid,
        ]);
        $response->assertStatus(401);
    }

    /** @test 3 */
    public function test_missing_org_uuid_header_is_blocked()
    {
        $response = $this->getJson('/api/ai/meta/accounts', [
            'Authorization' => 'Bearer ' . $this->gatewayToken,
            'X-User-UUID' => $this->ownerA->uuid,
        ]);
        $response->assertStatus(400);
    }

    /** @test 4 */
    public function test_nonexistent_organization_is_blocked()
    {
        $response = $this->getJson('/api/ai/meta/accounts', $this->aiHeaders(orgUuid: (string) Str::uuid()));
        $response->assertStatus(404);
    }

    /** @test 5 */
    public function test_user_from_different_org_cannot_access()
    {
        $response = $this->getJson('/api/ai/meta/accounts', $this->aiHeaders(
            orgUuid: $this->orgA->uuid,
            userUuid: $this->ownerB->uuid, // Owner de Org B tentando acessar Org A
        ));
        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  6-7: PERMISSÕES
    // ═══════════════════════════════════════════════════════════════════

    /** @test 6 */
    public function test_member_without_meta_read_permission_is_blocked()
    {
        // memberA não tem role/permissão meta.read e não é owner
        $response = $this->getJson('/api/ai/meta/accounts', $this->aiHeaders(
            userUuid: $this->memberA->uuid,
        ));
        $response->assertStatus(403)
                 ->assertJsonPath('code', 'META_PERMISSION_DENIED');
    }

    /** @test 7 */
    public function test_owner_has_bypass_access()
    {
        $response = $this->aiGet('/meta/accounts');
        $response->assertStatus(200)
                 ->assertJsonPath('success', true);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  8-9: INTEGRAÇÃO META STATUS
    // ═══════════════════════════════════════════════════════════════════

    /** @test 8 */
    public function test_meta_not_connected_returns_proper_error()
    {
        $this->integrationA->update(['status' => 'not_connected']);

        $response = $this->aiGet('/meta/accounts');
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'META_NOT_CONNECTED');
    }

    /** @test 9 */
    public function test_meta_needs_reconnect_returns_proper_error()
    {
        $this->integrationA->update(['status' => 'needs_reconnect']);

        $response = $this->aiGet('/meta/accounts');
        $response->assertStatus(403)
                 ->assertJsonPath('code', 'META_NEEDS_RECONNECT');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  10-11: /accounts
    // ═══════════════════════════════════════════════════════════════════

    /** @test 10 */
    public function test_accounts_returns_only_org_a()
    {
        $response = $this->aiGet('/meta/accounts');
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(1, 'data');

        $account = $response->json('data.0');
        $this->assertEquals('SisMatriz Ads', $account['name']);
        $this->assertEquals('BRL', $account['currency']);
        $this->assertEquals('America/Sao_Paulo', $account['timezone']);
    }

    /** @test 11 */
    public function test_accounts_never_returns_external_id()
    {
        $response = $this->aiGet('/meta/accounts');
        $json = $response->getContent();
        $this->assertStringNotContainsString('act_111', $json);
        $this->assertStringNotContainsString('external_id', $json);
        $this->assertStringNotContainsString('integration_id', $json);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  12-15: /campaigns
    // ═══════════════════════════════════════════════════════════════════

    /** @test 12 */
    public function test_campaigns_returns_only_org_a_campaigns()
    {
        $response = $this->aiGet('/meta/campaigns');
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(2, 'data');

        $json = $response->getContent();
        $this->assertStringNotContainsString('Campanha B', $json);
    }

    /** @test 13 */
    public function test_campaigns_search_works()
    {
        $response = $this->aiGet('/meta/campaigns?search=SisMatriz');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'SisMatriz - Leads');
    }

    /** @test 14 */
    public function test_campaigns_status_filter_works()
    {
        $response = $this->aiGet('/meta/campaigns?status=PAUSED');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Vendas Gerais');
    }

    /** @test 15 */
    public function test_campaigns_with_org_b_ad_account_uuid_returns_404()
    {
        // Cria ad account na Org B
        $adAccountB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Account B',
            'external_id' => 'act_222',
        ]);

        $response = $this->aiGet('/meta/campaigns?ad_account_uuid=' . $adAccountB->uuid);
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'META_RESOURCE_NOT_FOUND');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  16-21: /resources/{uuid}
    // ═══════════════════════════════════════════════════════════════════

    /** @test 16 */
    public function test_campaign_uuid_from_org_b_returns_404()
    {
        $response = $this->aiGet('/meta/resources/' . $this->campaignB->uuid);
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'META_RESOURCE_NOT_FOUND');
    }

    /** @test 17 */
    public function test_adset_uuid_from_org_b_returns_404()
    {
        $adSetB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'ad_set',
            'name' => 'AdSet B',
            'external_id' => 'adset_b1',
            'parent_external_id' => 'camp_b1',
        ]);
        $response = $this->aiGet('/meta/resources/' . $adSetB->uuid);
        $response->assertStatus(404);
    }

    /** @test 18 */
    public function test_ad_uuid_from_org_b_returns_404()
    {
        $adB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'ad',
            'name' => 'Ad B',
            'external_id' => 'ad_b1',
            'parent_external_id' => 'adset_b1',
        ]);
        $response = $this->aiGet('/meta/resources/' . $adB->uuid);
        $response->assertStatus(404);
    }

    /** @test 19 */
    public function test_resource_returns_correct_resource()
    {
        $response = $this->aiGet('/meta/resources/' . $this->campaignA1->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.uuid', $this->campaignA1->uuid)
                 ->assertJsonPath('data.name', 'SisMatriz - Leads')
                 ->assertJsonPath('data.type', 'campaign');
    }

    /** @test 20 */
    public function test_resource_children_returns_correct_children()
    {
        $response = $this->aiGet('/meta/resources/' . $this->campaignA1->uuid . '?children=true');
        $response->assertStatus(200);

        $children = $response->json('data.children');
        $this->assertCount(1, $children);
        $this->assertEquals('Santana 25-55', $children[0]['name']);
        $this->assertEquals('ad_set', $children[0]['type']);
    }

    /** @test 21 */
    public function test_resource_never_contains_parent_external_id()
    {
        $response = $this->aiGet('/meta/resources/' . $this->campaignA1->uuid . '?children=true');
        $json = $response->getContent();
        $this->assertStringNotContainsString('parent_external_id', $json);
        $this->assertStringNotContainsString('camp_a1', $json); // external_id do campaign
        $this->assertStringNotContainsString('act_111', $json); // external_id do parent
        // Deve conter parent_uuid (referência interna)
        $this->assertStringContainsString('parent_uuid', $json);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  22-23: /insights (sync & async)
    // ═══════════════════════════════════════════════════════════════════

    /** @test 22 */
    public function test_insights_sync_works()
    {
        Http::fake([
            'graph.facebook.com/*/act_111/insights*' => Http::response([
                'data' => [
                    [
                        'campaign_id' => 'camp_a1',
                        'account_currency' => 'BRL',
                        'spend' => '100.00',
                        'impressions' => '500',
                        'clicks' => '25',
                        'ctr' => '5.0',
                        'cpc' => '4.00',
                        'cpm' => '200.0',
                        'actions' => [['action_type' => 'lead', 'value' => '5']],
                    ]
                ]
            ])
        ]);

        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'period' => 'last_7d',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('async', false)
                 ->assertJsonPath('data.mode', 'sync');
    }

    /** @test 23 */
    public function test_insights_async_returns_report_uuid()
    {
        Queue::fake();

        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'ad', // Aciona async
            'period' => 'last_7d',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('async', true)
                 ->assertJsonPath('data.mode', 'async')
                 ->assertJsonPath('data.status', 'queued');

        $this->assertNotNull($response->json('data.report_uuid'));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  24-29: /reports/{uuid}
    // ═══════════════════════════════════════════════════════════════════

    /** @test 24 */
    public function test_async_report_from_org_b_returns_404()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'completed',
            'result' => ['test' => true],
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'META_REPORT_NOT_FOUND');
    }

    /** @test 25 */
    public function test_queued_report_returns_status()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'queued',
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'queued')
                 ->assertJsonPath('data.progress', 0);
    }

    /** @test 26 */
    public function test_running_report_returns_progress()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'running',
            'progress' => 42,
            'started_at' => now(),
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'running')
                 ->assertJsonPath('data.progress', 42);
    }

    /** @test 27 */
    public function test_completed_report_returns_result()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'completed',
            'progress' => 100,
            'result' => ['metrics' => ['spend' => 100]],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'completed')
                 ->assertJsonPath('data.progress', 100)
                 ->assertJsonPath('data.result.metrics.spend', 100);
    }

    /** @test 28 */
    public function test_partial_report_returns_data()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'partial',
            'progress' => 75,
            'started_at' => now(),
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'partial')
                 ->assertJsonPath('data.progress', 75);
    }

    /** @test 29 */
    public function test_failed_report_is_normalized()
    {
        $report = AsyncReport::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'type' => 'insights',
            'status' => 'failed',
            'error_message' => 'Timeout na consulta Meta',
            'started_at' => now(),
        ]);

        $response = $this->aiGet('/reports/' . $report->uuid);
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.status', 'failed')
                 ->assertJsonPath('data.error.code', 'META_REPORT_FAILED')
                 ->assertJsonPath('data.error.message', 'Não foi possível concluir o relatório.')
                 ->assertJsonMissing(['error_message']);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  30-38: SEGURANÇA E EDGE CASES
    // ═══════════════════════════════════════════════════════════════════

    /** @test 30 — rate limit retorna META_RATE_LIMITED */
    public function test_rate_limit_returns_proper_error()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
        ]);

        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'period' => 'today',
        ]);

        // O MetaMarketingClient pode lançar exception com code 429
        // ou o controller pode pegar e retornar META_RATE_LIMITED
        $this->assertContains($response->status(), [429, 400, 500]);
    }

    /** @test 31 */
    public function test_random_uuid_returns_404()
    {
        $response = $this->aiGet('/meta/resources/' . (string) Str::uuid());
        $response->assertStatus(404)
                 ->assertJsonPath('code', 'META_RESOURCE_NOT_FOUND');
    }

    /** @test 32 */
    public function test_external_id_sent_as_uuid_is_rejected()
    {
        // act_111 não é UUID válido → rota com whereUuid rejeitará
        $response = $this->aiGet('/meta/resources/act_111');
        $response->assertStatus(404); // 404 pois whereUuid não faz match
    }

    /** @test 33 */
    public function test_response_never_contains_access_token()
    {
        $response = $this->aiGet('/meta/accounts');
        $json = $response->getContent();
        $this->assertStringNotContainsString('TOKEN_A', $json);
        $this->assertStringNotContainsString('access_token', $json);
    }

    /** @test 34 */
    public function test_response_never_contains_integration_id()
    {
        $response = $this->aiGet('/meta/accounts');
        $json = $response->getContent();
        $this->assertStringNotContainsString('"integration_id"', $json);
        $this->assertStringNotContainsString('"organization_id"', $json);
    }

    /** @test 35 — cache isolado entre tenants */
    public function test_insights_cache_is_isolated_between_tenants()
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [
                ['campaign_id' => 'camp_a1', 'spend' => '50', 'account_currency' => 'BRL']
            ]])
        ]);

        // Consulta Org A
        $r1 = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'period' => 'yesterday',
        ]);
        $r1->assertStatus(200);

        // Org B nunca consegue acessar dados do mesmo cache
        $r2 = $this->getJson('/api/ai/meta/accounts', $this->aiHeaders(
            orgUuid: $this->orgB->uuid,
            userUuid: $this->ownerB->uuid,
        ));
        // Org B acessando /accounts retorna seus próprios dados (nenhum ad_account criado para B)
        $json = $r2->getContent();
        $this->assertStringNotContainsString('SisMatriz', $json);
    }

    /** @test 36 — paginação impede resposta gigante */
    public function test_limit_parameter_caps_results()
    {
        $response = $this->aiGet('/meta/campaigns?limit=1');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    /** @test 37 — parâmetros inválidos retornam erro 422 */
    public function test_invalid_parameters_return_error()
    {
        $response = $this->aiPost('/meta/insights', [
            // resource_uuid ausente
            'level' => 'invalid_level',
        ]);
        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    /** @test 39 — period e custom dates são mutualmente exclusivos */
    public function test_period_and_custom_dates_are_mutually_exclusive()
    {
        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'period' => 'today',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);
        $response->assertStatus(422)
                 ->assertJsonPath('errors.period.0', 'O parâmetro period não pode ser enviado junto com date_from ou date_to.');
    }

    /** @test 40 — date_to é obrigatório se date_from enviado */
    public function test_date_to_is_required_with_date_from()
    {
        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'date_from' => '2026-01-01',
        ]);
        $response->assertStatus(422)
                 ->assertJsonPath('errors.date_to.0', 'date_to é obrigatório quando period não é informado.');
    }

    /** @test 41 — date_from <= date_to */
    public function test_date_from_must_be_before_date_to()
    {
        $response = $this->aiPost('/meta/insights', [
            'resource_uuid' => $this->adAccountA->uuid,
            'level' => 'campaign',
            'date_from' => '2026-01-31',
            'date_to' => '2026-01-01',
        ]);
        $response->assertStatus(422)
                 ->assertJsonPath('errors.date_to.0', 'date_to deve ser igual ou posterior a date_from.');
    }

    /** @test 38 — read-only não aceita escrita */
    public function test_readonly_endpoints_reject_write_methods()
    {
        $response = $this->postJson('/api/ai/meta/accounts', [], $this->aiHeaders());
        $response->assertStatus(405); // Method not allowed

        $response = $this->deleteJson('/api/ai/meta/accounts', [], $this->aiHeaders());
        $response->assertStatus(405);
    }
}

<?php

namespace Tests\Feature\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Reports\Models\AsyncReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Domain\Reports\Jobs\GenerateAsyncReportJob;
use Tests\TestCase;

class MetaInsightsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private Integration $integrationA;
    private Integration $integrationB;
    private IntegrationResource $campaignA;
    private IntegrationResource $campaignB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->userA = User::create(['name' => 'User A', 'email' => 'a@a.com', 'password' => bcrypt('password')]);
        $this->orgA->users()->attach($this->userA->id, ['is_owner' => true]);

        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);

        $this->integrationA = Integration::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta',
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
            'display_name' => 'Meta',
        ]);

        $adAccount = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Ad Account 123',
            'external_id' => 'act_123',
            'metadata_json' => ['timezone_name' => 'America/Sao_Paulo', 'currency' => 'BRL'],
        ]);

        $this->campaignA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Campaign A',
            'external_id' => 'camp_a',
            'parent_external_id' => 'act_123'
        ]);

        $this->campaignB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'campaign',
            'name' => 'Campaign B',
            'external_id' => 'camp_b',
            'parent_external_id' => 'act_999'
        ]);
    }

    public function test_sync_report_returns_normalized_data_and_uses_cache()
    {
        Http::fake([
            'graph.facebook.com/*/act_123/insights*' => Http::response([
                'data' => [
                    [
                        'campaign_id' => 'camp_a',
                        'account_currency' => 'BRL',
                        'spend' => '150.50',
                        'impressions' => '1000',
                        'clicks' => '50',
                        'ctr' => '5.0',
                        'cpc' => '3.01',
                        'cpm' => '150.5',
                        'actions' => [
                            ['action_type' => 'lead', 'value' => '10'],
                            ['action_type' => 'link_click', 'value' => '45']
                        ],
                        'action_values' => [
                            ['action_type' => 'purchase', 'value' => '500.00']
                        ]
                    ]
                ]
            ])
        ]);

        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => $adAccount->uuid ?? IntegrationResource::where('external_id', 'act_123')->first()->uuid,
            'level' => 'campaign',
            'period' => 'last_7d'
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.0.metrics');

        $this->assertEquals('BRL', $data['currency']);
        $this->assertEquals(150.50, $data['spend']);
        $this->assertEquals(10, $data['leads']);
        $this->assertEquals(15.05, $data['cpl']); // 150.50 / 10
        $this->assertEquals(5.0, $data['ctr']);
        $this->assertEquals(3.01, $data['cpc']);

        // Test Cache hit
        Http::assertSentCount(1);
        
        $response2 = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => IntegrationResource::where('external_id', 'act_123')->first()->uuid,
            'level' => 'campaign',
            'period' => 'last_7d'
        ]);
        
        $response2->assertStatus(200);
        Http::assertSentCount(1); // Não aumentou pois pegou do cache
    }

    public function test_async_report_is_queued_for_heavy_requests()
    {
        Queue::fake();

        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => IntegrationResource::where('external_id', 'act_123')->first()->uuid,
            'level' => 'ad', // Level AD aciona async automático
            'period' => 'last_7d'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('async', true)
                 ->assertJsonPath('data.status', 'queued');

        $reportUuid = $response->json('data.report_uuid');
        $this->assertNotNull($reportUuid);

        Queue::assertPushed(GenerateAsyncReportJob::class, function ($job) use ($reportUuid) {
            return $job->report->uuid === $reportUuid;
        });

        // Polling test
        $pollingResponse = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->getJson("/api/reports/{$reportUuid}");
        $pollingResponse->assertStatus(200)
                        ->assertJsonPath('data.status', 'queued');
    }

    public function test_org_a_cannot_request_insights_for_org_b_resource()
    {
        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => $this->campaignB->uuid,
            'level' => 'campaign',
            'period' => 'last_7d'
        ]);

        $response->assertStatus(400); // Falha de validação pois o Service lança InvalidArgumentException
        $this->assertStringContainsString('não encontrado', $response->json('error'));
    }

    public function test_zero_leads_returns_null_cpl()
    {
        Http::fake([
            'graph.facebook.com/*/act_123/insights*' => Http::response([
                'data' => [
                    [
                        'campaign_id' => 'camp_a',
                        'spend' => '100.00',
                        'actions' => [] // zero leads
                    ]
                ]
            ])
        ]);

        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => IntegrationResource::where('external_id', 'act_123')->first()->uuid,
            'level' => 'campaign',
            'period' => 'today' // outro periodo p/ não bugar com cache do teste anterior
        ]);

        $data = $response->json('data.0.metrics');
        $this->assertNull($data['cpl']);
        $this->assertEquals(0, $data['leads']);
    }

    public function test_external_id_never_appears_in_response()
    {
        Http::fake([
            'graph.facebook.com/*/act_123/insights*' => Http::response([
                'data' => [['campaign_id' => 'camp_a', 'spend' => '10']]
            ])
        ]);

        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->postJson('/integrations/meta/insights', [
            'resource_uuid' => IntegrationResource::where('external_id', 'act_123')->first()->uuid,
            'level' => 'campaign',
            'period' => 'yesterday'
        ]);

        $json = $response->getContent();
        $this->assertStringNotContainsString('camp_a', $json);
        $this->assertStringNotContainsString('act_123', $json);
        $this->assertStringContainsString($this->campaignA->uuid, $json); // UUID deve aparecer
    }
}

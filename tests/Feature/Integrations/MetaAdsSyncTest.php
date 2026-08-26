<?php

namespace Tests\Feature\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Jobs\Meta\SyncMetaAssetsJob;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Integrations\Services\Meta\MetaRateLimitException;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdsSyncTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private Integration $integrationA;
    private Integration $integrationB;

    protected function setUp(): void
    {
        parent::setUp();

        // Org A
        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->userA = User::create(['name' => 'User A', 'email' => 'a@a.com', 'password' => bcrypt('password')]);
        $this->orgA->users()->attach($this->userA->id, ['is_owner' => true]);

        $this->integrationA = Integration::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta A',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_A',
        ]);

        // Org B
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);
        $this->userB = User::create(['name' => 'User B', 'email' => 'b@b.com', 'password' => bcrypt('password')]);
        $this->orgB->users()->attach($this->userB->id, ['is_owner' => true]);

        $this->integrationB = Integration::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta B',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_B',
        ]);
    }

    private function fakeGraphApiSuccess()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response([
                'data' => [
                    ['id' => 'act_123', 'name' => 'Conta 123', 'account_status' => 1]
                ]
            ], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => []
            ], 200),
            'graph.facebook.com/*/act_123/campaigns*' => Http::response([
                'data' => [
                    ['id' => 'camp_1', 'name' => 'Campanha 1', 'status' => 'ACTIVE', 'objective' => 'CONVERSIONS']
                ]
            ], 200),
            'graph.facebook.com/*/act_123/adsets*' => Http::response([
                'data' => [
                    ['id' => 'adset_1', 'campaign_id' => 'camp_1', 'name' => 'Conjunto 1', 'status' => 'ACTIVE', 'daily_budget' => '1000']
                ]
            ], 200),
            'graph.facebook.com/*/act_123/ads*' => Http::response([
                'data' => [
                    ['id' => 'ad_1', 'adset_id' => 'adset_1', 'name' => 'Anúncio 1', 'status' => 'ACTIVE']
                ]
            ], 200)
        ]);
    }

    public function test_campaign_adset_and_ad_are_created_with_correct_hierarchy()
    {
        $this->fakeGraphApiSuccess();

        $job = new SyncMetaAssetsJob($this->integrationA, $this->userA->id);
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'resource_type' => ResourceType::AD_ACCOUNT->value,
            'external_id' => 'act_123'
        ]);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'resource_type' => ResourceType::CAMPAIGN->value,
            'external_id' => 'camp_1',
            'parent_external_id' => 'act_123'
        ]);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'resource_type' => ResourceType::AD_SET->value,
            'external_id' => 'adset_1',
            'parent_external_id' => 'camp_1'
        ]);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'resource_type' => ResourceType::AD->value,
            'external_id' => 'ad_1',
            'parent_external_id' => 'adset_1'
        ]);
    }

    public function test_second_sync_does_not_duplicate_but_updates_metadata()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_123', 'name' => 'Conta 123']]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/campaigns*' => Http::sequence()
                ->push(['data' => [['id' => 'camp_1', 'name' => 'Campanha Antiga', 'status' => 'ACTIVE']]])
                ->push(['data' => [['id' => 'camp_1', 'name' => 'Campanha Nova', 'status' => 'PAUSED']]]),
            'graph.facebook.com/*/act_123/adsets*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/ads*' => Http::response(['data' => []])
        ]);

        $job = new SyncMetaAssetsJob($this->integrationA, $this->userA->id);
        
        // Primeira rodada
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        $this->assertEquals(1, IntegrationResource::where('resource_type', 'campaign')->count());
        $this->assertDatabaseHas('integration_resources', ['external_id' => 'camp_1', 'name' => 'Campanha Antiga']);

        // Segunda rodada
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        $this->assertEquals(1, IntegrationResource::where('resource_type', 'campaign')->count()); // Sem duplicidade
        $this->assertDatabaseHas('integration_resources', ['external_id' => 'camp_1', 'name' => 'Campanha Nova']);
    }

    public function test_account_without_campaigns_does_not_fail()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_123']]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/campaigns*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/adsets*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/ads*' => Http::response(['data' => []])
        ]);

        $job = new SyncMetaAssetsJob($this->integrationA, $this->userA->id);
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        $this->assertDatabaseHas('integration_logs', [
            'integration_id' => $this->integrationA->id,
            'event' => 'meta_campaigns_sync_completed'
        ]);

        $this->assertEquals(0, IntegrationResource::where('resource_type', 'campaign')->count());
    }

    public function test_org_b_cannot_resolve_campaign_of_org_a()
    {
        $this->fakeGraphApiSuccess();

        $job = new SyncMetaAssetsJob($this->integrationA, $this->userA->id);
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        $repo = app(ResourceRepository::class);
        $campaign = IntegrationResource::where('resource_type', 'campaign')->first();

        $this->assertNotNull($repo->findByUuid($this->orgA->id, $campaign->uuid));
        $this->assertNull($repo->findByUuid($this->orgB->id, $campaign->uuid));
    }

    public function test_partial_error_does_not_delete_synchronized_resources()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['data' => [['id' => 'act_123']]]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []]),
            'graph.facebook.com/*/act_123/campaigns*' => Http::response(['data' => [['id' => 'camp_1']]]),
            'graph.facebook.com/*/act_123/adsets*' => Http::response(['error' => ['message' => 'Rate limit']], 403),
            'graph.facebook.com/*/act_123/ads*' => Http::response(['data' => [['id' => 'ad_1']]])
        ]);

        $job = new SyncMetaAssetsJob($this->integrationA, $this->userA->id);
        $job->handle(
            app(\App\Domain\Integrations\Services\Meta\MetaAdAccountsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaPagesService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaCampaignsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdSetsService::class),
            app(\App\Domain\Integrations\Services\Meta\MetaAdsService::class)
        );

        // Mesmo que Ad Sets falhou com Rate Limit, a campanha foi salva com sucesso e as outras passaram/tentaram.
        $this->assertDatabaseHas('integration_resources', ['external_id' => 'camp_1']);
        
        $this->assertDatabaseHas('integration_logs', [
            'event' => 'meta_adsets_sync_failed',
            'status' => 'error'
        ]);
        
        $this->assertDatabaseHas('integration_logs', [
            'event' => 'meta_sync_job_finished_with_errors'
        ]);
    }
}

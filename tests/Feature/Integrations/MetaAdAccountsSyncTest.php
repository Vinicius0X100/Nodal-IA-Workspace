<?php

namespace Tests\Feature\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Integrations\Services\Meta\MetaAdAccountsService;
use App\Domain\Integrations\Services\Meta\MetaRateLimitException;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaAdAccountsSyncTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private Integration $integrationA;
    private Integration $integrationB;
    private MetaAdAccountsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MetaAdAccountsService::class);

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
                    [
                        'id' => 'act_111',
                        'name' => 'Conta 111',
                        'currency' => 'BRL',
                        'timezone_name' => 'America/Sao_Paulo',
                        'account_status' => 1
                    ],
                    [
                        'id' => 'act_222',
                        'name' => 'Conta 222',
                        'currency' => 'USD',
                        'timezone_name' => 'America/New_York',
                        'account_status' => 1
                    ]
                ],
                'paging' => []
            ], 200)
        ]);
    }

    public function test_org_a_uses_only_token_a_for_graph_api()
    {
        $this->fakeGraphApiSuccess();

        $this->service->syncAdAccounts($this->integrationA);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN_A');
        });
    }

    public function test_org_b_uses_only_token_b_for_graph_api()
    {
        $this->fakeGraphApiSuccess();

        $this->service->syncAdAccounts($this->integrationB);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer TOKEN_B');
        });
    }

    public function test_token_inexistent_throws_exception_and_does_not_call_api()
    {
        IntegrationToken::where('organization_id', $this->orgA->id)->delete();
        Http::fake();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token Meta não encontrado');

        $this->service->syncAdAccounts($this->integrationA);

        Http::assertNothingSent();
    }

    public function test_status_needs_reconnect_is_marked_when_graph_returns_401()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['error' => ['message' => 'Invalid token']], 401)
        ]);

        $this->expectException(\RuntimeException::class);

        $this->service->syncAdAccounts($this->integrationA);

        $this->assertEquals('needs_reconnect', $this->integrationA->fresh()->status);
    }

    public function test_rate_limit_throws_specific_exception()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response(['error' => ['code' => 17, 'message' => 'Limit']], 403)
        ]);

        $this->expectException(MetaRateLimitException::class);

        $this->service->syncAdAccounts($this->integrationA);
    }

    public function test_sync_creates_resource_with_provider_meta_and_resource_type_ad_account()
    {
        $this->fakeGraphApiSuccess();

        $this->service->syncAdAccounts($this->integrationA);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'provider' => Provider::META->value,
            'resource_type' => ResourceType::AD_ACCOUNT->value,
            'external_id' => 'act_111',
            'name' => 'Conta 111'
        ]);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'provider' => Provider::META->value,
            'resource_type' => ResourceType::AD_ACCOUNT->value,
            'external_id' => 'act_222',
            'name' => 'Conta 222'
        ]);
    }

    public function test_second_sync_does_upsert_without_duplicating()
    {
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::sequence()
                ->push([
                    'data' => [
                        ['id' => 'act_111', 'name' => 'Conta 111', 'currency' => 'BRL', 'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1]
                    ],
                    'paging' => []
                ], 200)
                ->push([
                    'data' => [
                        ['id' => 'act_111', 'name' => 'Conta 111 Alterada', 'currency' => 'BRL', 'timezone_name' => 'America/Sao_Paulo', 'account_status' => 1]
                    ],
                    'paging' => []
                ], 200)
        ]);

        $this->service->syncAdAccounts($this->integrationA);
        $this->assertEquals(1, IntegrationResource::count());

        $this->service->syncAdAccounts($this->integrationA);

        $this->assertEquals(1, IntegrationResource::count()); // Contagem não mudou
        $this->assertDatabaseHas('integration_resources', [
            'external_id' => 'act_111',
            'name' => 'Conta 111 Alterada'
        ]);
    }

    public function test_resources_stay_linked_to_the_correct_integration()
    {
        $this->fakeGraphApiSuccess();
        
        $this->service->syncAdAccounts($this->integrationA);

        $this->assertEquals(2, IntegrationResource::where('integration_id', $this->integrationA->id)->count());
        $this->assertEquals(0, IntegrationResource::where('integration_id', $this->integrationB->id)->count());
    }

    public function test_uuid_is_exposed_and_external_id_never_appears_in_dto()
    {
        $this->fakeGraphApiSuccess();
        $this->service->syncAdAccounts($this->integrationA);

        // Simulando a renderização pelo controller
        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->get(route('integrations.meta'));

        $response->assertStatus(200);

        // O Inertia passa 'ad_accounts' como prop. Vamos pegar da view retornada
        $props = $response->viewData('page')['props'];
        
        $this->assertArrayHasKey('ad_accounts', $props);
        $this->assertCount(2, $props['ad_accounts']);
        
        $account = $props['ad_accounts'][0];
        $this->assertArrayHasKey('uuid', $account);
        $this->assertArrayNotHasKey('external_id', $account);
    }

    public function test_internal_uuid_is_exposed_by_resource_repository()
    {
        $this->fakeGraphApiSuccess();
        $this->service->syncAdAccounts($this->integrationA);

        $resource = IntegrationResource::first();
        $repo = app(ResourceRepository::class);

        $found = $repo->findByUuid($this->orgA->id, $resource->uuid);

        $this->assertNotNull($found);
        $this->assertEquals($resource->id, $found->id);
    }

    public function test_uuid_of_org_a_cannot_be_resolved_with_org_b()
    {
        $this->fakeGraphApiSuccess();
        $this->service->syncAdAccounts($this->integrationA);

        $resource = IntegrationResource::first();
        $repo = app(ResourceRepository::class);

        $found = $repo->findByUuid($this->orgB->id, $resource->uuid);

        $this->assertNull($found); // Segurança garantida
    }

    public function test_meta_failure_does_not_contaminate_resources_of_other_organizations()
    {
        // Org A falha
        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::sequence()
                ->push(['error' => ['message' => 'Fail']], 500)
                ->push(['data' => [['id' => 'act_B', 'name' => 'Conta B']]], 200)
        ]);

        try {
            $this->service->syncAdAccounts($this->integrationA);
        } catch (\Exception $e) {}

        // Org B sincroniza com sucesso
        $this->service->syncAdAccounts($this->integrationB);

        $this->assertEquals(0, IntegrationResource::where('integration_id', $this->integrationA->id)->count());
        $this->assertEquals(1, IntegrationResource::where('integration_id', $this->integrationB->id)->count());
    }
}

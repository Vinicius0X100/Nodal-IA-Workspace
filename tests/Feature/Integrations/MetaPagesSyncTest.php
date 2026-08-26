<?php

namespace Tests\Feature\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Integrations\Services\Meta\MetaPagesService;
use App\Domain\Integrations\Services\Meta\MetaRateLimitException;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Enums\Provider;
use App\Domain\Resources\Enums\ResourceType;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Resources\Repositories\ResourceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaPagesSyncTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private Integration $integrationA;
    private Integration $integrationB;
    private MetaPagesService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MetaPagesService::class);

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

    private function fakeGraphApiPagesAndIgSuccess()
    {
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page_111',
                        'name' => 'Página A1',
                        'username' => 'page_a1',
                        'category' => 'Software',
                        'picture' => ['data' => ['url' => 'http://pic.com/a1.jpg']],
                        'instagram_business_account' => [
                            'id' => 'ig_111',
                            'username' => 'ig_a1',
                            'name' => 'Insta A1',
                            'profile_picture_url' => 'http://pic.com/iga1.jpg'
                        ]
                    ],
                    [
                        'id' => 'page_222',
                        'name' => 'Página A2 Sem IG',
                    ]
                ],
                'paging' => []
            ], 200)
        ]);
    }

    public function test_org_a_synchronizes_only_its_pages()
    {
        $this->fakeGraphApiPagesAndIgSuccess();

        $counts = $this->service->syncPagesAndInstagram($this->integrationA);

        $this->assertEquals(2, $counts['pages']);
        $this->assertEquals(1, $counts['instagram']);
        
        $this->assertEquals(3, IntegrationResource::where('integration_id', $this->integrationA->id)->count());
        $this->assertEquals(0, IntegrationResource::where('integration_id', $this->integrationB->id)->count());
    }

    public function test_org_b_synchronizes_only_its_pages()
    {
        $this->fakeGraphApiPagesAndIgSuccess();

        $this->service->syncPagesAndInstagram($this->integrationB);

        $this->assertEquals(0, IntegrationResource::where('integration_id', $this->integrationA->id)->count());
        $this->assertEquals(3, IntegrationResource::where('integration_id', $this->integrationB->id)->count());
    }

    public function test_page_is_persisted_with_meta_provider_and_facebook_page_resource_type()
    {
        $this->fakeGraphApiPagesAndIgSuccess();
        $this->service->syncPagesAndInstagram($this->integrationA);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'provider' => Provider::META->value,
            'resource_type' => ResourceType::FACEBOOK_PAGE->value,
            'external_id' => 'page_111',
            'name' => 'Página A1'
        ]);
    }

    public function test_instagram_linked_is_persisted_with_instagram_account_resource_type_and_parent()
    {
        $this->fakeGraphApiPagesAndIgSuccess();
        $this->service->syncPagesAndInstagram($this->integrationA);

        $this->assertDatabaseHas('integration_resources', [
            'integration_id' => $this->integrationA->id,
            'provider' => Provider::META->value,
            'resource_type' => ResourceType::INSTAGRAM_ACCOUNT->value,
            'external_id' => 'ig_111',
            'parent_external_id' => 'page_111',
            'name' => 'Insta A1'
        ]);
    }

    public function test_page_without_instagram_does_not_cause_failure()
    {
        $this->fakeGraphApiPagesAndIgSuccess();
        // O fake já tem a page_222 sem instagram_business_account
        $this->service->syncPagesAndInstagram($this->integrationA);

        $this->assertDatabaseHas('integration_resources', [
            'external_id' => 'page_222',
            'resource_type' => ResourceType::FACEBOOK_PAGE->value,
        ]);
    }

    public function test_second_synchronization_does_not_duplicate_resources_but_updates_metadata()
    {
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::sequence()
                ->push([
                    'data' => [
                        ['id' => 'page_111', 'name' => 'Página A1 Antiga']
                    ],
                    'paging' => []
                ], 200)
                ->push([
                    'data' => [
                        ['id' => 'page_111', 'name' => 'Página A1 Nova']
                    ],
                    'paging' => []
                ], 200)
        ]);

        $this->service->syncPagesAndInstagram($this->integrationA);
        $this->assertEquals(1, IntegrationResource::count());

        $this->service->syncPagesAndInstagram($this->integrationA);
        $this->assertEquals(1, IntegrationResource::count()); // não duplicou

        $this->assertDatabaseHas('integration_resources', [
            'external_id' => 'page_111',
            'name' => 'Página A1 Nova'
        ]);
    }

    public function test_invalid_token_marks_needs_reconnect()
    {
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response(['error' => ['message' => 'Invalid token']], 401)
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->syncPagesAndInstagram($this->integrationA);

        $this->assertEquals('needs_reconnect', $this->integrationA->fresh()->status);
    }

    public function test_rate_limit_is_handled_by_existing_mechanism()
    {
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response(['error' => ['code' => 17, 'message' => 'Limit']], 403)
        ]);

        $this->expectException(MetaRateLimitException::class);
        $this->service->syncPagesAndInstagram($this->integrationA);
    }

    public function test_external_id_never_appears_in_public_response_but_uuid_does()
    {
        $this->fakeGraphApiPagesAndIgSuccess();
        $this->service->syncPagesAndInstagram($this->integrationA);

        $response = $this->actingAs($this->userA)
            ->withSession(['active_organization_id' => $this->orgA->id])
            ->get(route('integrations.meta'));

        $response->assertStatus(200);
        $props = $response->viewData('page')['props'];

        $this->assertArrayHasKey('facebook_pages', $props);
        $this->assertArrayHasKey('instagram_accounts', $props);

        $page = $props['facebook_pages'][0];
        $this->assertArrayHasKey('uuid', $page);
        $this->assertArrayNotHasKey('external_id', $page);

        $ig = $props['instagram_accounts'][0];
        $this->assertArrayHasKey('uuid', $ig);
        $this->assertArrayNotHasKey('external_id', $ig);
        $this->assertEquals('page_111', $ig['parent_external_id']); // parent_external_id é exposto no frontend para vincular
    }

    public function test_two_organizations_with_same_external_id_do_not_contaminate_resources()
    {
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    ['id' => 'page_same', 'name' => 'Mesma Page']
                ]
            ], 200)
        ]);

        $this->service->syncPagesAndInstagram($this->integrationA);
        $this->service->syncPagesAndInstagram($this->integrationB);

        $this->assertEquals(2, IntegrationResource::where('external_id', 'page_same')->count());
        $this->assertEquals(1, IntegrationResource::where('integration_id', $this->integrationA->id)->count());
        $this->assertEquals(1, IntegrationResource::where('integration_id', $this->integrationB->id)->count());
    }

    public function test_org_b_cannot_resolve_uuid_of_org_a()
    {
        $this->fakeGraphApiPagesAndIgSuccess();
        $this->service->syncPagesAndInstagram($this->integrationA);

        $repo = app(ResourceRepository::class);
        
        $page = IntegrationResource::where('resource_type', ResourceType::FACEBOOK_PAGE->value)->first();
        $ig = IntegrationResource::where('resource_type', ResourceType::INSTAGRAM_ACCOUNT->value)->first();

        // Org A consegue
        $this->assertNotNull($repo->findByUuid($this->orgA->id, $page->uuid));
        $this->assertNotNull($repo->findByUuid($this->orgA->id, $ig->uuid));

        // Org B não consegue
        $this->assertNull($repo->findByUuid($this->orgB->id, $page->uuid));
        $this->assertNull($repo->findByUuid($this->orgB->id, $ig->uuid));
    }
}

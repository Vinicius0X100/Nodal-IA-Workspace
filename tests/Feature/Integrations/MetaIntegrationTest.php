<?php

namespace Tests\Feature\Integrations;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class MetaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.meta', [
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'redirect' => 'http://localhost/oauth/meta/callback',
        ]);
        Config::set('services.facebook', [
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'redirect' => 'http://localhost/oauth/meta/callback',
        ]);
    }

    public function test_organization_can_start_meta_oauth()
    {
        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);
        
        // Mock Socialite
        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('scopes')->andReturnSelf();
        $providerMock->shouldReceive('redirect')->andReturn(redirect('https://facebook.com/oauth'));
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($providerMock);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('integrations.connect', ['provider' => 'meta']));

        $response->assertRedirect('https://facebook.com/oauth');

        $this->assertDatabaseHas('integrations', [
            'organization_id' => $organization->id,
            'provider' => 'meta',
            'status' => 'configuring',
        ]);
    }

    public function test_callback_saves_token_and_sets_status_connected()
    {
        \Illuminate\Support\Facades\Queue::fake();

        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);

        $abstractUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->token = 'fake_access_token';
        $abstractUser->refreshToken = 'fake_refresh_token';
        $abstractUser->expiresIn = 3600;
        $abstractUser->approvedScopes = ['email', 'public_profile'];

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($providerMock);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'fakecode', 'state' => 'fakestate']));

        if (session()->has('error')) {
            dump(session('error'));
        }

        $response->assertRedirect(route('integrations.meta'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('integrations', [
            'organization_id' => $organization->id,
            'provider' => 'meta',
            'status' => 'connected',
        ]);

        $token = IntegrationToken::where('organization_id', $organization->id)
            ->where('provider', 'meta')
            ->first();

        $this->assertNotNull($token);
        $this->assertEquals('Bearer', $token->token_type);
        // access_token is encrypted, so we can't assert equal string on DB directly, but we know it's there
    }

    public function test_multi_tenancy_isolation()
    {
        $orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);
        $userA = User::create(['name' => 'User A', 'email' => 'usera@test.com', 'password' => '123']);
        $userB = User::create(['name' => 'User B', 'email' => 'userb@test.com', 'password' => '123']);
        $orgA->users()->attach($userA->id, ['is_owner' => true]);
        $orgB->users()->attach($userB->id, ['is_owner' => true]);

        Integration::create(['organization_id' => $orgA->id, 'provider' => 'meta', 'status' => 'connected', 'display_name' => 'Meta']);
        IntegrationToken::create(['organization_id' => $orgA->id, 'provider' => 'meta', 'access_token' => 'a', 'expires_at' => now()->addDay()]);
        
        Integration::create(['organization_id' => $orgB->id, 'provider' => 'meta', 'status' => 'connected', 'display_name' => 'Meta']);
        IntegrationToken::create(['organization_id' => $orgB->id, 'provider' => 'meta', 'access_token' => 'b', 'expires_at' => now()->addDay()]);

        $this->assertDatabaseCount('integrations', 2);

        // User A disconnects Meta
        $response = $this->actingAs($userA)
            ->from(route('integrations.index'))
            ->withSession(['active_organization_id' => $orgA->id])
            ->post(route('integrations.disconnect', ['provider' => 'meta']));

        $response->assertRedirect(route('integrations.index'));

        // Org A should be disconnected
        $this->assertDatabaseHas('integrations', [
            'organization_id' => $orgA->id,
            'provider' => 'meta',
            'status' => 'not_connected',
        ]);
        $this->assertDatabaseMissing('integration_tokens', [
            'organization_id' => $orgA->id,
            'provider' => 'meta',
        ]);

        // Org B should remain intact
        $this->assertDatabaseHas('integrations', [
            'organization_id' => $orgB->id,
            'provider' => 'meta',
            'status' => 'connected',
        ]);
        $this->assertEquals(1, IntegrationToken::where('organization_id', $orgB->id)->where('provider', 'meta')->count());
    }

    public function test_callback_without_credentials_fails()
    {
        Config::set('services.meta', []);

        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);

        $response = $this->actingAs($user)
            ->from(route('integrations.index'))
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('integrations.connect', ['provider' => 'meta']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_cancelled_oauth()
    {
        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);

        $response = $this->actingAs($user)
            ->from(route('integrations.index'))
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('oauth.callback', [
                'provider' => 'meta',
                'error' => 'access_denied',
                'error_description' => 'The user denied your request.'
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Falha ao conectar: The user denied your request.');
    }

    public function test_invalid_state_exception_from_socialite()
    {
        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('user')->andThrow(new \Laravel\Socialite\Two\InvalidStateException());
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($providerMock);

        $response = $this->actingAs($user)
            ->from(route('integrations.index'))
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'fake', 'state' => 'fake']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_reconnect_is_idempotent()
    {
        \Illuminate\Support\Facades\Queue::fake();

        $organization = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => '123']);
        $organization->users()->attach($user->id, ['is_owner' => true]);

        Integration::create(['organization_id' => $organization->id, 'provider' => 'meta', 'status' => 'connected', 'display_name' => 'Meta']);
        IntegrationToken::create(['organization_id' => $organization->id, 'provider' => 'meta', 'access_token' => 'old_token', 'expires_at' => now()->addDay()]);

        $abstractUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->token = 'new_token';
        $abstractUser->refreshToken = 'new_refresh_token';
        $abstractUser->expiresIn = 3600;
        $abstractUser->approvedScopes = ['email'];

        $providerMock = Mockery::mock();
        $providerMock->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($providerMock);

        $this->actingAs($user)
            ->withSession(['active_organization_id' => $organization->id])
            ->get(route('oauth.callback', ['provider' => 'meta', 'code' => 'fakecode', 'state' => 'fakestate']));

        $this->assertDatabaseCount('integrations', 1);
        $this->assertDatabaseCount('integration_tokens', 1);

        $token = IntegrationToken::where('organization_id', $organization->id)->where('provider', 'meta')->first();
        $this->assertNotEquals('old_token', $token->access_token);
    }
}

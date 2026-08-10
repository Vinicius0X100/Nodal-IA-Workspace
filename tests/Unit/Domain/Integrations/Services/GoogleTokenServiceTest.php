<?php

namespace Tests\Unit\Domain\Integrations\Services;

use Tests\TestCase;
use App\Domain\Integrations\Services\GoogleTokenService;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationConfig;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;
use Carbon\Carbon;

class GoogleTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GoogleTokenService $service;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleTokenService();
        Log::spy();
        
        $this->organization = Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'active' => true,
        ]);
    }

    public function test_it_returns_current_token_if_valid()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'valid_token_123',
            'token_expires_at' => now()->addMinutes(10), // > 5 minutes
        ]);

        $token = $this->service->getValidAccessToken($integration);

        $this->assertEquals('valid_token_123', $token);
    }

    public function test_it_refreshes_token_if_expired()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'expired_token',
            'refresh_token' => 'refresh_me_123',
            'token_expires_at' => now()->subMinutes(1),
        ]);

        IntegrationConfig::create([
            'integration_id' => $integration->id,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token_456',
                'expires_in' => 3600,
            ], 200)
        ]);

        $token = $this->service->getValidAccessToken($integration);

        $this->assertEquals('new_access_token_456', $token);
        
        $integration->refresh();
        $this->assertEquals('new_access_token_456', $integration->access_token);
        $this->assertEquals('refresh_me_123', $integration->refresh_token); // preserved
        $this->assertTrue($integration->token_expires_at->isFuture());
    }

    public function test_it_refreshes_token_if_expiring_soon()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'soon_to_expire_token',
            'refresh_token' => 'refresh_me_123',
            'token_expires_at' => now()->addMinutes(2), // < 5 minutes
        ]);

        IntegrationConfig::create([
            'integration_id' => $integration->id,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token_789',
                'expires_in' => 3600,
            ], 200)
        ]);

        $token = $this->service->getValidAccessToken($integration);

        $this->assertEquals('new_access_token_789', $token);
    }

    public function test_it_updates_refresh_token_if_google_provides_a_new_one()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'expired_token',
            'refresh_token' => 'old_refresh_123',
            'token_expires_at' => now()->subMinutes(1),
        ]);

        IntegrationConfig::create([
            'integration_id' => $integration->id,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'brand_new_refresh',
                'expires_in' => 3600,
            ], 200)
        ]);

        $this->service->getValidAccessToken($integration);
        
        $integration->refresh();
        $this->assertEquals('brand_new_refresh', $integration->refresh_token);
    }

    public function test_it_handles_invalid_grant_by_marking_reconnect()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'expired_token',
            'refresh_token' => 'bad_refresh_token',
            'token_expires_at' => now()->subMinutes(1),
            'status' => 'connected'
        ]);

        IntegrationConfig::create([
            'integration_id' => $integration->id,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.'
            ], 400)
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('O refresh token é inválido ou foi revogado pelo usuário no painel do Google. Reconecte a conta.');

        try {
            $this->service->getValidAccessToken($integration);
        } finally {
            $integration->refresh();
            $this->assertEquals('needs_reconnect', $integration->status);
        }
    }

    public function test_execute_with_retry_succeeds_first_time()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'valid_token',
            'token_expires_at' => now()->addMinutes(10),
        ]);

        Http::fake([
            'api.test/*' => Http::response('OK', 200)
        ]);

        $response = $this->service->executeWithRetry($integration, function ($token) {
            $this->assertEquals('valid_token', $token);
            return Http::withToken($token)->get('https://api.test/endpoint');
        });

        $this->assertEquals(200, $response->status());
    }

    public function test_execute_with_retry_handles_401_and_retries()
    {
        $integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'access_token' => 'soon_invalid_token',
            'refresh_token' => 'good_refresh',
            'token_expires_at' => now()->addMinutes(10),
        ]);

        IntegrationConfig::create([
            'integration_id' => $integration->id,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
        ]);

        // Mock OAuth refresh endpoint and API endpoint
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new_fresh_token',
                'expires_in' => 3600,
            ], 200),
            'api.test/*' => Http::sequence()
                            ->push('Unauthorized', 401)
                            ->push('OK', 200)
        ]);

        $callCount = 0;

        $response = $this->service->executeWithRetry($integration, function ($token) use (&$callCount) {
            $callCount++;
            
            if ($callCount === 1) {
                $this->assertEquals('soon_invalid_token', $token);
            } else {
                $this->assertEquals('new_fresh_token', $token);
            }
            
            return Http::withToken($token)->get('https://api.test/endpoint');
        });

        $this->assertEquals(2, $callCount);
        $this->assertEquals(200, $response->status());
        
        $integration->refresh();
        $this->assertEquals('new_fresh_token', $integration->access_token);
    }
}

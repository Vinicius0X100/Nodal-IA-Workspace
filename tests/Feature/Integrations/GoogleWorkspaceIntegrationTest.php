<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationConfig;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleWorkspaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $organization;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::create(['name' => 'Admin', 'email' => 'admin@nodal.app', 'password' => '123']);
        $this->organization = Organization::create(['name' => 'Org Nodal', 'slug' => 'org-nodal']);
        $this->organization->users()->attach($this->user->id, ['is_owner' => true]);
        
        session(['active_organization_id' => $this->organization->id]);

        // Mock Global Configs
        Config::set('services.google_workspace.client_id', 'global-client-id');
        Config::set('services.google_workspace.client_secret', 'global-client-secret');
        Config::set('services.google_workspace.redirect', 'https://nodal.app/oauth/google_workspace/callback');
        Config::set('services.google_workspace.service_account_client_id', 'global-sa-client-id');
        Config::set('services.google_workspace.service_account_json', json_encode([
            'client_email' => 'ai@nodal.app',
            'private_key' => '-----BEGIN PRIVATE KEY-----\nMOCK_KEY\n-----END PRIVATE KEY-----'
        ]));
    }

    public function test_tenant_without_own_client_id_can_initiate_oauth_using_global_credential()
    {
        // Ao salvar a config vazia
        $response = $this->actingAs($this->user)->post(route('integrations.config', ['provider' => 'google_workspace']), [
            'tenant' => 'sacratech.com'
        ]);
        
        $response->assertSessionHasNoErrors();
        
        // E tentar conectar, ele deve usar a config global e redirecionar ao Google
        $response = $this->actingAs($this->user)->get(route('integrations.connect', ['provider' => 'google_workspace']));
        
        $response->assertRedirect();
        $this->assertStringContainsString('https://accounts.google.com/o/oauth2', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=global-client-id', $response->headers->get('Location'));
    }

    public function test_integration_configs_is_preserved_with_tenant_specific_data()
    {
        $this->actingAs($this->user)->post(route('integrations.config', ['provider' => 'google_workspace']), [
            'tenant' => 'meu-dominio.com'
        ]);

        $config = IntegrationConfig::first();
        
        $this->assertNotNull($config);
        $this->assertEquals('meu-dominio.com', $config->tenant);
        $this->assertNull($config->client_id); // Não precisa ser preenchido
        $this->assertNull($config->client_secret);
    }
    
    public function test_api_and_frontend_never_returns_client_secret_or_private_key()
    {
        // O Inertia endpoint não deve conter o client_secret (a menos que a model puxe, e ela casta pra hidden ou a view nunca usa).
        // Vamos checar que o frontend controller expõe apenas service_account_client_id
        
        $response = $this->actingAs($this->user)->get(route('integrations.google-workspace'));
        
        $response->assertOk();
        $page = $response->viewData('page');
        
        $this->assertEquals('global-sa-client-id', $page['props']['google_service_account_client_id']);
        
        // Ensure that client_secret and private_key aren't magically leaked in global props
        $pageJson = json_encode($page);
        $this->assertStringNotContainsString('global-client-secret', $pageJson);
        $this->assertStringNotContainsString('MOCK_KEY', $pageJson);
    }
}

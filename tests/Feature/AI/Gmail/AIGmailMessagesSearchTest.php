<?php

namespace Tests\Feature\AI\Gmail;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIGmailMessagesSearchTest extends TestCase
{
    use RefreshDatabase;

    private $organization;
    private $user;
    private $integration;
    private $externalIdentity;
    private $endpoint = '/api/ai/gmail/messages';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->organization = Organization::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'active' => true
        ]);

        $this->user = User::create(['name' => 'Regular User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $this->integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider'        => 'google_workspace',
            'display_name'    => 'Google Workspace',
            'status'          => 'connected',
            'config'          => [],
        ]);

        \App\Domain\Integrations\Models\IntegrationConfig::create([
            'integration_id' => $this->integration->id,
            'delegation_credentials_json' => [
                'client_email' => 'test@test.com',
                'private_key'  => 'fake_key'
            ]
        ]);

        $this->externalIdentity = ExternalIdentity::create([
            'organization_id' => $this->organization->id,
            'user_id'         => $this->user->id,
            'integration_id'  => $this->integration->id,
            'provider'        => 'google_workspace',
            'external_id'     => 'user@google.com',
            'primary_email'   => 'user@google.com',
            'metadata'        => ['email' => 'user@google.com']
        ]);

        // Associa a permissão ao usuário
        $role = \App\Domain\Roles\Models\Role::create([
            'organization_id' => $this->organization->id,
            'name' => 'Self Role',
            'slug' => 'self-role'
        ]);
        
        $permission = \App\Domain\Permissions\Models\Permission::create([
            'slug' => 'gmail.messages.read',
            'name' => 'Gmail Read',
            'group' => 'Gmail',
            'is_system' => true
        ]);
        $role->permissions()->attach($permission->id, ['scope' => 'self']);
        $this->user->roles()->attach($role->id, ['organization_id' => $this->organization->id]);

        Cache::flush();

        // Mock para pular a geração JWT do TokenService
        $this->mock(\App\Domain\Integrations\Services\GoogleTokenService::class, function ($mock) {
            $mock->shouldReceive('getDelegatedAccessToken')
                 ->andReturn('dwd_token');
            $mock->shouldReceive('executeWithRetry')
                 ->andReturnUsing(function ($integration, $callback, $identity, $scopes) {
                     return $callback('dwd_token');
                 });
        });
    }

    private function actAsAI(?array $revokePermissions = null)
    {
        if ($revokePermissions) {
            $roleSelf = \App\Domain\Roles\Models\Role::where('slug', 'self-role')->first();
            $roleSelf->permissions()->detach();
        }

        config(['services.ai_gateway.token' => 'test-token']);

        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID' => $this->user->uuid,
            'X-Conversation-UUID' => 'conv-123',
        ]);
    }

    public function test_blocks_if_missing_permission()
    {
        $response = $this->actAsAI(['gmail.messages.read'])->getJson($this->endpoint);
        $response->assertStatus(403);
    }

    public function test_blocks_if_missing_external_identity()
    {
        $this->externalIdentity->delete();

        $response = $this->actAsAI()->getJson($this->endpoint);
        
        $response->assertStatus(403)
                 ->assertJsonPath('code', 'EXTERNAL_IDENTITY_REQUIRED');
    }

    public function test_successfully_searches_and_enriches_messages()
    {
        $this->withoutExceptionHandling();
        // Fake messages list
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages*' => Http::sequence()
                ->push([
                    'messages' => [
                        ['id' => 'msg1', 'threadId' => 'thread1'],
                    ],
                    'resultSizeEstimate' => 1
                ], 200)
                ->push([
                    'id' => 'msg1',
                    'threadId' => 'thread1',
                    'snippet' => 'Test snippet',
                    'labelIds' => ['UNREAD', 'INBOX'],
                    'payload' => [
                        'headers' => [
                            ['name' => 'From', 'value' => 'Sender <sender@example.com>'],
                            ['name' => 'To', 'value' => 'user@google.com'],
                            ['name' => 'Subject', 'value' => 'Test Subject'],
                            ['name' => 'Date', 'value' => 'Wed, 12 Aug 2026 10:00:00 -0300'],
                        ]
                    ]
                ], 200)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint . '?q=test&limit=10');
        
        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.result_size_estimate', 1)
                 ->assertJsonPath('data.messages.0.message_id', 'msg1')
                 ->assertJsonPath('data.messages.0.subject', 'Test Subject')
                 ->assertJsonPath('data.messages.0.from.email', 'sender@example.com')
                 ->assertJsonPath('data.messages.0.unread', true)
                 ->assertJsonPath('data.messages.0.has_attachment', false)
                 ->assertJsonPath('data.messages.0.snippet', 'Test snippet');

        // Audit Log verification
        $audit = \App\Domain\Audit\Models\AuditLog::where('action', 'ai_gmail_messages_search')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->metadata['allowed']);
        $this->assertEquals(1, $audit->metadata['result_count']);
        $this->assertEquals('test', $audit->metadata['q'] ?? null);
    }

    public function test_handles_no_messages_found()
    {
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages*' => Http::response([
                'resultSizeEstimate' => 0
            ], 200)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.result_size_estimate', 0)
                 ->assertJsonCount(0, 'data.messages');
    }

    public function test_query_builder_with_advanced_filters()
    {
        // Verificaremos se o serviço envia os filtros corretos na queryString
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages*' => function (\Illuminate\Http\Client\Request $request) {
                // A requisição de search não tem "format=metadata"
                if (!str_contains($request->url(), 'format=metadata')) {
                    $this->assertStringContainsString('q=' . urlencode('financeiro@empresa.com from:(sender@test.com) to:(receiver@test.com) subject:(Nota Fiscal) after:2026/08/01 before:2026/08/12 is:unread has:attachment label:INBOX'), $request->url());
                    return Http::response(['resultSizeEstimate' => 0], 200);
                }
            }
        ]);

        $this->actAsAI()->getJson($this->endpoint . '?' . http_build_query([
            'q' => 'financeiro@empresa.com',
            'from' => 'sender@test.com',
            'to' => 'receiver@test.com',
            'subject' => 'Nota Fiscal',
            'after' => '2026-08-01',
            'before' => '2026-08-12',
            'is_unread' => true,
            'has_attachment' => true,
            'label' => 'INBOX'
        ]));
    }
}

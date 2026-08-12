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

class AIGmailMessageReadTest extends TestCase
{
    use RefreshDatabase;

    private $organization;
    private $user;
    private $integration;
    private $externalIdentity;
    private $endpoint = '/api/ai/gmail/messages/msg_123';

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

        $this->mock(\App\Domain\Integrations\Services\GoogleTokenService::class, function ($mock) {
            $mock->shouldReceive('getDelegatedAccessToken')
                 ->andReturn('dwd_token');
            $mock->shouldReceive('executeWithRetry')
                 ->andReturnUsing(function ($integration, $callback, $identity, $scopes) {
                     return $callback('dwd_token');
                 });
        });
    }

    private function actAsAI()
    {
        config(['services.ai_gateway.token' => 'test-token']);

        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID' => $this->user->uuid,
            'X-Conversation-UUID' => 'conv-123',
        ]);
    }

    private function encodeBase64Url(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    public function test_reads_multipart_alternative_message_successfully()
    {
        $plainText = "Hello Plain World!";
        $htmlText = "<h1>Hello HTML World!</h1>";

        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/msg_123*' => Http::response([
                'id' => 'msg_123',
                'threadId' => 'thread_123',
                'snippet' => 'Hello Plain World',
                'labelIds' => ['INBOX'],
                'payload' => [
                    'headers' => [
                        ['name' => 'From', 'value' => 'Sender <sender@example.com>'],
                        ['name' => 'To', 'value' => 'user@google.com'],
                        ['name' => 'Cc', 'value' => 'cc@google.com'],
                        ['name' => 'Subject', 'value' => 'Multipart Test'],
                        ['name' => 'Date', 'value' => 'Wed, 12 Aug 2026 10:00:00 -0300'],
                    ],
                    'parts' => [
                        [
                            'mimeType' => 'multipart/alternative',
                            'parts' => [
                                [
                                    'mimeType' => 'text/plain',
                                    'body' => ['data' => $this->encodeBase64Url($plainText)]
                                ],
                                [
                                    'mimeType' => 'text/html',
                                    'body' => ['data' => $this->encodeBase64Url($htmlText)]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.message.message_id', 'msg_123')
                 ->assertJsonPath('data.message.subject', 'Multipart Test')
                 ->assertJsonPath('data.message.from.email', 'sender@example.com')
                 ->assertJsonPath('data.message.to.0.email', 'user@google.com')
                 ->assertJsonPath('data.message.cc.0.email', 'cc@google.com')
                 ->assertJsonPath('data.message.body.text', $plainText . "\n")
                 ->assertJsonPath('data.message.body.html_available', true);
    }

    public function test_reads_message_with_attachments()
    {
        $plainText = "See attached file.";
        
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/msg_123*' => Http::response([
                'id' => 'msg_123',
                'payload' => [
                    'headers' => [
                        ['name' => 'From', 'value' => 'Sender <sender@example.com>'],
                        ['name' => 'To', 'value' => 'user@google.com'],
                    ],
                    'parts' => [
                        [
                            'mimeType' => 'text/plain',
                            'body' => ['data' => $this->encodeBase64Url($plainText)]
                        ],
                        [
                            'mimeType' => 'application/pdf',
                            'filename' => 'document.pdf',
                            'body' => [
                                'attachmentId' => 'att_12345',
                                'size' => 102400
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.message.attachments.0.filename', 'document.pdf')
                 ->assertJsonPath('data.message.attachments.0.attachment_id', 'att_12345')
                 ->assertJsonPath('data.message.attachments.0.size', 102400);
    }

    public function test_returns_404_if_message_not_found()
    {
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/msg_123*' => Http::response([], 404)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint);

        $response->assertStatus(404)
                 ->assertJsonPath('code', 'GMAIL_MESSAGE_NOT_FOUND');
    }

    public function test_returns_403_if_access_denied_by_google()
    {
        Http::fake([
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/msg_123*' => Http::response([], 403)
        ]);

        $response = $this->actAsAI()->getJson($this->endpoint);

        $response->assertStatus(403)
                 ->assertJsonPath('code', 'ACCESS_DENIED');
    }
}

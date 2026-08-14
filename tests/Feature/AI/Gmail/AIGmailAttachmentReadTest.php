<?php

namespace Tests\Feature\AI\Gmail;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Audit\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIGmailAttachmentReadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;
    private User $member;
    private Integration $integration;
    private ExternalIdentity $externalIdentity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-' . Str::uuid(),
        ]);

        $this->owner = User::create([
            'name'     => 'Owner User',
            'email'    => 'owner@acme.com',
            'password' => bcrypt('password'),
        ]);
        $this->owner->organizations()->attach($this->organization->id, ['is_owner' => true]);

        $this->member = User::create([
            'name'     => 'Member User',
            'email'    => 'member@acme.com',
            'password' => bcrypt('password'),
        ]);
        $this->member->organizations()->attach($this->organization->id, ['is_owner' => false]);

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
                'client_email' => 'service-account@test.iam.gserviceaccount.com',
                'private_key'  => 'fake_private_key',
            ]
        ]);

        $this->externalIdentity = ExternalIdentity::create([
            'user_id'         => $this->member->id,
            'organization_id' => $this->organization->id,
            'integration_id'  => $this->integration->id,
            'provider'        => 'google_workspace',
            'external_id'     => 'member@acme.com',
            'primary_email'   => 'member@acme.com',
            'metadata'        => ['email' => 'member@acme.com'],
            'access_token'    => 'not_used_for_dwd',
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
        $this->member->roles()->attach($role->id, ['organization_id' => $this->organization->id]);

        \Illuminate\Support\Facades\Cache::flush();

        $this->mock(\App\Domain\Integrations\Services\GoogleTokenService::class, function ($mock) {
            $mock->shouldReceive('getDelegatedAccessToken')
                 ->andReturn('dwd_token');
            $mock->shouldReceive('executeWithRetry')
                 ->andReturnUsing(function ($integration, $callback, $identity, $scopes) {
                     return $callback('dwd_token');
                 });
        });

        // Simula DWD Token
        Http::preventStrayRequests();
    }

    private function actAsAI()
    {
        config(['services.ai_gateway.token' => 'test-token']);

        return $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID' => $this->member->uuid,
            'X-Conversation-UUID' => 'conv-123',
        ]);
    }

    public function test_can_read_plain_text_attachment()
    {
        $messageId = 'msg123';
        $attachmentId = 'att123';
        $plainTextContent = 'Hello World from Attachment!';
        $base64Content = str_replace(['+', '/'], ['-', '_'], base64_encode($plainTextContent));

        Http::fake([
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}*" => Http::response([
                'size' => strlen($plainTextContent),
                'data' => $base64Content
            ], 200),

            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response([
                'id' => $messageId,
                'payload' => [
                    'mimeType' => 'multipart/mixed',
                    'parts' => [
                        [
                            'partId' => '1',
                            'mimeType' => 'text/plain',
                            'filename' => 'test.txt',
                            'body' => [
                                'attachmentId' => $attachmentId,
                                'size' => strlen($plainTextContent),
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actAsAI()
            ->getJson("/api/ai/gmail/messages/{$messageId}/attachments/{$attachmentId}");



        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'attachment' => [
                    'message_id' => $messageId,
                    'attachment_id' => $attachmentId,
                    'filename' => 'test.txt',
                    'mime_type' => 'text/plain',
                    'content' => [
                        'type' => 'text',
                        'text' => $plainTextContent,
                        'truncated' => false
                    ]
                ]
            ]
        ]);

        $audit = AuditLog::where('action', 'ai_gmail_attachment_read')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->member->id, $audit->user_id);
        $this->assertEquals($attachmentId, $audit->metadata['attachment_id']);
        $this->assertEquals('text/plain', $audit->metadata['mime_type']);
        
        // Ensure no base64 in audit log
        $this->assertStringNotContainsString('data', json_encode($audit->metadata));
        $this->assertStringNotContainsString($base64Content, json_encode($audit->metadata));
    }

    public function test_rejects_missing_attachment_in_message()
    {
        $messageId = 'msg123';
        $attachmentId = 'att123';

        Http::fake([
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response([
                'id' => $messageId,
                'payload' => [
                    'mimeType' => 'multipart/mixed',
                    'parts' => [
                        [
                            'partId' => '1',
                            'mimeType' => 'text/plain',
                            'filename' => 'test.txt',
                            'body' => [
                                'attachmentId' => 'OTHER_ATT_ID',
                                'size' => 10,
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actAsAI()
            ->getJson("/api/ai/gmail/messages/{$messageId}/attachments/{$attachmentId}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'code' => 'GMAIL_ATTACHMENT_NOT_FOUND',
        ]);
    }

    public function test_rejects_large_attachment()
    {
        $messageId = 'msg123';
        $attachmentId = 'att123';

        Http::fake([
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response([
                'id' => $messageId,
                'payload' => [
                    'mimeType' => 'multipart/mixed',
                    'parts' => [
                        [
                            'partId' => '1',
                            'mimeType' => 'text/plain',
                            'filename' => 'large.txt',
                            'body' => [
                                'attachmentId' => $attachmentId,
                                'size' => 15000000, // 15MB
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actAsAI()
            ->getJson("/api/ai/gmail/messages/{$messageId}/attachments/{$attachmentId}");

        $response->assertStatus(413);
        $response->assertJson([
            'success' => false,
            'code' => 'ATTACHMENT_TOO_LARGE',
        ]);
    }

    public function test_can_read_nested_multipart_attachment()
    {
        $messageId = 'msg123';
        $attachmentId = 'att123_nested';
        $plainTextContent = 'Nested PDF Content Fake';
        $base64Content = str_replace(['+', '/'], ['-', '_'], base64_encode($plainTextContent));

        Http::fake([
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}*" => Http::response([
                'size' => strlen($plainTextContent),
                'data' => $base64Content
            ], 200),

            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response([
                'id' => $messageId,
                'payload' => [
                    'mimeType' => 'multipart/mixed',
                    'parts' => [
                        [
                            'partId' => '1',
                            'mimeType' => 'multipart/alternative',
                            'parts' => [
                                [
                                    'partId' => '1.1',
                                    'mimeType' => 'text/plain',
                                    'body' => [
                                        'size' => 10,
                                        'data' => 'SGVsbG8=' // Hello
                                    ]
                                ],
                                [
                                    'partId' => '1.2',
                                    'mimeType' => 'text/plain',
                                    'filename' => 'nested.txt',
                                    'body' => [
                                        'attachmentId' => $attachmentId,
                                        'size' => strlen($plainTextContent),
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actAsAI()
            ->getJson("/api/ai/gmail/messages/{$messageId}/attachments/{$attachmentId}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'attachment' => [
                    'message_id' => $messageId,
                    'attachment_id' => $attachmentId,
                    'filename' => 'nested.txt',
                    'mime_type' => 'text/plain',
                    'size' => strlen($plainTextContent)
                ]
            ]
        ]);
    }
}

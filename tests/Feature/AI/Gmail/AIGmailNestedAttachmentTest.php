<?php

namespace Tests\Feature\AI\Gmail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\Permission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AIGmailNestedAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Integration $integration;
    private ExternalIdentity $externalIdentity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-' . Str::uuid(),
        ]);

        $this->user = User::create([
            'name'     => 'Member User',
            'email'    => 'member@acme.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->organizations()->attach($this->organization->id);

        $this->integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'status' => 'connected',
            'config' => ['client_id' => 'test', 'client_secret' => 'test']
        ]);

        $this->externalIdentity = ExternalIdentity::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'integration_id' => $this->integration->id,
            'provider' => 'google_workspace',
            'external_id' => 'google-user-123',
            'primary_email' => 'test@example.com'
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('resolveAccessContext')
                 ->andReturn(new \App\Domain\Permissions\Contexts\AuthorizedAccessContext(
                     $this->organization,
                     $this->user,
                     $this->user,
                     ['gmail.attachments.download'],
                     $this->externalIdentity
                 ));
        });

        $this->mock(\App\Domain\Integrations\Services\GoogleTokenService::class, function ($mock) {
            $mock->shouldReceive('executeWithRetry')
                 ->andReturnUsing(function ($integration, $callback, $identity, $scopes) {
                     return $callback('dwd_token');
                 });
        });
    }

    public function test_find_nested_attachment()
    {
        $messageId = '19fc0144bf7f0eba';
        $attachmentId = 'ANGjdJ...nested';

        $gmailApiResponse = [
            'id' => $messageId,
            'threadId' => 'thread-123',
            'snippet' => 'Test',
            'payload' => [
                'partId' => '',
                'mimeType' => 'multipart/mixed',
                'filename' => '',
                'headers' => [],
                'body' => ['size' => 0],
                'parts' => [
                    [
                        'partId' => '0',
                        'mimeType' => 'multipart/alternative',
                        'filename' => '',
                        'headers' => [],
                        'body' => ['size' => 0],
                        'parts' => [
                            [
                                'partId' => '0.0',
                                'mimeType' => 'text/plain',
                                'filename' => '',
                                'headers' => [],
                                'body' => ['size' => 10, 'data' => 'dGVzdA==']
                            ],
                            [
                                'partId' => '0.1',
                                'mimeType' => 'text/html',
                                'filename' => '',
                                'headers' => [],
                                'body' => ['size' => 20, 'data' => 'PGI+dGVzdDwvYj4=']
                            ]
                        ]
                    ],
                    [
                        'partId' => '1',
                        'mimeType' => 'multipart/related',
                        'filename' => '',
                        'headers' => [],
                        'body' => ['size' => 0],
                        'parts' => [
                            [
                                'partId' => '1.0',
                                'mimeType' => 'application/pdf',
                                'filename' => '5636380881.pdf',
                                'headers' => [],
                                'body' => [
                                    'attachmentId' => $attachmentId,
                                    'size' => 86350
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            'https://www.googleapis.com/oauth2/v4/token' => Http::response(['access_token' => 'mocked_token']),
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mocked_token']),
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response($gmailApiResponse, 200),
        ]);

        config(['services.ai_gateway.token' => 'test-token']);

        // 1. Testa Ler Email
        $responseRead = $this->withHeaders([
                'Authorization' => 'Bearer test-token',
                'X-Organization-UUID' => $this->organization->uuid,
                'X-User-UUID' => $this->user->uuid,
                'X-Conversation-UUID' => 'conv-123',
            ])
            ->getJson("/api/ai/gmail/messages/{$messageId}");

        if ($responseRead->status() !== 200) {
            dd($responseRead->json());
        }
        $responseRead->assertStatus(200);
        $attachments = $responseRead->json('data.message.attachments');
        $this->assertCount(1, $attachments);
        $this->assertEquals($attachmentId, $attachments[0]['attachment_id']);

        // 2. Testa Gerar Link de Anexo
        $responseLink = $this->withHeaders([
                'Authorization' => 'Bearer test-token',
                'X-Organization-UUID' => $this->organization->uuid,
                'X-User-UUID' => $this->user->uuid,
                'X-Conversation-UUID' => 'conv-123',
            ])
            ->postJson("/api/ai/gmail/messages/{$messageId}/attachments/download-link");

        $responseLink->assertStatus(200);
        $this->assertTrue($responseLink->json('success'));
    }
}

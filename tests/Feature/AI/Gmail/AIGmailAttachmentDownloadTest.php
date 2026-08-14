<?php

namespace Tests\Feature\AI\Gmail;

use App\Domain\Identities\Models\ExternalIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Downloads\Models\TemporaryDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class AIGmailAttachmentDownloadTest extends TestCase
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
            'slug' => 'gmail.attachments.download',
            'name' => 'Gmail Download',
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

    public function test_can_generate_and_use_download_link()
    {
        $messageId = 'msg123';
        $attachmentId = 'att123';
        $content = 'Real PDF content binary format fake';
        $base64Content = str_replace(['+', '/'], ['-', '_'], base64_encode($content));

        Http::fake([
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/attachments/{$attachmentId}*" => Http::response([
                'size' => strlen($content),
                'data' => $base64Content
            ], 200),
            
            "https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}*" => Http::response([
                'id' => $messageId,
                'payload' => [
                    'mimeType' => 'multipart/mixed',
                    'parts' => [
                        [
                            'partId' => '1',
                            'mimeType' => 'application/pdf',
                            'filename' => 'invoice.pdf',
                            'body' => [
                                'attachmentId' => $attachmentId,
                                'size' => strlen($content),
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // 1. Gera o link via API AI
        $response = $this->actAsAI()
            ->postJson("/api/ai/gmail/messages/{$messageId}/attachments/{$attachmentId}/download-link");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'filename',
                'mime_type',
                'size',
                'download_url',
                'expires_at'
            ]
        ]);

        $downloadUrl = $response->json('data.download_url');
        
        $this->assertDatabaseHas('temporary_downloads', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
            'filename' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
        ]);
        
        $temporaryDownload = TemporaryDownload::first();
        $this->assertTrue($temporaryDownload->expires_at->isFuture());
        
        $payload = json_decode(Crypt::decrypt($temporaryDownload->payload), true);
        $this->assertEquals($messageId, $payload['message_id']);
        $this->assertEquals($attachmentId, $payload['attachment_id']);
        $this->assertEquals($this->externalIdentity->id, $payload['identity_id']);

        // 2. Faz o download via Web simulando a sessão do usuário Nodal
        // Extrai a rota a partir da URL gerada
        $path = parse_url($downloadUrl, PHP_URL_PATH);

        $downloadResponse = $this->actingAs($this->member)
                                 ->withSession(['active_organization_id' => $this->organization->id])
                                 ->get($path);

        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString('attachment; filename=', $downloadResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('invoice.pdf', $downloadResponse->headers->get('Content-Disposition'));
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
        $this->assertEquals($content, $downloadResponse->streamedContent());
    }

    public function test_rejects_download_from_other_organization()
    {
        $uuid = (string) Str::uuid();
        
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-' . Str::uuid(),
        ]);
        
        TemporaryDownload::create([
            'uuid' => $uuid,
            'organization_id' => $otherOrg->id, // Outra org
            'user_id' => $this->member->id,
            'provider' => 'google_workspace',
            'resource_type' => 'gmail_attachment',
            'payload' => Crypt::encrypt(json_encode(['message_id' => 'abc', 'attachment_id' => '123'])),
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'expires_at' => now()->addMinutes(10),
        ]);

        $downloadResponse = $this->actingAs($this->member)
                                 ->withSession(['active_organization_id' => $this->organization->id])
                                 ->get("/downloads/{$uuid}");

        $downloadResponse->assertStatus(403);
    }
    
    public function test_rejects_expired_link()
    {
        $uuid = (string) Str::uuid();
        
        TemporaryDownload::create([
            'uuid' => $uuid,
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
            'provider' => 'google_workspace',
            'resource_type' => 'gmail_attachment',
            'payload' => Crypt::encrypt(json_encode(['message_id' => 'abc', 'attachment_id' => '123'])),
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'expires_at' => now()->subMinutes(1),
        ]);

        $downloadResponse = $this->actingAs($this->member)
                                 ->withSession(['active_organization_id' => $this->organization->id])
                                 ->get("/downloads/{$uuid}");

        $downloadResponse->assertStatus(410);
    }
}

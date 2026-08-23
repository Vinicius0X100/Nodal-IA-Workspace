<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use App\Domain\AI\Models\Conversation;
use App\Domain\AI\Models\Message;
use App\Domain\AI\Models\MessageAttachment;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UploadAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai_gateway.token' => 'test-token']);
        Storage::fake('chat-attachments');
    }

    public function test_fails_without_conversation_context()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $organization->uuid,
            'X-User-UUID' => $user->uuid,
        ])->postJson('/api/v1/ai/resources/upload-attachment', [
            'attachment_uuid' => 'fake-uuid'
        ]);
        
        // As the ai group is under /api/ai/ and ai.gateway middleware
        // wait, prefix is 'ai', let me adjust URL
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $organization->uuid,
            'X-User-UUID' => $user->uuid,
        ])->postJson('/api/ai/resources/upload-attachment', [
            'attachment_uuid' => 'fake-uuid'
        ]);

        $response->assertStatus(400);
        $this->assertEquals('CONVERSATION_REQUIRED', $response->json('code'));
    }

    public function test_upload_resumable_success()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $organization->users()->attach($user->id); // if using Many-to-Many or just belongsTo

        $integration = Integration::factory()->create([
            'organization_id' => $organization->id,
            'provider' => 'google_workspace',
            'status' => 'connected',
            'is_enabled' => true
        ]);

        $conversation = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);
        
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
        ]);

        Storage::disk('chat-attachments')->put('fake_path.pdf', str_repeat('a', 1024 * 1024 * 9)); // 9MB file

        $attachment = MessageAttachment::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'storage_path' => 'fake_path.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 9 * 1024 * 1024,
            'expires_at' => now()->addDays(7),
            'status' => 'staged'
        ]);

        // Mock Google Token Service just allowing through if using Http faking
        Http::fake([
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable*' => Http::response('', 200, ['Location' => 'https://resumable.url']),
            'https://resumable.url*' => function ($request) {
                if ($request->header('Content-Length')[0] === (string)(8 * 1024 * 1024)) {
                    // First chunk 8MB
                    return Http::response('', 308);
                }
                if ($request->header('Content-Length')[0] === (string)(1 * 1024 * 1024)) {
                    // Second chunk 1MB
                    return Http::response([
                        'id' => 'google-file-123',
                        'name' => 'test.pdf',
                        'mimeType' => 'application/pdf',
                    ], 200);
                }
                return Http::response('', 500);
            }
        ]);

        // Since GoogleTokenService calls other things, we might just mock executeWithRetry or mock all HTTP calls.
        // Assuming executeWithRetry uses Http facade, Http::fake covers it.
        // Also need to fake token refresh if any. We will assume we have a valid token logic or mock the Identity.
        // To simplify, let's just let it run. It might try to call Google for token. 
        // If the token is not mocked, it throws. Let's mock the Token Service using app()->instance.
        $mockTokenService = \Mockery::mock(\App\Domain\Integrations\Services\GoogleTokenService::class);
        $mockTokenService->shouldReceive('executeWithRetry')->andReturnUsing(function($int, $closure) {
            return $closure('fake_token');
        });
        app()->instance(\App\Domain\Integrations\Services\GoogleTokenService::class, $mockTokenService);

        // Mock AuthorizationService
        $mockAuthService = \Mockery::mock(\App\Domain\Permissions\Services\AuthorizationService::class);
        $mockAuthService->shouldReceive('resolveAccessContext')->andReturn(
            new \App\Domain\Permissions\Contexts\AuthorizedAccessContext(null)
        );
        $mockAuthService->shouldReceive('canAccessResource')->andReturn(true);
        app()->instance(\App\Domain\Permissions\Services\AuthorizationService::class, $mockAuthService);
        
        $mockAuditAction = \Mockery::mock(\App\Domain\Audit\Actions\LogAuditAction::class);
        $mockAuditAction->shouldReceive('execute')->andReturn();
        app()->instance(\App\Domain\Audit\Actions\LogAuditAction::class, $mockAuditAction);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'X-Organization-UUID' => $organization->uuid,
            'X-User-UUID' => $user->uuid,
            'X-Conversation-UUID' => $conversation->uuid,
        ])->postJson('/api/ai/resources/upload-attachment', [
            'attachment_uuid' => $attachment->uuid,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('test.pdf', $response->json('data.name'));
        $this->assertDatabaseHas('integration_resources', [
            'external_id' => 'google-file-123',
            'name' => 'test.pdf'
        ]);
    }
}

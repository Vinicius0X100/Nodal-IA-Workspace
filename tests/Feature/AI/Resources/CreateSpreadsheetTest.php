<?php

namespace Tests\Feature\AI\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $user;
    private Integration  $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name'   => 'Test Corp',
            'slug'   => 'test-corp',
            'active' => true,
        ]);

        $this->user = User::create([
            'name'     => 'Regular User',
            'email'    => 'user@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($this->user->id, ['is_owner' => false]);

        $this->integration = Integration::create([
            'organization_id' => $this->organization->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google Workspace',
            'status' => 'connected',
            'access_token' => 'valid-token'
        ]);
        
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

        // Mock token service
        $mockTokenService = \Mockery::mock(\App\Domain\Integrations\Services\GoogleTokenService::class);
        $mockTokenService->shouldReceive('executeWithRetry')->andReturnUsing(function($int, $closure) {
            return $closure('fake_token');
        });
        $this->app->instance(\App\Domain\Integrations\Services\GoogleTokenService::class, $mockTokenService);

        $mockAuditAction = \Mockery::mock(\App\Domain\Audit\Actions\LogAuditAction::class);
        $mockAuditAction->shouldReceive('execute')->andReturn();
        $this->app->instance(\App\Domain\Audit\Actions\LogAuditAction::class, $mockAuditAction);
    }

    private function actAsAI()
    {
        config(['services.ai_gateway.token' => 'test-token']);
        return $this->withHeaders([
            'X-Organization-UUID' => $this->organization->uuid,
            'X-User-UUID'         => $this->user->uuid,
            'Authorization'       => 'Bearer test-token'
        ]);
    }

    public function test_creates_spreadsheet_without_parent_resource_uuid()
    {
        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => Http::response([
                'id' => 'google-file-123',
                'name' => 'Controle Financeiro',
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
            ], 200)
        ]);

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Controle Financeiro'
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Controle Financeiro',
                'type' => 'spreadsheet',
                'provider' => 'google_workspace',
                'parent_resource_uuid' => null
            ]
        ]);
        
        $this->assertArrayHasKey('resource_uuid', $response->json('data'));
        $this->assertArrayNotHasKey('external_id', $response->json('data'));
        $this->assertArrayNotHasKey('spreadsheet_id', $response->json('data'));

        $this->assertDatabaseHas('integration_resources', [
            'name' => 'Controle Financeiro',
            'external_id' => 'google-file-123',
            'resource_type' => 'spreadsheet',
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);
    }

    public function test_creates_spreadsheet_inside_valid_folder()
    {
        $folder = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'folder',
            'external_id' => 'parent-ext-123',
            'name' => 'My Folder',
            'mime_type' => 'application/vnd.google-apps.folder',
            'is_folder' => true,
        ]);

        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => function ($request) {
                $data = json_decode($request->body(), true);
                $this->assertEquals(['parent-ext-123'], $data['parents']);
                return Http::response([
                    'id' => 'google-file-456',
                    'name' => 'Planilha em Pasta',
                ], 200);
            }
        ]);

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Planilha em Pasta',
            'parent_resource_uuid' => $folder->uuid
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.parent_resource_uuid', $folder->uuid);
    }

    public function test_rejects_invalid_parent_resource_uuid()
    {
        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Teste',
            'parent_resource_uuid' => \Illuminate\Support\Str::uuid()->toString() // Not in DB
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'code' => 'RESOURCE_NOT_FOUND',
        ]);
        $this->assertStringContainsString('Pasta pai não encontrada', $response->json('message'));
    }

    public function test_rejects_parent_that_is_not_a_folder()
    {
        $document = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'document',
            'external_id' => 'doc-ext-123',
            'name' => 'My Doc',
            'mime_type' => 'application/vnd.google-apps.document',
            'is_folder' => false,
        ]);

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Teste',
            'parent_resource_uuid' => $document->uuid
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('O recurso pai especificado não é uma pasta', $response->json('message'));
    }

    public function test_prevents_using_resource_from_another_organization()
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'slug' => 'other-org',
            'active' => true,
        ]);

        $otherIntegration = Integration::create([
            'organization_id' => $otherOrg->id,
            'provider' => 'google_workspace',
            'display_name' => 'Other Google Workspace',
            'status' => 'connected',
        ]);

        $otherFolder = IntegrationResource::create([
            'integration_id' => $otherIntegration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'folder',
            'external_id' => 'other-parent-ext',
            'name' => 'Other Folder',
            'mime_type' => 'application/vnd.google-apps.folder',
            'is_folder' => true,
        ]);

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Teste Org Isolada',
            'parent_resource_uuid' => $otherFolder->uuid
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('Pasta pai não encontrada', $response->json('message'));
    }

    public function test_validates_lack_of_resources_write_permission()
    {
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('resolveAccessContext')->andThrow(new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão."));
        });

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Perm Negada'
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'code' => 'ACCESS_DENIED',
        ]);
    }

    public function test_rejects_when_user_lacks_access_to_parent_resource()
    {
        $folder = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'folder',
            'external_id' => 'parent-ext-restricted',
            'name' => 'Restricted Folder',
            'mime_type' => 'application/vnd.google-apps.folder',
            'is_folder' => true,
        ]);

        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(false); // No access to folder
        });

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'Teste Restrito',
            'parent_resource_uuid' => $folder->uuid
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'code' => 'ACCESS_DENIED',
        ]);
        $this->assertStringContainsString('Você não possui permissão para acessar a pasta pai', $response->json('message'));
    }

    public function test_fails_when_google_integration_is_missing()
    {
        $this->integration->delete();

        $response = $this->actAsAI()->postJson('/api/ai/resources/spreadsheets', [
            'name' => 'No Integration'
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Integração do Google Workspace não está ativa ou configurada', $response->json('message'));
    }
}

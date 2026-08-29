<?php

namespace Tests\Feature\AI\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateSpreadsheetValuesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User         $user;
    private Integration  $integration;
    private IntegrationResource $spreadsheet;

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

        $this->spreadsheet = IntegrationResource::create([
            'integration_id' => $this->integration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'spreadsheet',
            'external_id' => 'google-spreadsheet-ext-123',
            'name' => 'Test Spreadsheet',
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);
        
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(true);
        });

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

    public function test_updates_single_range()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123/values:batchUpdate' => function ($request) {
                $data = json_decode($request->body(), true);
                
                // Assert payload structure
                $this->assertEquals('USER_ENTERED', $data['valueInputOption']);
                $this->assertFalse($data['includeValuesInResponse']);
                $this->assertEquals('A1:B2', $data['data'][0]['range']);
                $this->assertEquals('ROWS', $data['data'][0]['majorDimension']);
                $this->assertEquals([['A', 'B'], ['1', '2']], $data['data'][0]['values']);

                return Http::response([
                    'spreadsheetId' => 'google-spreadsheet-ext-123',
                    'totalUpdatedCells' => 4,
                    'responses' => [
                        [
                            'spreadsheetId' => 'google-spreadsheet-ext-123',
                            'updatedRange' => 'Sheet1!A1:B2',
                            'updatedRows' => 2,
                            'updatedColumns' => 2,
                            'updatedCells' => 4
                        ]
                    ]
                ], 200);
            }
        ]);

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                [
                    'range' => 'A1:B2',
                    'values' => [
                        ['A', 'B'],
                        ['1', '2']
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'resource_uuid' => $this->spreadsheet->uuid,
                'total_updated_cells' => 4,
                'updated_ranges' => [
                    [
                        'range' => 'Sheet1!A1:B2',
                        'updated_rows' => 2,
                        'updated_columns' => 2,
                        'updated_cells' => 4
                    ]
                ]
            ]
        ]);
        
        $this->assertArrayNotHasKey('spreadsheetId', $response->json('data'));
        $this->assertArrayNotHasKey('external_id', $response->json('data'));
    }

    public function test_updates_multiple_ranges()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123/values:batchUpdate' => Http::response([
                'spreadsheetId' => 'google-spreadsheet-ext-123',
                'totalUpdatedCells' => 6,
                'responses' => [
                    [
                        'updatedRange' => 'Sheet1!A1:B1',
                        'updatedCells' => 2
                    ],
                    [
                        'updatedRange' => 'Sheet1!C1:F1',
                        'updatedCells' => 4
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                [
                    'range' => 'A1:B1',
                    'values' => [['A', 'B']]
                ],
                [
                    'range' => 'C1:F1',
                    'values' => [['C', 'D', 'E', 'F']]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_updated_cells', 6);
        $this->assertCount(2, $response->json('data.updated_ranges'));
    }

    public function test_rejects_invalid_resource_uuid()
    {
        $response = $this->actAsAI()->postJson('/api/ai/spreadsheets/' . \Illuminate\Support\Str::uuid()->toString() . '/values', [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'code' => 'RESOURCE_NOT_FOUND',
        ]);
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

        $otherSpreadsheet = IntegrationResource::create([
            'integration_id' => $otherIntegration->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'provider' => 'google_workspace',
            'resource_type' => 'spreadsheet',
            'external_id' => 'other-ext',
            'name' => 'Other File',
            'mime_type' => 'application/vnd.google-apps.spreadsheet',
            'is_folder' => false,
        ]);

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$otherSpreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(404);
    }

    public function test_rejects_resource_that_is_not_a_spreadsheet()
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

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$document->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('não é uma planilha', $response->json('message'));
    }

    public function test_validates_lack_of_resources_write_permission()
    {
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $mock->shouldReceive('resolveAccessContext')->andThrow(new \Illuminate\Auth\Access\AuthorizationException("Você não possui permissão."));
        });

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'code' => 'ACCESS_DENIED',
        ]);
    }

    public function test_rejects_when_user_lacks_access_to_specific_spreadsheet()
    {
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(false); // No access to specific resource
        });

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'code' => 'ACCESS_DENIED',
        ]);
    }

    public function test_fails_when_google_integration_is_missing()
    {
        $this->integration->delete();

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(403);
    }

    public function test_rejects_invalid_payload()
    {
        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1', 'values' => [['X', ['array_in_cell_invalid']]]]
            ]
        ]);

        $response->assertStatus(422); // Validation error
    }

    public function test_rejects_payload_above_limit()
    {
        $hugeValues = array_fill(0, 100, array_fill(0, 101, 'X')); // 10100 cells

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'A1:CX100', 'values' => $hugeValues]
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('updates');
    }

    public function test_google_api_error_for_invalid_range()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123/values:batchUpdate' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid data[0]: range'
                ]
            ], 400)
        ]);

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/values", [
            'updates' => [
                ['range' => 'INVALID_RANGE', 'values' => [['X']]]
            ]
        ]);

        $response->assertStatus(400); // Because Google returned 400
        $this->assertStringContainsString('Provider API Error', $response->json('message'));
    }
}

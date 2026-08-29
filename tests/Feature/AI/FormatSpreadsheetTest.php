<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Permissions\Services\AuthorizationService;

class FormatSpreadsheetTest extends TestCase
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
        $this->organization->users()->attach($this->user->id, ['is_owner' => true]);

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
            'organization_id' => $this->organization->id,
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

    public function test_can_format_spreadsheet_with_all_valid_operations()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123?fields=sheets(properties(sheetId,title,index))' => Http::response([
                'sheets' => [
                    [
                        'properties' => [
                            'sheetId' => 12345,
                            'title' => 'Página1',
                            'index' => 0
                        ]
                    ]
                ]
            ], 200),
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123:batchUpdate' => function ($request) {
                $payload = json_decode($request->body(), true);
                $this->assertCount(6, $payload['requests']);
                
                // Assert format_range repeatCell
                $this->assertArrayHasKey('repeatCell', $payload['requests'][0]);
                $this->assertEquals(12345, $payload['requests'][0]['repeatCell']['range']['sheetId']);
                
                // Assert number_format repeatCell
                $this->assertArrayHasKey('repeatCell', $payload['requests'][1]);
                $this->assertEquals('userEnteredFormat.numberFormat', $payload['requests'][1]['repeatCell']['fields']);

                // Assert freeze updateSheetProperties
                $this->assertArrayHasKey('updateSheetProperties', $payload['requests'][2]);
                $this->assertEquals(1, $payload['requests'][2]['updateSheetProperties']['properties']['gridProperties']['frozenRowCount']);

                // Assert autoResizeDimensions
                $this->assertArrayHasKey('autoResizeDimensions', $payload['requests'][3]);

                // Assert updateDimensionProperties
                $this->assertArrayHasKey('updateDimensionProperties', $payload['requests'][4]);

                // Assert borders
                $this->assertArrayHasKey('updateBorders', $payload['requests'][5]);

                return Http::response(['replies' => []], 200);
            }
        ]);

        $payload = [
            'operations' => [
                [
                    'type' => 'format_range',
                    'range' => 'A1:D1',
                    'format' => [
                        'bold' => true,
                        'background_color' => '#1F4E78'
                    ]
                ],
                [
                    'type' => 'number_format',
                    'range' => 'C2:D100',
                    'format' => 'CURRENCY_BRL'
                ],
                [
                    'type' => 'freeze',
                    'rows' => 1
                ],
                [
                    'type' => 'auto_resize_columns',
                    'range' => 'A:D'
                ],
                [
                    'type' => 'set_column_width',
                    'range' => 'E:E',
                    'width_px' => 150
                ],
                [
                    'type' => 'borders',
                    'range' => 'A1:E100',
                    'style' => 'SUBTLE'
                ]
            ]
        ];

        $response = $this->actAsAI()->postJson("/api/ai/spreadsheets/{$this->spreadsheet->uuid}/format", $payload); 
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals(6, $response->json('data.applied_operations'));
    }
}

<?php

namespace Tests\Feature\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Integrations\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReadSpreadsheetTest extends TestCase
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
            'access_token' => 'valid-token',
            'is_enabled' => true,
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
            $mock->shouldReceive('can')->andReturn(true); // canWrite = true
        });

        $mockTokenService = \Mockery::mock(\App\Domain\Integrations\Services\GoogleTokenService::class);
        $mockTokenService->shouldReceive('executeWithRetry')->andReturnUsing(function($int, $closure) {
            return $closure('fake_token');
        });
        $this->app->instance(\App\Domain\Integrations\Services\GoogleTokenService::class, $mockTokenService);
    }

    private function actAsUser()
    {
        session(['active_organization_id' => $this->organization->id]);
        return $this->actingAs($this->user);
    }

    private function getFakeGoogleResponse(string $sheetTitle = 'Página1', $hasData = true)
    {
        return [
            'spreadsheetId' => 'google-spreadsheet-ext-123',
            'sheets' => [
                [
                    'properties' => [
                        'title' => $sheetTitle,
                        'index' => 0,
                        'gridProperties' => [
                            'rowCount' => 1000,
                            'columnCount' => 26,
                            'frozenRowCount' => 1,
                            'frozenColumnCount' => 0
                        ]
                    ],
                    'data' => $hasData ? [
                        [
                            'rowData' => [
                                [
                                    'values' => [
                                        [
                                            'userEnteredValue' => ['stringValue' => 'Produto'],
                                            'effectiveValue' => ['stringValue' => 'Produto'],
                                            'formattedValue' => 'Produto',
                                            'effectiveFormat' => [
                                                'backgroundColor' => ['red' => 1, 'green' => 0, 'blue' => 0],
                                                'textFormat' => ['bold' => true],
                                                'numberFormat' => ['type' => 'TEXT']
                                            ]
                                        ],
                                        [
                                            'userEnteredValue' => ['formulaValue' => '=SUM(A1:A2)'],
                                            'effectiveValue' => ['numberValue' => 100],
                                            'formattedValue' => '100',
                                            'effectiveFormat' => []
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ] : []
                ]
            ]
        ];
    }

    public function test_reads_default_range_of_spreadsheet()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123*' => Http::response($this->getFakeGoogleResponse(), 200)
        ]);

        $response = $this->actAsUser()->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet");

        $response->assertStatus(200);
        
        $response->assertJson([
            'success' => true,
            'data' => [
                'resource_uuid' => $this->spreadsheet->uuid,
                'type' => 'spreadsheet',
                'active_sheet' => 'Página1',
                'requested_range' => 'A1:Z100', // Default range
                'capabilities' => [
                    'preview' => true,
                    'edit' => true,
                    'download' => true,
                ],
                'sheets' => [
                    [
                        'title' => 'Página1',
                        'row_count' => 1000,
                        'frozen_rows' => 1,
                    ]
                ],
                'grid' => [
                    'range' => 'Página1!A1:Z100',
                    'rows' => [
                        [
                            [
                                'value' => 'Produto',
                                'formatted_value' => 'Produto',
                                'formula' => null,
                                'format' => [
                                    'bold' => true,
                                    'background_color' => '#ff0000',
                                    'number_format' => ['type' => 'TEXT']
                                ]
                            ],
                            [
                                'value' => 100,
                                'formatted_value' => '100',
                                'formula' => '=SUM(A1:A2)',
                                'format' => [
                                    'bold' => false,
                                    'background_color' => null,
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Never expose internal IDs
        $this->assertArrayNotHasKey('spreadsheetId', $response->json('data'));
        $this->assertArrayNotHasKey('external_id', $response->json('data'));
    }

    public function test_reads_specific_sheet_and_range()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123*' => Http::response($this->getFakeGoogleResponse('Plan 2'), 200)
        ]);

        $response = $this->actAsUser()->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet?sheet=Plan 2&range=B2:C10");

        $response->assertStatus(200);
        $response->assertJsonPath('data.active_sheet', 'Plan 2');
        $response->assertJsonPath('data.requested_range', 'B2:C10');
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'ranges=') && str_contains(urldecode($request->url()), "'Plan 2'!B2:C10");
        });
    }

    public function test_isolates_tenant_properly()
    {
        $otherOrg = Organization::create([
            'name' => 'Other',
            'slug' => 'other',
            'active' => true,
        ]);
        
        session(['active_organization_id' => $otherOrg->id]);

        $otherOrg->users()->attach($this->user->id, ['is_owner' => false]);
        $response = $this->actingAs($this->user)->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet");
        
        $response->assertStatus(404);
        $response->assertJsonPath('code', 'INTERNAL_ERROR'); // Resource not found mapped to 404
    }

    public function test_rejects_when_not_spreadsheet()
    {
        $this->spreadsheet->update(['resource_type' => 'document']);

        $response = $this->actAsUser()->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet");

        $response->assertStatus(422);
        $this->assertStringContainsString('não é uma planilha', $response->json('message'));
    }

    public function test_capabilities_respect_write_access()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123*' => Http::response($this->getFakeGoogleResponse(), 200)
        ]);

        // Mock canWrite = false
        $this->mock(\App\Domain\Permissions\Services\AuthorizationService::class, function ($mock) {
            $contextMock = \Mockery::mock(\App\Domain\Permissions\Contexts\AuthorizedAccessContext::class);
            $contextMock->shouldReceive('getResolvedIdentity')->andReturn(null);

            $mock->shouldReceive('resolveAccessContext')->andReturn($contextMock);
            $mock->shouldReceive('canAccessResource')->andReturn(true);
            $mock->shouldReceive('can')->andReturn(false); // cannot write
        });

        $response = $this->actAsUser()->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet");

        $response->assertStatus(200);
        $response->assertJsonPath('data.capabilities.edit', false);
    }

    public function test_returns_404_if_sheet_not_found()
    {
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/google-spreadsheet-ext-123*' => Http::response($this->getFakeGoogleResponse('Valid', false), 200)
        ]);

        // Sheet sem data
        $response = $this->actAsUser()->getJson("/resources/{$this->spreadsheet->uuid}/spreadsheet?sheet=InvalidSheet");

        $response->assertStatus(404);
        $this->assertStringContainsString('Aba não encontrada', $response->json('message'));
    }
}

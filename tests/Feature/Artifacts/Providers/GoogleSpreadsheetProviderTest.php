<?php

namespace Tests\Feature\Artifacts\Providers;

use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCommitIdentity;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetCreateCommand;
use App\Domain\Artifacts\Providers\Google\GoogleSpreadsheetProvider;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Services\GoogleTokenService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class GoogleSpreadsheetProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_spreadsheet()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'access_token' => 'fake_token',
            'is_enabled' => true
        ]);
        
        $tokenService = Mockery::mock(GoogleTokenService::class);
        $tokenService->shouldReceive('executeWithRetry')
            ->andReturnUsing(function ($integration, $callback) {
                return $callback('fake_token');
            });
            
        $provider = new GoogleSpreadsheetProvider($tokenService);
        $provider->setContext($integration, null);
        
        Http::fake([
            'https://www.googleapis.com/drive/v3/files' => Http::response([
                'id' => 'abc_123_test'
            ], 200)
        ]);

        $cmd = new SpreadsheetCreateCommand('Test Sheet', new SpreadsheetCommitIdentity('uuid-123'));
        $resource = $provider->createSpreadsheet($cmd);
        
        $this->assertEquals('abc_123_test', $resource->externalId);
        $this->assertEquals('https://docs.google.com/spreadsheets/d/abc_123_test/edit', $resource->externalUrl);
        
        Http::assertSent(function (Request $request) {
            return $request->url() == 'https://www.googleapis.com/drive/v3/files' &&
                   $request->method() == 'POST' &&
                   $request['name'] == 'Test Sheet' &&
                   $request['appProperties']['nodal_commit_uuid'] == 'uuid-123';
        });
    }

    public function test_it_formats_empty_cells()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'access_token' => 'fake_token',
            'is_enabled' => true
        ]);
        
        $tokenService = Mockery::mock(GoogleTokenService::class);
        $tokenService->shouldReceive('executeWithRetry')
            ->andReturnUsing(fn($integration, $callback) => $callback('fake_token'));
            
        $provider = new GoogleSpreadsheetProvider($tokenService);
        $provider->setContext($integration, null);
        
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate' => Http::response([], 200)
        ]);

        $resource = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource('sheet_123', 'url');
        $handle = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle('uuid1', 0, 'Sheet1');
        
        // Format A1:Z100 background blue, even if there are no values mapped.
        $batch = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch($handle, [
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('background_color', 0, 99, 0, 25, ['key' => 'background_color', 'val' => '#0000FF'])
        ]);
        
        $provider->applyFormatting($resource, $batch);
        
        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $req = $data['requests'][0]['repeatCell'];
            
            return $request->url() == 'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate' &&
                   $req['range']['startRowIndex'] === 0 &&
                   $req['range']['endRowIndex'] === 100 &&
                   $req['range']['startColumnIndex'] === 0 &&
                   $req['range']['endColumnIndex'] === 26 &&
                   isset($req['cell']['userEnteredFormat']['backgroundColorStyle']);
        });
    }

    public function test_it_handles_overlapping_formats()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-2']);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'access_token' => 'fake_token',
            'is_enabled' => true
        ]);
        
        $tokenService = Mockery::mock(GoogleTokenService::class);
        $tokenService->shouldReceive('executeWithRetry')
            ->andReturnUsing(fn($integration, $callback) => $callback('fake_token'));
            
        $provider = new GoogleSpreadsheetProvider($tokenService);
        $provider->setContext($integration, null);
        
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate' => Http::response([], 200)
        ]);

        $resource = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource('sheet_123', 'url');
        $handle = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle('uuid1', 0, 'Sheet1');
        
        $batch = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch($handle, [
            // operation 1: A1:D10 background blue
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('background_color', 0, 9, 0, 3, ['key' => 'background_color', 'val' => '#0000FF']),
            // operation 1: A1:D10 bold true
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('text_style', 0, 9, 0, 3, ['key' => 'bold', 'val' => true]),
            // operation 2: B2 background yellow
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('background_color', 1, 1, 1, 1, ['key' => 'background_color', 'val' => '#FFFF00']),
        ]);
        
        $provider->applyFormatting($resource, $batch);
        
        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $reqs = $data['requests'];
            
            // Should preserve order
            return count($reqs) === 3 &&
                   $reqs[0]['repeatCell']['cell']['userEnteredFormat']['backgroundColorStyle']['rgbColor']['blue'] == 1 &&
                   $reqs[1]['repeatCell']['cell']['userEnteredFormat']['textFormat']['bold'] == true &&
                   $reqs[2]['repeatCell']['cell']['userEnteredFormat']['backgroundColorStyle']['rgbColor']['red'] == 1 &&
                   $reqs[2]['repeatCell']['range']['startRowIndex'] === 1; // B2
        });
    }

    public function test_it_formats_merges()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-3']);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'access_token' => 'fake_token',
            'is_enabled' => true
        ]);
        
        $tokenService = Mockery::mock(GoogleTokenService::class);
        $tokenService->shouldReceive('executeWithRetry')
            ->andReturnUsing(fn($integration, $callback) => $callback('fake_token'));
            
        $provider = new GoogleSpreadsheetProvider($tokenService);
        $provider->setContext($integration, null);
        
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate' => Http::response([], 200)
        ]);

        $resource = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource('sheet_123', 'url');
        $handle = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle('uuid1', 0, 'Sheet1');
        
        $batch = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatBatch($handle, [
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('merge', 0, 0, 0, 3, []), // A1:D1
            new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetFormatOperation('merge', 5, 10, 2, 4, [])  // C6:E11
        ]);
        
        $provider->applyFormatting($resource, $batch);
        
        Http::assertSent(function (Request $request) {
            $data = $request->data();
            $reqs = $data['requests'];
            
            return count($reqs) === 2 &&
                   isset($reqs[0]['mergeCells']) &&
                   $reqs[0]['mergeCells']['range']['startRowIndex'] === 0 &&
                   $reqs[0]['mergeCells']['range']['endRowIndex'] === 1 &&
                   $reqs[0]['mergeCells']['range']['startColumnIndex'] === 0 &&
                   $reqs[0]['mergeCells']['range']['endColumnIndex'] === 4 &&
                   isset($reqs[1]['mergeCells']) &&
                   $reqs[1]['mergeCells']['range']['startRowIndex'] === 5 &&
                   $reqs[1]['mergeCells']['range']['endRowIndex'] === 11 &&
                   $reqs[1]['mergeCells']['range']['startColumnIndex'] === 2 &&
                   $reqs[1]['mergeCells']['range']['endColumnIndex'] === 5;
        });
    }

    public function test_it_prepares_structure_with_crash_reconciliation()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-4']);
        $integration = Integration::create([
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Google',
            'status' => 'connected',
            'access_token' => 'fake_token',
            'is_enabled' => true
        ]);
        
        $tokenService = Mockery::mock(GoogleTokenService::class);
        $tokenService->shouldReceive('executeWithRetry')
            ->andReturnUsing(fn($integration, $callback) => $callback('fake_token'));
            
        $provider = new GoogleSpreadsheetProvider($tokenService);
        $provider->setContext($integration, null);
        
        // Simulating the metadata response with existing sheets
        Http::fake([
            'https://sheets.googleapis.com/v4/spreadsheets/sheet_123?fields=sheets(properties(sheetId,title,index))' => Http::response([
                'sheets' => [
                    [
                        'properties' => [
                            'sheetId' => 0,
                            'title' => 'Sheet1',
                            'index' => 0
                        ]
                    ],
                    [
                        'properties' => [
                            'sheetId' => 999,
                            'title' => 'Sheet2',
                            'index' => 1
                        ]
                    ]
                ]
            ], 200),
            'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate' => Http::response([], 200)
        ]);

        $resource = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource('sheet_123', 'url');
        
        // Let's request to create Sheet1 (rename), Sheet2 (already exists remotely), and Sheet3 (new)
        $batch = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureBatch(
            sheetsToCreate: [
                ['uuid' => 'uuid1', 'title' => 'Sheet1', 'index' => 0],
                ['uuid' => 'uuid2', 'title' => 'Sheet2', 'index' => 1], // Should be reconciled!
                ['uuid' => 'uuid3', 'title' => 'Sheet3', 'index' => 2], // Should trigger addSheet
            ],
            sheetsToRename: []
        );
        
        $result = $provider->prepareStructure($resource, $batch);
        
        $this->assertCount(3, $result->sheetHandles);
        $this->assertEquals(0, $result->sheetHandles[0]->externalSheetId);
        $this->assertEquals(999, $result->sheetHandles[1]->externalSheetId);
        $this->assertNotEquals(999, $result->sheetHandles[2]->externalSheetId); // Random new ID
        
        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://sheets.googleapis.com/v4/spreadsheets/sheet_123:batchUpdate') {
                return false;
            }
            $data = $request->data();
            $reqs = $data['requests'];
            
            // Should NOT have updateSheetProperties for Sheet1 because $idx===0 and count(sheets)>1
            // Wait, our logic says: if ($idx === 0 && count($sheets) === 1). So it won't rename!
            // It will just addSheet for Sheet3!
            return count($reqs) === 1 &&
                   isset($reqs[0]['addSheet']) &&
                   $reqs[0]['addSheet']['properties']['title'] === 'Sheet3';
        });
    }
}

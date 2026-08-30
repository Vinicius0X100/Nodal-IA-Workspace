<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\SpreadsheetDraftChunk;
use App\Domain\Artifacts\Models\SpreadsheetDraftFormat;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SpreadsheetDraftViewportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private ArtifactDraft $draft;
    private SpreadsheetDraftSheet $sheet;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->organization = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->user = User::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);
        $this->user->organizations()->attach($this->organization->id);
        
        $this->draft = ArtifactDraft::create([
            'uuid' => Str::uuid(),
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'type' => 'spreadsheet',
            'title' => 'Test Draft',
            'status' => 'draft',
            'schema_version' => 1,
            'revision' => 5, // We are at revision 5
        ]);
        
        $this->sheet = $this->draft->sheets()->create([
            'uuid' => Str::uuid(),
            'index' => 0,
            'title' => 'Planilha1',
            'properties_json' => ['frozen_rows' => 1],
            'dimensions_json' => ['column_widths' => ['A' => 150]]
        ]);
        
        // Chunk 0,0
        SpreadsheetDraftChunk::create([
            'uuid' => Str::uuid(),
            'sheet_id' => $this->sheet->id,
            'chunk_row' => 0,
            'chunk_column' => 0,
            'revision' => 2,
            'payload_json' => [
                '0' => [
                    '0' => ['value' => 'Header A'],
                    '1' => ['value' => 'Header B']
                ],
                '1' => [
                    '0' => ['value' => 10],
                    '1' => ['value' => 20]
                ]
            ]
        ]);
        
        // Chunk 0,1 (Column over 50, but let's just mock cross-chunk by putting a value in 0, 51)
        SpreadsheetDraftChunk::create([
            'uuid' => Str::uuid(),
            'sheet_id' => $this->sheet->id,
            'chunk_row' => 0,
            'chunk_column' => 1,
            'revision' => 3,
            'payload_json' => [
                '0' => [
                    '50' => ['value' => 'Far Away']
                ]
            ]
        ]);
        
        // Formats
        SpreadsheetDraftFormat::create([
            'uuid' => Str::uuid(),
            'sheet_id' => $this->sheet->id,
            'revision' => 1,
            'operation_index' => 0,
            'start_row' => 0,
            'end_row' => 0,
            'start_col' => 0,
            'end_col' => 1,
            'format_json' => ['bold' => true, 'background_color' => '#CCC']
        ]);
        
        SpreadsheetDraftFormat::create([
            'uuid' => Str::uuid(),
            'sheet_id' => $this->sheet->id,
            'revision' => 2,
            'operation_index' => 0,
            'start_row' => 0,
            'end_row' => 0,
            'start_col' => 0,
            'end_col' => 0,
            'format_json' => ['background_color' => '#FFF'] // Overrides A1 background
        ]);
        
        $this->actingAs($this->user);
        
        // In session for tests
        session(['active_organization_id' => $this->organization->id]);
    }

    public function test_it_returns_normalized_viewport()
    {
        $response = $this->withSession(['active_organization_id' => $this->organization->id])
            ->getJson("/artifacts/{$this->draft->uuid}/spreadsheet?sheet=Planilha1&range=A1:B2");
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Test Draft');
        $response->assertJsonPath('data.revision', 5);
        $response->assertJsonPath('data.viewport.frozen_rows', 1);
        
        $cells = $response->json('data.viewport.cells');
        
        // Should have 4 cells (0,0), (0,1), (1,0), (1,1)
        $this->assertCount(4, $cells);
        
        // Find A1 (0,0)
        $a1 = collect($cells)->where('row', 0)->where('column', 0)->first();
        $this->assertEquals('Header A', $a1['value']);
        $this->assertEquals(true, $a1['format']['bold']);
        $this->assertEquals('#FFF', $a1['format']['background_color']); // Precedence applied
        
        // Find B1 (0,1)
        $b1 = collect($cells)->where('row', 0)->where('column', 1)->first();
        $this->assertEquals('Header B', $b1['value']);
        $this->assertEquals(true, $b1['format']['bold']);
        $this->assertEquals('#CCC', $b1['format']['background_color']); // Did not get overridden
        
        // Find A2 (1,0)
        $a2 = collect($cells)->where('row', 1)->where('column', 0)->first();
        $this->assertEquals(10, $a2['value']);
        $this->assertEmpty($a2['format']);
    }
    
    public function test_it_handles_cross_chunk_viewport()
    {
        // Viewport reaching AZ (col 51)
        $response = $this->withSession(['active_organization_id' => $this->organization->id])
            ->getJson("/artifacts/{$this->draft->uuid}/spreadsheet?sheet=Planilha1&range=A1:AZ1");
        
        $response->assertStatus(200);
        $cells = collect($response->json('data.viewport.cells'));
        
        // Should have A1, B1, and AY1 (col 50)
        $far = $cells->where('row', 0)->where('column', 50)->first();
        $this->assertNotNull($far);
        $this->assertEquals('Far Away', $far['value']);
    }
}

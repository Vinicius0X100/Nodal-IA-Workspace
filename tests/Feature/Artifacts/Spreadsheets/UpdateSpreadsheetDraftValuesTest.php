<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactDraftChange;
use App\Domain\Artifacts\Models\SpreadsheetDraftChunk;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateSpreadsheetDraftValuesTest extends TestCase
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
            'revision' => 5,
        ]);
        
        $this->sheet = $this->draft->sheets()->create([
            'uuid' => Str::uuid(),
            'index' => 0,
            'title' => 'Planilha1',
        ]);
        
        SpreadsheetDraftChunk::create([
            'uuid' => Str::uuid(),
            'sheet_id' => $this->sheet->id,
            'chunk_row' => 0,
            'chunk_column' => 0,
            'revision' => 2,
            'payload_json' => [
                '0' => [
                    '0' => ['value' => 'Old Value']
                ]
            ]
        ]);
        
        $this->actingAs($this->user);
        session(['active_organization_id' => $this->organization->id]);
    }

    public function test_it_updates_values_and_increments_revision_once()
    {
        $payload = [
            'expected_revision' => 5,
            'sheet_uuid' => $this->sheet->uuid,
            'updates' => [
                [
                    'range' => 'A1:B2',
                    'values' => [
                        ['New Value', '=SUM(A1:A2)'],
                        [['clear' => true], 50]
                    ]
                ],
                [
                    'range' => 'AZ1:AZ1', // Crosses to chunk 0_1
                    'values' => [
                        ['Far Value']
                    ]
                ]
            ]
        ];

        $response = $this->patchJson("/artifacts/{$this->draft->uuid}/spreadsheet/values", $payload);
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.revision', 6);
        
        // Assert Draft updated
        $this->draft->refresh();
        $this->assertEquals(6, $this->draft->revision);
        
        // Assert Chunks
        $chunk00 = SpreadsheetDraftChunk::where('sheet_id', $this->sheet->id)->where('chunk_row', 0)->where('chunk_column', 0)->first();
        $this->assertEquals('New Value', $chunk00->payload_json['0']['0']['value']);
        $this->assertEquals('=SUM(A1:A2)', $chunk00->payload_json['0']['1']['formula']);
        $this->assertEquals(50, $chunk00->payload_json['1']['1']['value']);
        $this->assertArrayNotHasKey('0', $chunk00->payload_json['1']); // Cleared
        
        $chunk01 = SpreadsheetDraftChunk::where('sheet_id', $this->sheet->id)->where('chunk_row', 0)->where('chunk_column', 1)->first();
        $this->assertNotNull($chunk01);
        $this->assertEquals('Far Value', $chunk01->payload_json['0']['51']['value']); // AZ is col 51
        
        // Assert Journal
        $changes = ArtifactDraftChange::where('artifact_draft_id', $this->draft->id)->where('revision', 6)->get();
        $this->assertCount(2, $changes);
    }
    
    public function test_it_rejects_revision_conflict()
    {
        $payload = [
            'expected_revision' => 4, // Conflict
            'sheet_uuid' => $this->sheet->uuid,
            'updates' => [
                [
                    'range' => 'A1:A1',
                    'values' => [['Fail']]
                ]
            ]
        ];

        $response = $this->patchJson("/artifacts/{$this->draft->uuid}/spreadsheet/values", $payload);
        $response->assertStatus(409);
        $response->assertJsonPath('code', 'DRAFT_REVISION_CONFLICT');
    }
}

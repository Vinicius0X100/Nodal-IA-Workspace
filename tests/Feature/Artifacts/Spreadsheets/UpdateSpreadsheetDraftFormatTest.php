<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactDraftChange;
use App\Domain\Artifacts\Models\SpreadsheetDraftFormat;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateSpreadsheetDraftFormatTest extends TestCase
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
        
        $this->actingAs($this->user);
        session(['active_organization_id' => $this->organization->id]);
    }

    public function test_it_applies_formats_and_properties_atomically()
    {
        $payload = [
            'expected_revision' => 5,
            'sheet_uuid' => $this->sheet->uuid,
            'operations' => [
                [
                    'type' => 'format_range',
                    'range' => 'A1:A1',
                    'format' => ['bold' => true, 'background_color' => '#FFF']
                ],
                [
                    'type' => 'number_format',
                    'range' => 'B1:B10',
                    'format' => 'CURRENCY_BRL'
                ],
                [
                    'type' => 'freeze',
                    'rows' => 2,
                    'columns' => 1
                ]
            ]
        ];

        $response = $this->patchJson("/artifacts/{$this->draft->uuid}/spreadsheet/format", $payload);
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.revision', 6);
        
        $this->draft->refresh();
        $this->assertEquals(6, $this->draft->revision);
        
        $formats = SpreadsheetDraftFormat::where('sheet_id', $this->sheet->id)->orderBy('operation_index')->get();
        $this->assertCount(2, $formats);
        
        $this->assertEquals(6, $formats[0]->revision);
        $this->assertEquals(0, $formats[0]->operation_index);
        $this->assertTrue($formats[0]->format_json['bold']);
        
        $this->assertEquals(6, $formats[1]->revision);
        $this->assertEquals(1, $formats[1]->operation_index);
        $this->assertEquals('CURRENCY_BRL', $formats[1]->format_json['number_format']);
        
        $this->sheet->refresh();
        $this->assertEquals(2, $this->sheet->properties_json['frozen_rows']);
        $this->assertEquals(1, $this->sheet->properties_json['frozen_columns']);
        
        $changes = ArtifactDraftChange::where('artifact_draft_id', $this->draft->id)->where('revision', 6)->get();
        $this->assertCount(3, $changes);
    }
}

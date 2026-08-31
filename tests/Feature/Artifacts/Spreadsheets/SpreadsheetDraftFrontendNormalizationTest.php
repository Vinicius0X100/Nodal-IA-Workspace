<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpreadsheetDraftFrontendNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_viewport_returns_sheets_metadata_for_frontend()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org']);
        $user = User::create(['name' => 'U', 'email' => 'u@u.com', 'password' => '123', 'organization_id' => $org->id]);
        
        $draft = ArtifactDraft::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'status' => \App\Domain\Artifacts\Enums\ArtifactDraftStatus::DRAFT,
            'title' => 'Test',
            'revision' => 1
        ]);
        
        $sheet = $draft->sheets()->create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'title' => 'S1',
            'index' => 0
        ]);
        
        session(['active_organization_id' => $org->id]);
        $this->actingAs($user);
        
        $response = $this->getJson("/artifacts/{$draft->uuid}/spreadsheet");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'sheets' => [
                    '*' => [
                        'uuid', 'title', 'index', 'row_count', 'column_count'
                    ]
                ]
            ]
        ]);
        
        $this->assertEquals('S1', $response->json('data.sheets.0.title'));
    }
}

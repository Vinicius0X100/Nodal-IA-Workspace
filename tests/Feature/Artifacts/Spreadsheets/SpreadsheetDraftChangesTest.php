<?php

namespace Tests\Feature\Artifacts\Spreadsheets;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactDraftChange;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SpreadsheetDraftChangesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private ArtifactDraft $draft;

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
        
        $this->draft = ArtifactDraft::create([
            'uuid' => Str::uuid(),
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'type' => 'spreadsheet',
            'title' => 'Test Draft',
            'status' => 'draft',
            'schema_version' => 1,
            'revision' => 8,
        ]);
        
        ArtifactDraftChange::create([
            'uuid' => Str::uuid(),
            'artifact_draft_id' => $this->draft->id,
            'revision' => 5,
            'type' => 'values_updated',
            'range' => 'A1:A1'
        ]);
        
        ArtifactDraftChange::create([
            'uuid' => Str::uuid(),
            'artifact_draft_id' => $this->draft->id,
            'revision' => 6,
            'type' => 'format_applied',
            'range' => 'B2:B2'
        ]);
        
        ArtifactDraftChange::create([
            'uuid' => Str::uuid(),
            'artifact_draft_id' => $this->draft->id,
            'revision' => 7,
            'type' => 'values_updated',
            'range' => 'C3:C3'
        ]);
        
        $this->withoutMiddleware(\App\Http\Middleware\AIGatewayMiddleware::class);
    }

    public function test_it_returns_changes_since_revision()
    {
        $response = $this->getJson("/api/ai/artifacts/{$this->draft->uuid}/changes?since_revision=5", [
            'X-Organization-UUID' => $this->organization->uuid
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.from_revision', 5);
        $response->assertJsonPath('data.to_revision', 8);
        
        $changes = $response->json('data.changes');
        $this->assertCount(2, $changes);
        
        $this->assertEquals(6, $changes[0]['revision']);
        $this->assertEquals('format_applied', $changes[0]['type']);
        $this->assertEquals('B2:B2', $changes[0]['range']);
        
        $this->assertEquals(7, $changes[1]['revision']);
        $this->assertEquals('values_updated', $changes[1]['type']);
    }
}

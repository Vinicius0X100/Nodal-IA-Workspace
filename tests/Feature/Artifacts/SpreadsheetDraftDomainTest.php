<?php

namespace Tests\Feature\Artifacts;

use App\Domain\Artifacts\Enums\ArtifactDraftStatus;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use App\Domain\Artifacts\Repositories\SpreadsheetDraftRepository;
use App\Domain\Artifacts\Services\ArtifactDraftService;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SpreadsheetDraftDomainTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private ArtifactDraft $draft;
    private SpreadsheetDraftSheet $sheet;
    private SpreadsheetDraftRepository $repository;
    private ArtifactDraftService $service;

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
            'status' => ArtifactDraftStatus::DRAFT,
            'schema_version' => 1,
            'revision' => 1,
        ]);
        
        $this->sheet = $this->draft->sheets()->create([
            'uuid' => Str::uuid(),
            'index' => 0,
            'title' => 'Sheet1',
        ]);
        
        $this->repository = new SpreadsheetDraftRepository();
        $this->service = new ArtifactDraftService();
    }

    public function test_it_upserts_and_clears_chunk_payloads_correctly()
    {
        // Upsert standard values
        $this->repository->upsertChunkPayload($this->sheet, 0, 0, [
            0 => [
                0 => ['value' => 'Header A'],
                1 => ['value' => 'Header B']
            ],
            1 => [
                0 => ['value' => 10],
                1 => ['value' => 20]
            ]
        ]);
        
        $chunk = $this->sheet->chunks()->where('chunk_row', 0)->where('chunk_column', 0)->first();
        $this->assertNotNull($chunk);
        $this->assertEquals('Header A', $chunk->payload_json['0']['0']['value']);
        $this->assertEquals(20, $chunk->payload_json['1']['1']['value']);
        
        // Semantic Clear - partially empty
        $this->repository->upsertChunkPayload($this->sheet, 0, 0, [
            1 => [
                1 => ['clear' => true]
            ]
        ]);
        
        $chunk->refresh();
        $this->assertArrayHasKey('0', $chunk->payload_json['1']);
        $this->assertArrayNotHasKey('1', $chunk->payload_json['1']);
        
        // Semantic Clear - fully empty row 1 and row 0
        $this->repository->upsertChunkPayload($this->sheet, 0, 0, [
            1 => [
                0 => ['clear' => true]
            ],
            0 => [
                0 => ['clear' => true],
                1 => ['clear' => true]
            ]
        ]);
        
        // Chunk should be completely deleted since it's empty
        $chunk = $this->sheet->chunks()->where('chunk_row', 0)->where('chunk_column', 0)->first();
        $this->assertNull($chunk);
    }

    public function test_it_enforces_optimistic_concurrency_revision()
    {
        // Success
        $this->repository->incrementRevision($this->draft, 1);
        $this->assertEquals(2, $this->draft->revision);
        
        // Conflict
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("DRAFT_REVISION_CONFLICT");
        $this->repository->incrementRevision($this->draft, 1);
    }
    
    public function test_it_adds_format_rules_with_atomic_precedence()
    {
        $this->repository->addFormatRule($this->sheet, 5, 0, 100, 0, 10, ['bold' => true]);
        $this->repository->addFormatRule($this->sheet, 5, 0, null, 0, null, ['background' => '#FFF']);
        $this->repository->addFormatRule($this->sheet, 6, 1, 1, 1, 1, ['italic' => true]);
        
        $formats = $this->repository->getFormats($this->sheet);
        $this->assertCount(3, $formats);
        
        // Format 1: Revision 5, index 0
        $this->assertEquals(5, $formats[0]->revision);
        $this->assertEquals(0, $formats[0]->operation_index);
        
        // Format 2: Revision 5, index 1
        $this->assertEquals(5, $formats[1]->revision);
        $this->assertEquals(1, $formats[1]->operation_index);
        
        // Format 3: Revision 6, index 0
        $this->assertEquals(6, $formats[2]->revision);
        $this->assertEquals(0, $formats[2]->operation_index);
    }
    
    public function test_it_logs_changes_deterministically()
    {
        $this->repository->logChange($this->draft, 2, 'values_updated', 'A1:B2', null, $this->sheet);
        $change = \App\Domain\Artifacts\Models\ArtifactDraftChange::first();
        
        $this->assertNotNull($change);
        $this->assertEquals(2, $change->revision);
        $this->assertEquals('values_updated', $change->type);
        $this->assertEquals('A1:B2', $change->range);
        $this->assertEquals($this->sheet->uuid, $change->sheet_uuid);
    }

    public function test_it_transitions_state_machine_to_committing()
    {
        $lockedDraft = $this->service->transitionToCommitting($this->draft);
        $this->assertEquals(ArtifactDraftStatus::COMMITTING, $lockedDraft->status);
        
        // Cannot edit committing
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("ARTIFACT_DRAFT_NOT_EDITABLE");
        $this->service->transitionToCommitting($lockedDraft);
    }
}

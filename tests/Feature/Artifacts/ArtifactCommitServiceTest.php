<?php

namespace Tests\Feature\Artifacts;

use Tests\TestCase;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Services\ArtifactCommitService;
use App\Jobs\MaterializeArtifactDraftJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArtifactCommitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_commit_attempt_and_dispatches_job()
    {
        Queue::fake();
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        $draft = ArtifactDraft::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'draft',
            'revision' => 10,
        ]);

        $service = new ArtifactCommitService();
        $result = $service->commit($draft->uuid, $org->id);

        $this->assertEquals('committing', $result['status']);
        $this->assertNotNull($result['commit_uuid']);
        
        $this->assertDatabaseHas('artifact_commit_attempts', [
            'artifact_draft_id' => $draft->id,
            'status' => 'pending',
            'current_stage' => 'preflight',
            'source_revision' => 10,
        ]);

        $this->assertDatabaseHas('artifact_drafts', [
            'id' => $draft->id,
            'status' => 'committing'
        ]);

        Queue::assertPushed(MaterializeArtifactDraftJob::class);
    }

    public function test_it_returns_existing_commit_if_committing()
    {
        Queue::fake();
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        $draft = ArtifactDraft::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'committing',
            'revision' => 10,
        ]);

        $attempt = ArtifactCommitAttempt::create([
            'commit_uuid' => 'fake-uuid',
            'artifact_draft_id' => $draft->id,
            'source_revision' => 10,
            'provider' => 'google_workspace',
            'status' => 'working',
            'current_stage' => 'preflight'
        ]);

        $service = new ArtifactCommitService();
        $result = $service->commit($draft->uuid, $org->id);

        $this->assertEquals('committing', $result['status']);
        $this->assertEquals('fake-uuid', $result['commit_uuid']);
        
        Queue::assertNothingPushed(); // Did not dispatch again
    }

    public function test_it_redispatches_pending_attempt_on_commit()
    {
        Queue::fake();
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-2']);
        $draft = ArtifactDraft::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'committing',
            'revision' => 10,
        ]);

        $attempt = ArtifactCommitAttempt::create([
            'commit_uuid' => 'fake-uuid-pending',
            'artifact_draft_id' => $draft->id,
            'source_revision' => 10,
            'provider' => 'google_workspace',
            'status' => 'pending', // Pending triggers redispatch!
            'current_stage' => 'preflight'
        ]);

        $service = new ArtifactCommitService();
        $result = $service->commit($draft->uuid, $org->id);

        $this->assertEquals('committing', $result['status']);
        $this->assertEquals('fake-uuid-pending', $result['commit_uuid']);
        
        Queue::assertPushed(MaterializeArtifactDraftJob::class);
    }
}

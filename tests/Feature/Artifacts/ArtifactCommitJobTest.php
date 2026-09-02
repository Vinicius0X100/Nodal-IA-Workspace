<?php

namespace Tests\Feature\Artifacts;

use Tests\TestCase;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Jobs\MaterializeArtifactDraftJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;

class ArtifactCommitJobTest extends TestCase
{
    use RefreshDatabase;

    private function createAttempt(): ArtifactCommitAttempt
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        
        \App\Domain\Integrations\Models\Integration::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'provider' => 'google_workspace',
            'display_name' => 'Test',
            'status' => 'active',
            'is_enabled' => true,
        ]);
        
        $draft = ArtifactDraft::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $org->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'schema_version' => 1,
            'revision' => 1,
        ]);

        return ArtifactCommitAttempt::create([
            'commit_uuid' => (string) Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'source_revision' => 1,
            'provider' => 'google_workspace',
            'status' => 'pending',
            'current_stage' => 'preflight',
            'attempt_number' => 1,
        ]);
    }

    // =========================================================================
    // Tests mandated by the capabilities() contract fix — Phase 5C
    // =========================================================================

    /**
     * A. Provider contract: PreflightStage must call capabilities(), not getCapabilities().
     *    This test uses the real PreflightValidator mock but proves capabilities() is honoured.
     */
    public function test_preflight_stage_calls_capabilities_not_getCapabilities(): void
    {
        $capabilitiesDto = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(
            true, true, true, true, true, true, true, 50, 50, 50, 50, 1000
        );

        $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
        $providerMock->shouldReceive('capabilities')->once()->andReturn($capabilitiesDto);
        $providerMock->shouldNotReceive('getCapabilities');

        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) use ($providerMock) {
            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $this->mock(\App\Domain\Artifacts\Providers\Materialization\SpreadsheetPreflightValidator::class, function ($mock) {
            $mock->shouldReceive('validate')->once()->andReturn(true);
        });

        $attempt = $this->createAttempt();
        $job = new MaterializeArtifactDraftJob($attempt->id);
        
        $job->handle();

        $this->assertEquals('create_file', $attempt->fresh()->current_stage);
    }

    /**
     * C. Preflight failure propagates: Attempt -> failed, Draft -> failed.
     *    provider_external_id must remain null (no external side effect).
     */
    public function test_preflight_failure_marks_both_attempt_and_draft_as_failed(): void
    {
        $capabilitiesDto = new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(
            true, true, true, true, true, true, true, 50, 50, 50, 50, 1000
        );

        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) use ($capabilitiesDto) {
            $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
            $providerMock->shouldReceive('capabilities')->andReturn($capabilitiesDto);
            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $this->mock(\App\Domain\Artifacts\Providers\Materialization\SpreadsheetPreflightValidator::class, function ($mock) {
            $mock->shouldReceive('validate')->andThrow(
                new \App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnsupportedOperationException('Unsupported')
            );
        });

        $attempt = $this->createAttempt();
        $job = new MaterializeArtifactDraftJob($attempt->id);
        $job->handle();

        $freshAttempt = $attempt->fresh();
        $freshDraft   = $freshAttempt->artifactDraft;

        $this->assertEquals('failed', $freshAttempt->status);
        $this->assertNull($freshAttempt->provider_external_id);
        $this->assertEquals(\App\Domain\Artifacts\Enums\ArtifactDraftStatus::FAILED, $freshDraft->status);
    }

    /**
     * E. Retry from failed state (preflight, no external resource):
     *    POST commit must transition Draft FAILED -> COMMITTING and create a new Attempt.
     */
    public function test_commit_service_retries_from_failed_draft_with_no_external_resource(): void
    {
        Queue::fake();

        $org   = \App\Domain\Organizations\Models\Organization::create(['name' => 'Retry Org', 'slug' => 'retry-org']);
        $draft = ArtifactDraft::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $org->id,
            'type'            => 'spreadsheet',
            'title'           => 'Retry Test',
            'status'          => \App\Domain\Artifacts\Enums\ArtifactDraftStatus::FAILED,
            'schema_version'  => 1,
            'revision'        => 1,
        ]);
        ArtifactCommitAttempt::create([
            'commit_uuid'       => (string) \Illuminate\Support\Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'source_revision'   => 1,
            'provider'          => 'google_workspace',
            'status'            => 'failed',
            'current_stage'     => 'preflight',
            'attempt_number'    => 1,
            'error_payload'     => ['message' => 'capabilities error', 'code' => 0, 'stage' => 'preflight'],
        ]);

        $service = app(\App\Domain\Artifacts\Services\ArtifactCommitService::class);
        $result  = $service->commit($draft->uuid, $org->id);

        $this->assertEquals('committing', $result['status']);
        $this->assertNotNull($result['commit_uuid']);
        $this->assertEquals(\App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTING, $draft->fresh()->status);
        $this->assertEquals(2, $draft->commitAttempts()->count());
        Queue::assertPushed(\App\Jobs\MaterializeArtifactDraftJob::class);
    }
}

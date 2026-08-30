<?php

namespace Tests\Feature\Artifacts;

use Tests\TestCase;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Models\ArtifactCommitAttempt;
use App\Domain\Artifacts\Models\SpreadsheetDraftSheet;
use App\Jobs\MaterializeArtifactDraftJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Domain\Resources\Models\IntegrationResource;
use Illuminate\Support\Facades\Queue;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetStructureResult;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderSheetHandle;

class ArtifactCommitJobTest extends TestCase
{
    use RefreshDatabase;

    private function createAttempt(): ArtifactCommitAttempt
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        
        $integration = \App\Domain\Integrations\Models\Integration::create([
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
            'status' => \App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTING,
            'revision' => 10,
        ]);

        SpreadsheetDraftSheet::create([
            'uuid' => (string) Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'index' => 0,
            'title' => 'Sheet1',
        ]);

        return ArtifactCommitAttempt::create([
            'commit_uuid' => (string) Str::uuid(),
            'artifact_draft_id' => $draft->id,
            'source_revision' => 10,
            'provider' => 'google_workspace',
            'status' => 'pending',
            'current_stage' => 'preflight'
        ]);
    }

    public function test_finalize_duplicate_protection()
    {
        // Mock all provider calls to do nothing
        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) {
            $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
            $providerMock->shouldReceive('getCapabilities')->andReturn(new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 50, 50, 50, 50, 1000));
            $providerMock->shouldReceive('findByCommitKey')->andReturn(null);
            $providerMock->shouldReceive('createSpreadsheet')->andReturn(new SpreadsheetProviderResource('ext-123', 'url'));
            
            $result = new SpreadsheetStructureResult([
                'fake-uuid' => new SpreadsheetProviderSheetHandle('fake-uuid', '0', 'Sheet1')
            ]);
            $providerMock->shouldReceive('prepareStructure')->andReturn($result);
            $providerMock->shouldReceive('writeValues')->andReturnNull();
            $providerMock->shouldReceive('applyFormatting')->andReturnNull();

            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $attempt = $this->createAttempt();
        $attempt->update([
            'current_stage' => 'finalize',
            'provider_external_id' => 'ext-123'
        ]);

        $integration = \App\Domain\Integrations\Models\Integration::where('organization_id', $attempt->artifactDraft->organization_id)
            ->where('provider', 'google_workspace')
            ->first();

        // Let's create the IntegrationResource manually to simulate a crash where resource is created but draft is not updated
        IntegrationResource::create([
            'uuid' => (string) Str::uuid(),
            'integration_id' => $integration->id,
            'external_id' => 'ext-123',
            'provider' => \App\Domain\Resources\Enums\Provider::GOOGLE_WORKSPACE,
            'resource_type' => \App\Domain\Resources\Enums\ResourceType::from('spreadsheet'),
            'name' => 'Test',
            'metadata_json' => [
                'source_commit_uuid' => $attempt->commit_uuid
            ]
        ]);

        $job = new MaterializeArtifactDraftJob($attempt->id);
        $job->handle();
        
        $this->assertEquals('completed', $attempt->fresh()->current_stage);
        $this->assertEquals('succeeded', $attempt->fresh()->status);
        $this->assertEquals(\App\Domain\Artifacts\Enums\ArtifactDraftStatus::COMMITTED, $attempt->artifactDraft->fresh()->status);
        
        $this->assertEquals(1, IntegrationResource::count());
    }

    public function test_preflight_fails_fast()
    {
        // Preflight validator will fail if dimensions are requested but provider has unsupported operation.
        // We can just throw SpreadsheetProviderUnsupportedOperationException in mock
        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) {
            $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
            $providerMock->shouldReceive('getCapabilities')->andReturn(new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 50, 50, 50, 50, 1000));
            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $this->mock(\App\Domain\Artifacts\Providers\SpreadsheetPreflightValidator::class, function ($mock) {
            $mock->shouldReceive('validate')->andThrow(new \App\Domain\Artifacts\Providers\Exceptions\SpreadsheetProviderUnsupportedOperationException("Unsupported"));
        });

        $attempt = $this->createAttempt();

        $job = new MaterializeArtifactDraftJob($attempt->id);
        $job->handle();

        $this->assertEquals('failed', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->error_payload);
    }
    
    public function test_prepare_structure_crash_safety()
    {
        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) {
            $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
            $providerMock->shouldReceive('getCapabilities')->andReturn(new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 50, 50, 50, 50, 1000));
            
            // Mock provider returning 2 sheet handles
            $result = new SpreadsheetStructureResult([
                'sheet-1-uuid' => new SpreadsheetProviderSheetHandle('sheet-1-uuid', 'google-id-1', 'Sheet1'),
                'sheet-2-uuid' => new SpreadsheetProviderSheetHandle('sheet-2-uuid', 'google-id-2', 'Sheet2'),
            ]);
            $providerMock->shouldReceive('prepareStructure')->andReturn($result);

            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface::class, function ($mock) {
            $mock->shouldReceive('iterateValueChunks')->andReturn(collect([]));
            $mock->shouldReceive('iterateFormats')->andReturn(collect([]));
            $mock->shouldReceive('getMerges')->andReturn(collect([]));
        });

        $this->mock(\App\Domain\Artifacts\Providers\SpreadsheetBatchPlanner::class, function ($mock) {
            $mock->shouldReceive('planValues')->andReturn([]);
            $mock->shouldReceive('planFormatting')->andReturn([]);
        });

        $attempt = $this->createAttempt();
        $attempt->update(['current_stage' => 'prepare_structure', 'provider_external_id' => 'ext-123']);
        
        SpreadsheetDraftSheet::create([
            'uuid' => 'sheet-2-uuid',
            'artifact_draft_id' => $attempt->artifact_draft_id,
            'index' => 1,
            'title' => 'Sheet2',
        ]);

        $job = new MaterializeArtifactDraftJob($attempt->id);
        $job->handle();

        $this->assertEquals('completed', $attempt->fresh()->current_stage);
        $this->assertEquals(2, $attempt->sheetMappings()->count());
    }

    public function test_create_file_crash_reconciliation()
    {
        $this->mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface::class, function ($mock) {
            $providerMock = \Mockery::mock(\App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderInterface::class);
            $providerMock->shouldReceive('getCapabilities')->andReturn(new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 50, 50, 50, 50, 1000));
            // Return existing ID on findByCommitKey!
            $providerMock->shouldReceive('findByCommitKey')->andReturn(new \App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderResource('existing-ext-id', 'url'));
            // Create should NOT be called
            $providerMock->shouldNotReceive('createSpreadsheet');

            $mock->shouldReceive('resolve')->andReturn($providerMock);
        });

        $attempt = $this->createAttempt();
        $attempt->update(['current_stage' => 'create_file']);

        $job = new MaterializeArtifactDraftJob($attempt->id);
        $job->handle();

        // Since other mocks aren't here for subsequent stages, it will crash on prepare_structure, which means current_stage is prepare_structure
        $this->assertEquals('prepare_structure', $attempt->fresh()->current_stage);
        $this->assertEquals('existing-ext-id', $attempt->fresh()->provider_external_id);
    }
}

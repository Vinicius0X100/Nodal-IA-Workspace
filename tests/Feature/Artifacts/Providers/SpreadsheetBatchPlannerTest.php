<?php

namespace Tests\Feature\Artifacts\Providers;

use App\Domain\Artifacts\Models\ArtifactDraft;
use App\Domain\Artifacts\Providers\DTOs\SpreadsheetProviderCapabilities;
use App\Domain\Artifacts\Providers\Materialization\SpreadsheetBatchPlanner;
use App\Domain\Artifacts\Providers\Materialization\SpreadsheetMaterializationReader;
use App\Domain\Artifacts\Services\SpreadsheetViewportService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpreadsheetBatchPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_plans_values_correctly()
    {
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-1']);
        $user = User::create(['organization_id' => $org->id, 'name' => 'User', 'email' => 'u1@ex.com', 'password' => '123']);

        $draft = ArtifactDraft::create([
            'uuid' => Str::uuid(),
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'spreadsheet',
            'title' => 'Test',
            'status' => 'draft',
            'schema_version' => 1,
            'revision' => 1,
        ]);
        
        $sheetUuid = Str::uuid();
        $sheet = $draft->sheets()->create(['uuid' => $sheetUuid, 'index' => 0, 'title' => 'Sheet 1']);
        
        // Chunk 0x0
        $sheet->chunks()->create([
            'uuid' => Str::uuid(),
            'chunk_row' => 0,
            'chunk_column' => 0,
            'payload_json' => [
                '0' => [
                    '0' => ['value' => 'A1'],
                    '1' => ['value' => 'B1'],
                ],
                '1' => [
                    '0' => ['formula' => '=A1+B1'],
                ]
            ]
        ]);
        
        $viewportService = $this->app->make(SpreadsheetViewportService::class);
        $reader = new SpreadsheetMaterializationReader($viewportService);
        $planner = new SpreadsheetBatchPlanner($reader);
        
        $caps = new SpreadsheetProviderCapabilities(true, true, true, true, true, true, true, 500, 10, 10, 10, 1024);
        
        $generator = $planner->planValues($draft, $sheetUuid, 12345, $caps);
        
        $batches = [];
        foreach ($generator as $batch) {
            $batches[] = $batch;
        }
        
        $this->assertCount(1, $batches);
        $this->assertCount(1, $batches[0]->ranges);
        
        $range = $batches[0]->ranges[0];
        $this->assertEquals(0, $range->startRow);
        $this->assertEquals(0, $range->startCol);
        $this->assertEquals(1, $range->endRow); // Max row is 1
        $this->assertEquals(1, $range->endCol); // Max col is 1
        
        // Ensure values are structured correctly
        $this->assertEquals('A1', $range->values[0][0]);
        $this->assertEquals('B1', $range->values[0][1]);
        $this->assertEquals('=A1+B1', $range->values[1][0]);
        $this->assertNull($range->values[1][1]);
    }
}

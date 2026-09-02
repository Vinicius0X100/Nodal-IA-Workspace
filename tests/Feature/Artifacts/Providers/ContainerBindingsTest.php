<?php

namespace Tests\Feature\Artifacts\Providers;

use Tests\TestCase;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetProviderResolverInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetMaterializationReaderInterface;
use App\Domain\Artifacts\Providers\Contracts\SpreadsheetBatchPlannerInterface;
use App\Domain\Artifacts\Providers\SpreadsheetProviderResolver;
use App\Domain\Artifacts\Providers\Materialization\SpreadsheetMaterializationReader;
use App\Domain\Artifacts\Providers\Materialization\SpreadsheetBatchPlanner;
use App\Domain\Artifacts\Jobs\Stages\PreflightStage;
use App\Domain\Artifacts\Jobs\Stages\CreateFileStage;
use App\Domain\Artifacts\Jobs\Stages\PrepareStructureStage;
use App\Domain\Artifacts\Jobs\Stages\WriteValuesStage;
use App\Domain\Artifacts\Jobs\Stages\ApplyFormatsStage;

/**
 * Container binding tests for the Phase 4 Provider Abstraction.
 *
 * These tests MUST fail if any interface binding disappears from AppServiceProvider.
 * They do NOT mock the bindings being tested.
 */
class ContainerBindingsTest extends TestCase
{
    // --- Interface -> Concrete resolution -----------------------------------

    public function test_resolver_interface_resolves_to_concrete_class(): void
    {
        $instance = $this->app->make(SpreadsheetProviderResolverInterface::class);

        $this->assertInstanceOf(SpreadsheetProviderResolver::class, $instance);
    }

    public function test_materialization_reader_interface_resolves_to_concrete_class(): void
    {
        $instance = $this->app->make(SpreadsheetMaterializationReaderInterface::class);

        $this->assertInstanceOf(SpreadsheetMaterializationReader::class, $instance);
    }

    public function test_batch_planner_interface_resolves_to_concrete_class(): void
    {
        $instance = $this->app->make(SpreadsheetBatchPlannerInterface::class);

        $this->assertInstanceOf(SpreadsheetBatchPlanner::class, $instance);
    }

    // --- All Stages resolve (no "not instantiable" exception) ---------------

    public function test_preflight_stage_resolves_from_container(): void
    {
        $stage = $this->app->make(PreflightStage::class);

        $this->assertInstanceOf(PreflightStage::class, $stage);
    }

    public function test_create_file_stage_resolves_from_container(): void
    {
        $stage = $this->app->make(CreateFileStage::class);

        $this->assertInstanceOf(CreateFileStage::class, $stage);
    }

    public function test_prepare_structure_stage_resolves_from_container(): void
    {
        $stage = $this->app->make(PrepareStructureStage::class);

        $this->assertInstanceOf(PrepareStructureStage::class, $stage);
    }

    public function test_write_values_stage_resolves_from_container(): void
    {
        $stage = $this->app->make(WriteValuesStage::class);

        $this->assertInstanceOf(WriteValuesStage::class, $stage);
    }

    public function test_apply_formats_stage_resolves_from_container(): void
    {
        $stage = $this->app->make(ApplyFormatsStage::class);

        $this->assertInstanceOf(ApplyFormatsStage::class, $stage);
    }
}

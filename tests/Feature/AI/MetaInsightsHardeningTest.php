<?php

namespace Tests\Feature\AI;

use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationToken;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Resources\Models\IntegrationResource;
use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Reports\Jobs\GenerateAsyncReportJob;
use App\Domain\Reports\Jobs\CleanupExpiredReportsJob;
use App\Domain\Reports\Services\AsyncReportResultStorage;
use App\Domain\Reports\Services\InsightsQuerySignature;
use App\Domain\Reports\Services\IdempotentReportResolver;
use App\Domain\Integrations\Services\Meta\MetaRateLimitException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MetaInsightsHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Integration $integrationA;
    private Integration $integrationB;
    private IntegrationResource $adAccountA;
    private IntegrationResource $adAccountB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'active' => true]);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'active' => true]);

        $this->integrationA = Integration::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta A',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgA->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_A'
        ]);

        $this->integrationB = Integration::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'status' => 'connected',
            'display_name' => 'Meta B',
        ]);
        IntegrationToken::create([
            'organization_id' => $this->orgB->id,
            'provider' => 'meta',
            'access_token' => 'TOKEN_B'
        ]);

        $this->adAccountA = IntegrationResource::create([
            'organization_id' => $this->orgA->id,
            'integration_id' => $this->integrationA->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Ad Account A',
            'external_id' => 'act_A',
            'metadata_json' => ['timezone_name' => 'UTC', 'currency' => 'USD'],
        ]);

        $this->adAccountB = IntegrationResource::create([
            'organization_id' => $this->orgB->id,
            'integration_id' => $this->integrationB->id,
            'provider' => 'meta',
            'resource_type' => 'ad_account',
            'name' => 'Ad Account B',
            'external_id' => 'act_B',
            'metadata_json' => ['timezone_name' => 'UTC', 'currency' => 'USD'],
        ]);

        Storage::fake('private');
        Config::set('reports.result_disk', 'private');
    }

    private function createReport(Integration $integration, IntegrationResource $resource): AsyncReport
    {
        $resolver = app(IdempotentReportResolver::class);
        $result = $resolver->resolve($integration, [
            'resource_uuid' => $resource->uuid,
            'level' => 'ad',
            'period' => 'last_7d'
        ], 'meta', 'insights');
        
        return $result['report'];
    }

    /** @test 1 - 1 registro */
    public function test_processes_1_record_successfully()
    {
        Http::fake([
            '*/insights*' => Http::response([
                'data' => [['ad_id' => 'ad_1', 'spend' => '10']]
            ], 200)
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(100, $report->progress);
        $this->assertEquals(1, $report->records_processed);
        $this->assertEquals(1, $report->pages_processed);
        $this->assertNotNull($report->result);
    }

    /** @test 2 - milhares de Ads e 14 - ausência de memory explosion */
    public function test_processes_thousands_of_ads_without_memory_explosion()
    {
        // Simular 50 páginas de 200 registros = 10k registros
        $sequence = Http::sequence();
        for ($i = 0; $i < 50; $i++) {
            $data = [];
            for ($j = 0; $j < 200; $j++) {
                $data[] = ['ad_id' => "ad_{$i}_{$j}", 'spend' => '1'];
            }
            $sequence->push([
                'data' => $data,
                'paging' => $i < 49 ? ['cursors' => ['after' => "cursor_{$i}"], 'next' => 'http...'] : []
            ], 200);
        }
        
        Http::fake(['*/insights*' => $sequence]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $startMemory = memory_get_usage();
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(10000, $report->records_processed);
        $this->assertEquals(50, $report->pages_processed);
        
        // Assert memory used is less than 50MB (should be well below with chunking)
        $this->assertLessThan(50, $memoryUsed);
    }

    /** @test 3 - muitas páginas Graph */
    public function test_handles_many_graph_pages_up_to_max_limit()
    {
        Config::set('reports.max_pages_per_job', 10); // Reduce max for faster test
        
        Http::fake([
            '*/insights*' => function ($request) {
                return Http::response([
                    'data' => [['ad_id' => 'ad_1', 'spend' => '1']],
                    'paging' => ['cursors' => ['after' => 'cursor'], 'next' => 'http...']
                ], 200);
            }
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(10, $report->pages_processed); // Should stop at max_pages
        $this->assertEquals(10, $report->records_processed);
    }

    /** @test 4 - 429 durante paginação (retry) */
    public function test_retries_on_rate_limit_during_pagination()
    {
        Http::fake([
            '*/insights*' => Http::sequence()
                ->push([
                    'data' => [['ad_id' => '1']],
                    'paging' => ['cursors' => ['after' => 'cursor1'], 'next' => 'http...']
                ], 200)
                ->push(['error' => ['code' => 17, 'message' => 'User request limit']], 403) // Try 1
                ->push(['error' => ['code' => 17, 'message' => 'User request limit']], 403) // Try 2
                ->push(['error' => ['code' => 17, 'message' => 'User request limit']], 403) // Try 3
                ->push(['error' => ['code' => 17, 'message' => 'User request limit']], 403) // MetaRateLimitException thrown here!
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->tries = 3;
        
        try {
            $job->handle();
            $this->fail('Expected MetaRateLimitException');
        } catch (MetaRateLimitException $e) {
            // Success
        }

        $report->refresh();
        $this->assertEquals('queued', $report->status); // Status is reset for Job retry
        $this->assertEquals(0, $report->progress); // Progress reset
    }

    /** @test 5 - 5xx temporário */
    public function test_retries_on_transient_5xx_errors()
    {
        Http::fake([
            '*/insights*' => Http::sequence()
                ->push('Error', 503)
                ->push(['data' => [['ad_id' => '1', 'spend' => '10']]], 200)
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(1, $report->records_processed);
    }

    /** @test 6 - Job retry */
    public function test_job_retry_increments_attempts_and_updates_status()
    {
        Http::fake([
            '*/insights*' => Http::sequence()
                ->push(['error' => ['code' => 4, 'message' => 'API Error']], 400) // Definitive error
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('failed', $report->status);
        $this->assertEquals(1, $report->attempts);
        $this->assertStringContainsString('API Error', $report->error_message);
    }

    /** @test 7 - Job duplicado e 9 - mesma consulta simultânea */
    public function test_idempotent_resolver_prevents_duplicate_reports()
    {
        $resolver = app(IdempotentReportResolver::class);
        $params = ['resource_uuid' => $this->adAccountA->uuid, 'level' => 'ad', 'period' => 'today'];
        
        $result1 = $resolver->resolve($this->integrationA, $params, 'meta', 'insights');
        $this->assertFalse($result1['reused']);
        
        $result2 = $resolver->resolve($this->integrationA, $params, 'meta', 'insights');
        $this->assertTrue($result2['reused']);
        $this->assertEquals($result1['report']->id, $result2['report']->id);
        
        $this->assertEquals(1, AsyncReport::count());
    }

    /** @test 8 - worker crash/reexecução */
    public function test_job_handles_worker_crash_and_reexecution()
    {
        $report = $this->createReport($this->integrationA, $this->adAccountA);
        $report->update([
            'status' => 'running',
            'started_at' => now()->subHours(2) // Simulates crashed job
        ]);
        
        Http::fake([
            '*/insights*' => Http::response(['data' => [['ad_id' => '1']]], 200)
        ]);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(1, $report->attempts); // Because it was incremented during handle
    }

    /** @test 10 - Org A e B simultâneas */
    public function test_organizations_are_isolated_for_identical_queries()
    {
        $resolver = app(IdempotentReportResolver::class);
        $paramsA = ['resource_uuid' => $this->adAccountA->uuid, 'level' => 'ad', 'period' => 'today'];
        $paramsB = ['resource_uuid' => $this->adAccountB->uuid, 'level' => 'ad', 'period' => 'today'];
        
        $resultA = $resolver->resolve($this->integrationA, $paramsA, 'meta', 'insights');
        $resultB = $resolver->resolve($this->integrationB, $paramsB, 'meta', 'insights');
        
        $this->assertFalse($resultA['reused']);
        $this->assertFalse($resultB['reused']);
        $this->assertNotEquals($resultA['report']->id, $resultB['report']->id);
        
        $this->assertEquals(2, AsyncReport::count());
    }

    /** @test 11 - resultado acima do threshold de Storage */
    public function test_large_results_are_stored_in_filesystem()
    {
        Config::set('reports.result_size_threshold_kb', 1); // 1 KB threshold
        
        // Crie payload que seja > 1KB serializado
        $data = [];
        for ($i = 0; $i < 50; $i++) {
            $data[] = ['ad_id' => "ad_{$i}", 'spend' => '10', 'name' => str_repeat('A', 50)];
        }
        
        Http::fake([
            '*/insights*' => Http::response(['data' => $data], 200)
        ]);

        $report = $this->createReport($this->integrationA, $this->adAccountA);
        
        $job = new GenerateAsyncReportJob($report);
        $job->handle();

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNull($report->getRawOriginal('result'));
        $this->assertNotNull($report->result_path);
        
        Storage::disk('private')->assertExists($report->result_path);
        
        // Retrieve should work transparently
        $storage = app(AsyncReportResultStorage::class);
        $retrieved = $storage->retrieve($report);
        $this->assertCount(50, $retrieved);
    }

    /** @test 12 - limpeza de reports expirados */
    public function test_cleanup_job_removes_expired_reports_and_files()
    {
        Config::set('reports.result_size_threshold_kb', 0); // Always store in filesystem
        
        Http::fake([
            '*/insights*' => Http::response(['data' => [['ad_id' => '1']]], 200)
        ]);

        $report1 = $this->createReport($this->integrationA, $this->adAccountA);
        (new GenerateAsyncReportJob($report1))->handle();
        $report1->refresh();
        
        $report2 = $this->createReport($this->integrationB, $this->adAccountB);
        (new GenerateAsyncReportJob($report2))->handle();
        $report2->refresh();
        
        // Report 1 está expirado
        $report1->update(['expires_at' => now()->subDay()]);
        // Report 2 ainda é válido (padrão 30 dias na frente)
        
        Storage::disk('private')->assertExists($report1->result_path);
        Storage::disk('private')->assertExists($report2->result_path);
        
        (new CleanupExpiredReportsJob())->handle(app(AsyncReportResultStorage::class));
        
        $this->assertDatabaseMissing('async_reports', ['id' => $report1->id]);
        $this->assertDatabaseHas('async_reports', ['id' => $report2->id]);
        
        Storage::disk('private')->assertMissing($report1->result_path);
        Storage::disk('private')->assertExists($report2->result_path);
    }
}

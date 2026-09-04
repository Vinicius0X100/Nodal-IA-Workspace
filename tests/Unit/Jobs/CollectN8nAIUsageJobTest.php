<?php

namespace Tests\Unit\Jobs;

use App\Domain\AI\Services\N8nExecutionService;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\AI\Models\Conversation;
use App\Jobs\CollectN8nAIUsageJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CollectN8nAIUsageJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_fails_fast_if_config_is_missing()
    {
        $n8nService = Mockery::mock(N8nExecutionService::class);
        $n8nService->shouldReceive('isAvailable')->once()->andReturn(false);
        $aiUsageService = Mockery::mock(AIUsageService::class);

        $job = Mockery::mock(CollectN8nAIUsageJob::class)->makePartial();
        $job->shouldReceive('fail')->once()->with(Mockery::type(\RuntimeException::class));

        $job->handle($n8nService, $aiUsageService);
    }

    public function test_job_fails_fast_on_auth_errors()
    {
        $n8nService = Mockery::mock(N8nExecutionService::class);
        $n8nService->shouldReceive('isAvailable')->once()->andReturn(true);
        $n8nService->shouldReceive('getExecution')
            ->once()
            ->with('123')
            ->andThrow(new \RuntimeException('N8nExecutionService: credenciais da API do n8n inválidas ou sem permissão.'));

        $aiUsageService = Mockery::mock(AIUsageService::class);

        $job = Mockery::mock(CollectN8nAIUsageJob::class . '[fail]', ['123', '1']);
        $job->shouldReceive('fail')->once()->with(Mockery::type(\RuntimeException::class));

        $job->handle($n8nService, $aiUsageService);
    }

    public function test_job_retries_on_connection_exception()
    {
        $n8nService = Mockery::mock(N8nExecutionService::class);
        $n8nService->shouldReceive('isAvailable')->once()->andReturn(true);
        
        $exception = new ConnectionException('Timeout');
        $n8nService->shouldReceive('getExecution')
            ->once()
            ->with('123')
            ->andThrow($exception);

        $aiUsageService = Mockery::mock(AIUsageService::class);

        $job = new CollectN8nAIUsageJob('123', '1');

        $this->expectException(ConnectionException::class);
        $job->handle($n8nService, $aiUsageService);
    }

    public function test_job_logs_warning_if_no_usages_found()
    {
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'não gerou consumos') && $context['execution_id'] === '123';
        });

        $n8nService = Mockery::mock(N8nExecutionService::class);
        $n8nService->shouldReceive('isAvailable')->once()->andReturn(true);
        $n8nService->shouldReceive('getExecution')->once()->with('123')->andReturn(['status' => 'success']);
        $n8nService->shouldReceive('extractAIUsage')->once()->andReturn([]);

        $aiUsageService = Mockery::mock(AIUsageService::class);

        $job = new CollectN8nAIUsageJob('123', '1');
        $job->handle($n8nService, $aiUsageService);
    }
}

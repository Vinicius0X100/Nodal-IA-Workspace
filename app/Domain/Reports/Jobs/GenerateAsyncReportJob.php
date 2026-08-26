<?php

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Integrations\Services\Meta\MetaInsightsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAsyncReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hr max
    public $tries = 3;
    public $backoff = [60, 300, 600]; // Tenta de novo em 1m, 5m, 10m em caso de rate limit severo

    public function __construct(
        public AsyncReport $report
    ) {}

    public function handle(): void
    {
        $this->report->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 5, // Iniciando
        ]);

        try {
            if ($this->report->provider === 'meta' && $this->report->type === 'insights') {
                $service = app(MetaInsightsService::class);
                $result = $service->generateHeavyReport($this->report);
                
                $this->report->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $result,
                    'completed_at' => now(),
                ]);
            } else {
                throw new \Exception("Provider ou Type não suportado para geração de relatórios assíncronos.");
            }
        } catch (\App\Domain\Integrations\Services\Meta\MetaRateLimitException $e) {
            // Em caso de rate limit, não queremos marcar failed definitivo se ainda houver tries
            if ($this->attempts() < $this->tries) {
                // Relança a exceção para o Laravel re-agendar o Job com backoff
                throw $e;
            } else {
                $this->markAsFailed($e->getMessage());
            }
        } catch (\Exception $e) {
            $this->markAsFailed($e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markAsFailed($exception->getMessage());
    }

    private function markAsFailed(string $error): void
    {
        if ($this->report->status !== 'failed') {
            $this->report->update([
                'status' => 'failed',
                'error_message' => $error,
                'completed_at' => now(),
            ]);
            
            Log::error('[GenerateAsyncReportJob] Relatório falhou.', [
                'report_id' => $this->report->id,
                'error' => $error,
            ]);
        }
    }
}

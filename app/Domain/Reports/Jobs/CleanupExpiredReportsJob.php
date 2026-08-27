<?php

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Reports\Services\AsyncReportResultStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job genérico de cleanup de AsyncReports expirados.
 *
 * Remove:
 *  1. Arquivos de resultado do Storage (se result_path existir).
 *  2. O AsyncReport em si.
 *
 * Processamento chunked para não carregar tudo em memória.
 * Configurável via config/reports.php (retention_days, cleanup_chunk_size).
 *
 * Pode ser agendado via Scheduler no AppServiceProvider ou Console/Kernel.
 */
class CleanupExpiredReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutos
    public int $tries = 1;

    public function handle(AsyncReportResultStorage $storage): void
    {
        $chunkSize = (int) config('reports.cleanup_chunk_size', 100);
        $totalDeleted = 0;

        Log::info('[CleanupExpiredReportsJob] Iniciando cleanup de reports expirados.');

        // Chunk por ID para não carregar tudo em memória
        AsyncReport::where('expires_at', '<', now())
            ->chunkById($chunkSize, function ($reports) use ($storage, &$totalDeleted) {
                foreach ($reports as $report) {
                    try {
                        // 1. Remove arquivo do Storage (se existir)
                        $storage->delete($report);

                        // 2. Remove o model
                        $report->delete();

                        $totalDeleted++;
                    } catch (\Exception $e) {
                        Log::error('[CleanupExpiredReportsJob] Erro ao deletar report.', [
                            'report_id'   => $report->id,
                            'report_uuid' => $report->uuid,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            });

        // Cleanup de resultados em Storage com result_expires_at expirado (report ainda existe)
        $storageExpiredCount = 0;
        AsyncReport::whereNotNull('result_path')
            ->where('result_expires_at', '<', now())
            ->chunkById($chunkSize, function ($reports) use ($storage, &$storageExpiredCount) {
                foreach ($reports as $report) {
                    try {
                        $storage->delete($report);
                        $report->update(['result_path' => null, 'result' => null]);
                        $storageExpiredCount++;
                    } catch (\Exception $e) {
                        Log::error('[CleanupExpiredReportsJob] Erro ao limpar resultado expirado.', [
                            'report_id' => $report->id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('[CleanupExpiredReportsJob] Cleanup concluído.', [
            'reports_deleted'          => $totalDeleted,
            'storage_results_cleaned'  => $storageExpiredCount,
        ]);
    }
}

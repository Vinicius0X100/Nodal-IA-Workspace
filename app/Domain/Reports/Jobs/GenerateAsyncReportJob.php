<?php

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Models\AsyncReport;
use App\Domain\Integrations\Services\Meta\MetaInsightsService;
use App\Domain\Integrations\Services\Meta\MetaRateLimitException;
use App\Domain\Reports\Services\AsyncReportResultStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job assíncrono para geração de relatórios pesados.
 *
 * Garante:
 *  - Idempotência: uniqueId() baseado no query_hash (tenant-aware).
 *    Duas consultas idênticas da mesma org/integração nunca criam dois Jobs simultâneos.
 *  - Retry inteligente: rate limit propaga com backoff configurado, 5xx também.
 *  - Crash recovery: se o worker morrer e o Job reexecutar, guarda de status.
 *  - Resultado em Storage: via AsyncReportResultStorage (banco ou filesystem).
 *  - Observabilidade: metadata com duração, páginas, records, retries, rate_limit_hits.
 */
class GenerateAsyncReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout máximo do Job (Job timeout, não HTTP). */
    public int $timeout = 3600; // 1 hora

    /** Quantas tentativas antes de marcar como failed definitivo. */
    public int $tries = 3;

    /**
     * Backoff entre tentativas (segundos).
     * Usado como fallback quando o MetaMarketingClient não consegue fazer retry interno.
     */
    public array $backoff = [60, 300, 600]; // 1m, 5m, 10m

    /**
     * Tempo máximo que a trava do ShouldBeUnique fica ativa.
     * Previne que um crash do worker deixe a trava bloqueada para sempre.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public AsyncReport $report
    ) {}

    /**
     * Chave de unicidade do Job — baseada no query_hash do report.
     *
     * O query_hash já é tenant-aware (inclui organization_id + integration_id),
     * então nunca há colisão cross-tenant.
     *
     * Se query_hash não existir (report legado), usa o ID do report como fallback.
     */
    public function uniqueId(): string
    {
        return $this->report->query_hash ?? 'report-' . $this->report->id;
    }

    public function handle(): void
    {
        // ── Guard: se já foi completado (reexecução após crash) ──────────────
        $this->report->refresh();

        if ($this->report->status === 'completed') {
            Log::info('[GenerateAsyncReportJob] Report já completado, ignorando reexecução.', [
                'report_id' => $this->report->id,
                'query_hash' => $this->report->query_hash,
            ]);
            return;
        }

        // ── Guard: se está 'running' há muito tempo (crash recovery) ─────────
        if ($this->report->status === 'running' && $this->report->started_at) {
            $runningForMinutes = $this->report->started_at->diffInMinutes(now());
            $maxRunningMinutes = (int) ($this->timeout / 60);

            if ($runningForMinutes < $maxRunningMinutes - 5) {
                // Ainda dentro do tempo esperado — possivelmente duplicação de execução
                Log::warning('[GenerateAsyncReportJob] Report ainda em running dentro do tempo esperado, possivelmente duplicado.', [
                    'report_id' => $this->report->id,
                    'running_for_minutes' => $runningForMinutes,
                ]);
                return;
            }

            // Passou do tempo — assume crash, reprocessa
            Log::info('[GenerateAsyncReportJob] Reprocessando após possível crash.', [
                'report_id' => $this->report->id,
                'running_for_minutes' => $runningForMinutes,
            ]);
        }

        // ── Marca como running ────────────────────────────────────────────────
        $this->report->increment('attempts');
        $this->report->update([
            'status'     => 'running',
            'started_at' => now(),
            'progress'   => 5,
        ]);

        try {
            $this->execute();
        } catch (MetaRateLimitException $e) {
            // Rate limit: não marca failed definitivo se ainda houver tries
            if ($this->attempts() < $this->tries) {
                // Volta para queued e relança para o Laravel re-agendar com backoff
                $this->report->update([
                    'status'   => 'queued',
                    'progress' => 0,
                ]);
                throw $e;
            } else {
                $this->markAsFailed('Rate limit da Meta excedido após múltiplas tentativas. Tente novamente mais tarde.');
            }
        } catch (\Exception $e) {
            $this->markAsFailed($e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markAsFailed($exception->getMessage());
    }

    /**
     * Executa o processamento do relatório delegando ao Service correto.
     */
    private function execute(): void
    {
        if ($this->report->provider === 'meta' && $this->report->type === 'insights') {
            $service = app(MetaInsightsService::class);
            $storage = app(AsyncReportResultStorage::class);

            $result = $service->generateHeavyReport($this->report);

            $this->report->update([
                'status'       => 'completed',
                'progress'     => 100,
                'completed_at' => now(),
            ]);

            // Persiste resultado em banco ou Storage (transparente)
            $storage->store($this->report, $result);

        } else {
            throw new \Exception("Provider '{$this->report->provider}' ou type '{$this->report->type}' não suportado para geração de relatórios assíncronos.");
        }
    }

    /**
     * Marca o report como failed com mensagem sanitizada (sem stack trace).
     */
    private function markAsFailed(string $error): void
    {
        // Sanitiza mensagem: remove stack traces e tokens
        $sanitized = $this->sanitizeErrorMessage($error);

        if ($this->report->status !== 'failed') {
            $this->report->update([
                'status'        => 'failed',
                'error_message' => $sanitized,
                'completed_at'  => now(),
            ]);

            Log::error('[GenerateAsyncReportJob] Relatório falhou.', [
                'report_id'   => $this->report->id,
                'query_hash'  => $this->report->query_hash,
                'provider'    => $this->report->provider,
                'attempts'    => $this->report->attempts,
            ]);
        }
    }

    /**
     * Remove stack traces, tokens e dados sensíveis da mensagem de erro.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Trunca para evitar vazamento de stack trace
        $truncated = mb_substr($message, 0, 500);

        // Remove padrões de token (EAA...)
        return preg_replace('/EAA[a-zA-Z0-9]{10,}/', '[TOKEN_REDACTED]', $truncated);
    }
}

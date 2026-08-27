<?php

namespace App\Domain\Reports\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Reports\Models\AsyncReport;
use Illuminate\Support\Str;

/**
 * Resolve o AsyncReport correto para uma consulta, evitando trabalho duplicado.
 *
 * Regras de deduplicação (SEMPRE escopadas por organization_id):
 *  1. Existe report queued|running com o mesmo query_hash? → retorna o existente.
 *  2. Existe report completed com mesmo hash dentro da janela de TTL? → retorna o existente.
 *  3. Caso contrário → cria e retorna um novo report.
 *
 * NUNCA reutiliza reports de outra organização — o query_hash é tenant-aware,
 * e a busca é sempre escopada por organization_id.
 */
class IdempotentReportResolver
{
    public function __construct(
        private InsightsQuerySignature $signature,
    ) {}

    /**
     * Resolve ou cria um AsyncReport para a consulta.
     *
     * @param  Integration  $integration
     * @param  array        $params       Parâmetros normalizados
     * @param  string       $provider     'meta', 'google', etc.
     * @param  string       $type         'insights', 'performance', etc.
     * @return array{report: AsyncReport, reused: bool}
     */
    public function resolve(
        Integration $integration,
        array $params,
        string $provider,
        string $type,
    ): array {
        $hash = $this->signature->generate($integration, $params);

        // ── 1. Report ativo (queued ou running) ──────────────────────────────
        $activeReport = AsyncReport::where('organization_id', $integration->organization_id)
            ->where('query_hash', $hash)
            ->whereIn('status', ['queued', 'running'])
            ->latest()
            ->first();

        if ($activeReport) {
            return ['report' => $activeReport, 'reused' => true];
        }

        // ── 2. Report completado recentemente (dentro da janela de TTL) ──────
        $completedTtlMinutes = (int) config('reports.idempotency_completed_ttl_minutes', 10);
        $recentCompleted = AsyncReport::where('organization_id', $integration->organization_id)
            ->where('query_hash', $hash)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subMinutes($completedTtlMinutes))
            ->latest('completed_at')
            ->first();

        if ($recentCompleted) {
            return ['report' => $recentCompleted, 'reused' => true];
        }

        // ── 3. Cria novo report ───────────────────────────────────────────────
        $retentionDays = (int) config('reports.retention_days', 30);
        $resultTtlHours = (int) config('reports.result_ttl_hours', 48);

        $report = AsyncReport::create([
            'organization_id'  => $integration->organization_id,
            'integration_id'   => $integration->id,
            'provider'         => $provider,
            'type'             => $type,
            'status'           => 'queued',
            'query_hash'       => $hash,
            'params'           => $params,
            'expires_at'       => now()->addDays($retentionDays),
            'result_expires_at' => now()->addHours($resultTtlHours),
        ]);

        return ['report' => $report, 'reused' => false];
    }
}

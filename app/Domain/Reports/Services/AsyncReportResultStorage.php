<?php

namespace App\Domain\Reports\Services;

use App\Domain\Reports\Models\AsyncReport;
use Illuminate\Support\Facades\Storage;

/**
 * Gerencia onde o resultado de um AsyncReport é persistido.
 *
 * Estratégia:
 *   resultado pequeno (< threshold_kb) → banco (coluna result)
 *   resultado grande (>= threshold_kb) → Storage filesystem (disk configurável)
 *
 * O disco é configurável via REPORTS_RESULT_DISK:
 *   - 'private' → local filesystem (padrão)
 *   - 's3'      → AWS S3 em produção
 *
 * O path interno nunca é exposto ao AI Gateway ou ao n8n.
 * O AsyncReport.result_path é opaco e interno.
 *
 * Compressão: resultado em Storage é gzip-comprimido para economizar espaço.
 */
class AsyncReportResultStorage
{
    private const THRESHOLD_BYTES = null; // Calculado dinamicamente via config

    /**
     * Persiste o resultado do relatório (banco ou Storage).
     */
    public function store(AsyncReport $report, array $data): void
    {
        $serialized = json_encode($data);
        $thresholdKb = (int) config('reports.result_size_threshold_kb', 512);
        $thresholdBytes = $thresholdKb * 1024;

        if (strlen($serialized) >= $thresholdBytes) {
            $this->storeToFilesystem($report, $serialized);
        } else {
            $report->update([
                'result' => $data,
                'result_path' => null,
            ]);
        }
    }

    /**
     * Recupera o resultado do relatório (banco ou Storage).
     *
     * @return array|null  null se o resultado já expirou ou não existe.
     */
    public function retrieve(AsyncReport $report): ?array
    {
        // Resultado em Storage
        if (!empty($report->result_path)) {
            return $this->retrieveFromFilesystem($report);
        }

        // Resultado no banco
        return $report->result;
    }

    /**
     * Deleta o arquivo de resultado do Storage (se existir).
     * Chamado pelo CleanupExpiredReportsJob.
     */
    public function delete(AsyncReport $report): void
    {
        if (!empty($report->result_path)) {
            $disk = config('reports.result_disk', 'private');
            Storage::disk($disk)->delete($report->result_path);
        }
    }

    /**
     * Persiste o resultado no Storage filesystem com gzip.
     */
    private function storeToFilesystem(AsyncReport $report, string $serializedJson): void
    {
        $disk = config('reports.result_disk', 'private');

        // Path: reports/{org_uuid}/{report_uuid}.json.gz
        // Nunca inclui IDs externos, tokens ou dados sensíveis no path
        $orgUuid = $report->organization->uuid ?? $report->organization_id;
        $path = "reports/{$orgUuid}/{$report->uuid}.json.gz";

        $compressed = gzencode($serializedJson, 6);

        Storage::disk($disk)->put($path, $compressed);

        $report->update([
            'result' => null,
            'result_path' => $path,
        ]);
    }

    /**
     * Recupera resultado do Storage e descomprime.
     */
    private function retrieveFromFilesystem(AsyncReport $report): ?array
    {
        $disk = config('reports.result_disk', 'private');

        if (!Storage::disk($disk)->exists($report->result_path)) {
            return null;
        }

        $compressed = Storage::disk($disk)->get($report->result_path);
        $json = gzdecode($compressed);

        if ($json === false) {
            return null;
        }

        return json_decode($json, true);
    }
}

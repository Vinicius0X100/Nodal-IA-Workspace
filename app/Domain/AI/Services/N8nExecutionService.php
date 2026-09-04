<?php

namespace App\Domain\AI\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * N8nExecutionService
 *
 * Responsável por consultar a Public API do n8n para obter os dados
 * completos de uma execução e extrair os consumos de tokens de IA.
 *
 * Isolamento de credenciais:
 * - A API Key é lida exclusivamente via config('services.n8n.api_key').
 * - Nunca é logada, exposta em respostas ao usuário ou passada ao n8n.
 */
class N8nExecutionService
{
    private string $baseUrl;
    private string $apiKey;

    /** Timeout (segundos) para a chamada à API do n8n */
    private const TIMEOUT_SECONDS = 30;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.n8n.base_url', ''), '/');
        $this->apiKey  = (string) config('services.n8n.api_key', '');
    }

    /**
     * Verifica se o serviço está configurado (base_url + api_key presentes).
     */
    public function isAvailable(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    /**
     * Busca uma execução completa pelo ID via Public API do n8n.
     *
     * Endpoint: GET {base_url}/api/v1/executions/{executionId}?includeData=true
     *
     * @param  string|int  $executionId
     * @return array  Payload completo da execução deserializado.
     *
     * @throws \RuntimeException Se o serviço não estiver configurado ou a execução não for encontrada.
     */
    public function getExecution(string|int $executionId): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException(
                'N8nExecutionService: N8N_BASE_URL ou N8N_API_KEY não configurados.'
            );
        }

        $url = "{$this->baseUrl}/api/v1/executions/{$executionId}?includeData=true";

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeader('X-N8N-API-KEY', $this->apiKey)
                ->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('N8nExecutionService: timeout ou falha de conexão', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                "N8nExecutionService: falha de conexão com o n8n. ({$e->getMessage()})"
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::error('N8nExecutionService: autenticação recusada pela API do n8n', [
                'execution_id' => $executionId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException(
                'N8nExecutionService: credenciais da API do n8n inválidas ou sem permissão.'
            );
        }

        if ($response->status() === 404) {
            throw new \RuntimeException(
                "N8nExecutionService: execução #{$executionId} não encontrada na API do n8n."
            );
        }

        if (!$response->successful()) {
            Log::error('N8nExecutionService: resposta inesperada da API do n8n', [
                'execution_id' => $executionId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException(
                "N8nExecutionService: n8n retornou status inesperado {$response->status()} para execução #{$executionId}."
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new \RuntimeException(
                "N8nExecutionService: resposta inválida da API do n8n para execução #{$executionId}."
            );
        }

        return $data;
    }

    /**
     * Percorre todos os nodes e todos os runs de uma execução e retorna
     * um array normalizado com todos os consumos de tokens de IA encontrados.
     *
     * A detecção é baseada na PRESENÇA de tokenUsage (ou chave equivalente)
     * nos outputs de cada run — não no nome do node.
     * O node_name é preservado como metadado/origem.
     *
     * @param  array  $execution  Payload completo retornado por getExecution().
     * @return array  Lista de consumos normalizados.
     *
     * Estrutura de cada item:
     * [
     *   'node_name'          => string,
     *   'run_index'          => int,
     *   'provider'           => string,
     *   'model'              => string,
     *   'prompt_tokens'      => int,
     *   'completion_tokens'  => int,
     *   'total_tokens'       => int,
     * ]
     */
    public function extractAIUsage(array $execution): array
    {
        $runData = Arr::get($execution, 'data.resultData.runData');

        if (empty($runData) || !is_array($runData)) {
            return [];
        }

        $usages = [];

        foreach ($runData as $nodeName => $nodeRuns) {
            if (!is_array($nodeRuns)) {
                continue;
            }

            foreach ($nodeRuns as $runIndex => $run) {
                $tokenUsage = $this->findTokenUsage($run);

                if ($tokenUsage === null) {
                    continue;
                }

                $promptTokens     = (int) ($tokenUsage['promptTokens']     ?? $tokenUsage['prompt_tokens']     ?? 0);
                $completionTokens = (int) ($tokenUsage['completionTokens'] ?? $tokenUsage['completion_tokens'] ?? 0);
                $totalTokens      = (int) ($tokenUsage['totalTokens']      ?? $tokenUsage['total_tokens']      ?? ($promptTokens + $completionTokens));

                if ($promptTokens === 0 && $completionTokens === 0 && $totalTokens === 0) {
                    continue;
                }

                $usages[] = [
                    'node_name'         => $nodeName,
                    'run_index'         => (int) $runIndex,
                    'provider'          => $this->inferProvider($nodeName, $run),
                    'model'             => $this->inferModel($run),
                    'prompt_tokens'     => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens'      => $totalTokens,
                ];
            }
        }

        return $usages;
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Procura por tokenUsage nos caminhos conhecidos dentro de um run.
     *
     * Percorre TODOS os canais dentro de run.data (ex.: main, ai_languageModel, ai_tool).
     */
    private function findTokenUsage(array $run): ?array
    {
        $runData = Arr::get($run, 'data', []);

        if (!is_array($runData)) {
            return null;
        }

        foreach ($runData as $channelName => $channelOutputs) {
            if (!is_array($channelOutputs)) {
                continue;
            }

            foreach ($channelOutputs as $outputGroup) {
                if (!is_array($outputGroup)) {
                    continue;
                }

                foreach ($outputGroup as $item) {
                    $json = Arr::get($item, 'json', []);

                    if (!is_array($json)) {
                        continue;
                    }

                    // Caminho 1: json.tokenUsage (Google Gemini Chat Model via n8n)
                    if (isset($json['tokenUsage']) && is_array($json['tokenUsage'])) {
                        return $json['tokenUsage'];
                    }

                    // Caminho 2: json.response.tokenUsage
                    if (isset($json['response']['tokenUsage']) && is_array($json['response']['tokenUsage'])) {
                        return $json['response']['tokenUsage'];
                    }

                    // Caminho 3: json.usageMetadata (Gemini API nativa)
                    if (isset($json['usageMetadata']) && is_array($json['usageMetadata'])) {
                        $meta = $json['usageMetadata'];
                        return [
                            'promptTokens'     => $meta['promptTokenCount']     ?? $meta['promptTokens']     ?? 0,
                            'completionTokens' => $meta['candidatesTokenCount'] ?? $meta['completionTokens'] ?? 0,
                            'totalTokens'      => $meta['totalTokenCount']      ?? $meta['totalTokens']      ?? 0,
                        ];
                    }

                    // Caminho 4: json.usage (padrão OpenAI-compatible)
                    if (isset($json['usage']) && is_array($json['usage'])) {
                        $usage = $json['usage'];
                        if (isset($usage['prompt_tokens']) || isset($usage['completion_tokens'])) {
                            return [
                                'promptTokens'     => $usage['prompt_tokens']     ?? 0,
                                'completionTokens' => $usage['completion_tokens'] ?? 0,
                                'totalTokens'      => $usage['total_tokens']      ?? 0,
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Infere o provider pelo nome do node ou pelo conteúdo dos outputs.
     */
    private function inferProvider(string $nodeName, array $run): string
    {
        $nameLower = strtolower($nodeName);

        if (str_contains($nameLower, 'gemini') || str_contains($nameLower, 'google')) {
            return 'google';
        }
        if (str_contains($nameLower, 'openai') || str_contains($nameLower, 'gpt')) {
            return 'openai';
        }
        if (str_contains($nameLower, 'claude') || str_contains($nameLower, 'anthropic')) {
            return 'anthropic';
        }

        $runData = Arr::get($run, 'data', []);
        
        if (is_array($runData)) {
            foreach ($runData as $channelOutputs) {
                if (!is_array($channelOutputs)) continue;
                foreach ($channelOutputs as $outputGroup) {
                    foreach ((array) $outputGroup as $item) {
                        $model = Arr::get($item, 'json.model', '')
                            ?: Arr::get($item, 'json.response.model', '');
                        if (str_contains((string) $model, 'gemini')) return 'google';
                        if (str_contains((string) $model, 'gpt'))    return 'openai';
                        if (str_contains((string) $model, 'claude')) return 'anthropic';
                    }
                }
            }
        }

        return 'unknown';
    }

    /**
     * Tenta extrair o nome do modelo a partir dos outputs do run.
     */
    private function inferModel(array $run): string
    {
        $runData = Arr::get($run, 'data', []);

        if (is_array($runData)) {
            foreach ($runData as $channelOutputs) {
                if (!is_array($channelOutputs)) continue;
                foreach ($channelOutputs as $outputGroup) {
                    foreach ((array) $outputGroup as $item) {
                        $model = Arr::get($item, 'json.model');
                        if (!empty($model)) return (string) $model;

                        $model = Arr::get($item, 'json.response.model');
                        if (!empty($model)) return (string) $model;
                    }
                }
            }
        }

        return 'unknown';
    }
}

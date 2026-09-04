<?php

namespace App\Console\Commands;

use App\Domain\AI\Services\N8nExecutionService;
use Illuminate\Console\Command;

/**
 * Comando temporário de inspeção: valida a coleta de tokenUsage antes
 * de conectar ao módulo de Billing.
 *
 * NÃO registra créditos, eventos ou qualquer dado no banco de dados.
 * NÃO imprime prompts, conteúdo de mensagens ou dados sensíveis.
 * NÃO expõe a API Key do n8n.
 */
class InspectN8nAIUsageCommand extends Command
{
    protected $signature   = 'n8n:inspect-ai-usage {executionId : ID numérico da execução no n8n} {--debug-structure : Imprime a estrutura real do payload retornado pelo n8n}';
    protected $description = '[TEMPORÁRIO] Inspeciona o tokenUsage de uma execução n8n via Public API';

    public function handle(N8nExecutionService $service): int
    {
        $executionId = $this->argument('executionId');
        $debugStructure = $this->option('debug-structure');

        if (!$service->isAvailable()) {
            $this->error('N8N_BASE_URL e/ou N8N_API_KEY não estão configurados no .env.');
            return self::FAILURE;
        }

        $this->info("Buscando execução #{$executionId} via API do n8n...");

        try {
            $execution = $service->getExecution($executionId);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('');
        $status    = $execution['status'] ?? '—';
        $startedAt = $execution['startedAt'] ?? '—';
        $this->info("Status da execução: <comment>{$status}</comment>  |  Iniciada em: <comment>{$startedAt}</comment>");
        $this->line('');

        if ($debugStructure) {
            $this->printDebugStructure($execution);
            return self::SUCCESS;
        }

        $usages = $service->extractAIUsage($execution);

        if (empty($usages)) {
            $this->warn('Nenhum tokenUsage encontrado nesta execução.');
            $this->line('Isso pode indicar que:');
            $this->line('  - A execução não contém nodes de modelo de IA;');
            $this->line('  - O campo tokenUsage não está presente nos outputs;');
            $this->line('  - A execução foi buscada sem includeData=true (bug do serviço).');
            return self::SUCCESS;
        }

        // Tabela de resultados
        $this->table(
            ['Node', 'Run', 'Provider', 'Modelo', 'Prompt Tokens', 'Completion Tokens', 'Total Tokens'],
            array_map(fn ($u) => [
                $u['node_name'],
                $u['run_index'],
                $u['provider'],
                $u['model'],
                number_format($u['prompt_tokens'], 0, ',', '.'),
                number_format($u['completion_tokens'], 0, ',', '.'),
                number_format($u['total_tokens'], 0, ',', '.'),
            ], $usages)
        );

        // Totalizadores
        $totalCalls      = count($usages);
        $totalPrompt     = array_sum(array_column($usages, 'prompt_tokens'));
        $totalCompletion = array_sum(array_column($usages, 'completion_tokens'));
        $totalTokens     = array_sum(array_column($usages, 'total_tokens'));

        $this->line('');
        $this->line("  Total de chamadas encontradas : <info>{$totalCalls}</info>");
        $this->line("  Total Prompt Tokens           : <info>" . number_format($totalPrompt,     0, ',', '.') . "</info>");
        $this->line("  Total Completion Tokens       : <info>" . number_format($totalCompletion, 0, ',', '.') . "</info>");
        $this->line("  Total Tokens                  : <info>" . number_format($totalTokens,     0, ',', '.') . "</info>");
        $this->line('');

        return self::SUCCESS;
    }

    private function printDebugStructure(array $execution): void
    {
        $this->info('--- MODO DEBUG: Estrutura do Payload (Seguro) ---');
        $runData = \Illuminate\Support\Arr::get($execution, 'data.resultData.runData');

        if (!is_array($runData)) {
            $this->error('data.resultData.runData não é um array ou não foi encontrado.');
            return;
        }

        $nodeNames = array_keys($runData);
        $this->line("Nodes encontrados: <comment>" . implode(', ', $nodeNames) . "</comment>");
        $this->line('');

        foreach ($runData as $nodeName => $runs) {
            $this->info("Node: <comment>{$nodeName}</comment> (Runs: " . (is_array($runs) ? count($runs) : 0) . ")");

            if (!is_array($runs)) {
                continue;
            }

            foreach ($runs as $runIndex => $run) {
                $this->line("  Run Index: {$runIndex}");
                $this->line("    Keys no Run: " . implode(', ', array_keys($run)));

                $runDataContent = $run['data'] ?? null;
                if (!is_array($runDataContent)) {
                    $this->line("    'data' ausente ou não é array.");
                    continue;
                }

                $this->line("    Keys em run['data']: " . implode(', ', array_keys($runDataContent)));

                foreach ($runDataContent as $channelName => $channelData) {
                    $this->line("      Canal: <info>{$channelName}</info>");
                    
                    if (!is_array($channelData)) {
                        $this->line("        (Não é um array)");
                        continue;
                    }

                    $this->line("        Quantidade de itens (grupos de output): " . count($channelData));

                    if (count($channelData) > 0) {
                        $firstGroup = $channelData[0];
                        if (is_array($firstGroup) && count($firstGroup) > 0) {
                            $firstItem = $firstGroup[0];
                            if (is_array($firstItem)) {
                                $this->line("        Keys do primeiro item: " . implode(', ', array_keys($firstItem)));

                                if (isset($firstItem['json']) && is_array($firstItem['json'])) {
                                    $this->line("        Keys dentro de 'json': " . implode(', ', array_keys($firstItem['json'])));
                                } else {
                                    $this->line("        'json' ausente ou não é array no primeiro item.");
                                }
                            }
                        }
                    }
                }
            }
            $this->line('');
        }
    }
}

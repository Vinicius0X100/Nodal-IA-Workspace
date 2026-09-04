<?php

namespace Tests\Unit\Domain\AI;

use App\Domain\AI\Services\N8nExecutionService;
use Tests\TestCase;

/**
 * Testes unitários para N8nExecutionService::extractAIUsage().
 *
 * Todos os testes usam payloads simulados (sem HTTP real).
 * A estrutura de dados reflete o formato real retornado pela
 * Public API do n8n: data.resultData.runData.{nodeName}[runs][].data.main[][][].json
 */
class N8nExecutionServiceTest extends TestCase
{
    private N8nExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Injeta config mínima para o serviço não depender do .env em testes unitários
        config([
            'services.n8n.base_url' => 'http://localhost:5678',
            'services.n8n.api_key'  => 'test-key',
        ]);

        $this->service = new N8nExecutionService();
    }

    // -------------------------------------------------------------------------
    // Helpers para construir payloads de execução simulados
    // -------------------------------------------------------------------------

    /**
     * Monta a estrutura de um run de node com tokenUsage no caminho padrão.
     */
    private function makeRun(array $tokenUsage, array $extraJson = []): array
    {
        return [
            'data' => [
                'main' => [
                    [
                        [
                            'json' => array_merge(['tokenUsage' => $tokenUsage], $extraJson),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Monta o payload completo de uma execução com runData arbitrário.
     */
    private function makeExecution(array $runData): array
    {
        return [
            'status' => 'success',
            'data'   => [
                'resultData' => [
                    'runData' => $runData,
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Cenários de teste
    // -------------------------------------------------------------------------

    /** Cenário 1: 1 node, 1 run — caminho feliz */
    public function test_single_node_single_run(): void
    {
        $execution = $this->makeExecution([
            'Google Gemini Chat Model' => [
                $this->makeRun([
                    'promptTokens'     => 100,
                    'completionTokens' => 50,
                    'totalTokens'      => 150,
                ]),
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(1, $result);
        $this->assertEquals('Google Gemini Chat Model', $result[0]['node_name']);
        $this->assertEquals(0,   $result[0]['run_index']);
        $this->assertEquals('google', $result[0]['provider']);
        $this->assertEquals(100, $result[0]['prompt_tokens']);
        $this->assertEquals(50,  $result[0]['completion_tokens']);
        $this->assertEquals(150, $result[0]['total_tokens']);
    }

    /** Cenário 2: 1 node, 5 runs — todos devem ser coletados individualmente */
    public function test_single_node_five_runs(): void
    {
        $runs = [];
        for ($i = 0; $i < 5; $i++) {
            $runs[] = $this->makeRun([
                'promptTokens'     => 1000 + $i * 10,
                'completionTokens' => 50 + $i,
                'totalTokens'      => 1050 + $i * 11,
            ]);
        }

        $execution = $this->makeExecution([
            'Google Gemini Chat Model' => $runs,
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(5, $result);

        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($i, $result[$i]['run_index'], "run_index incorreto no índice {$i}");
            $this->assertEquals(1000 + $i * 10, $result[$i]['prompt_tokens']);
            $this->assertEquals(50 + $i,        $result[$i]['completion_tokens']);
        }
    }

    /** Cenário 3: nodes sem tokenUsage devem ser ignorados */
    public function test_nodes_without_token_usage_are_ignored(): void
    {
        $execution = $this->makeExecution([
            'HTTP Request' => [
                [
                    'data' => [
                        'main' => [
                            [
                                ['json' => ['statusCode' => 200, 'body' => 'ok']],
                            ],
                        ],
                    ],
                ],
            ],
            'Set' => [
                [
                    'data' => [
                        'main' => [
                            [
                                ['json' => ['value' => 'foo']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertEmpty($result, 'Nodes sem tokenUsage não devem gerar registros.');
    }

    /** Cenário 4: múltiplos nodes de modelo — todos coletados */
    public function test_multiple_model_nodes(): void
    {
        $execution = $this->makeExecution([
            'Google Gemini Chat Model' => [
                $this->makeRun(['promptTokens' => 200, 'completionTokens' => 30, 'totalTokens' => 230]),
            ],
            'Google Gemini Chat Model 2' => [
                $this->makeRun(['promptTokens' => 500, 'completionTokens' => 100, 'totalTokens' => 600]),
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(2, $result);

        $nodeNames = array_column($result, 'node_name');
        $this->assertContains('Google Gemini Chat Model',   $nodeNames);
        $this->assertContains('Google Gemini Chat Model 2', $nodeNames);
    }

    /** Cenário 5: runData ausente ou vazio */
    public function test_missing_run_data_returns_empty(): void
    {
        $emptyExecution = ['status' => 'success', 'data' => []];
        $this->assertEmpty($this->service->extractAIUsage($emptyExecution));

        $nullRunData = $this->makeExecution([]);
        $this->assertEmpty($this->service->extractAIUsage($nullRunData));

        $noDataKey = ['status' => 'success'];
        $this->assertEmpty($this->service->extractAIUsage($noDataKey));
    }

    /** Cenário 6: tokenUsage com campos zerados — deve ser ignorado */
    public function test_zero_token_usage_is_ignored(): void
    {
        $execution = $this->makeExecution([
            'Google Gemini Chat Model' => [
                $this->makeRun([
                    'promptTokens'     => 0,
                    'completionTokens' => 0,
                    'totalTokens'      => 0,
                ]),
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);
        $this->assertEmpty($result, 'tokenUsage zerado não deve gerar registro.');
    }

    /** Cenário 7: tokenUsage parcial — campos faltando devem ser tratados como 0 */
    public function test_incomplete_token_usage_fills_missing_fields(): void
    {
        $execution = $this->makeExecution([
            'Google Gemini Chat Model' => [
                $this->makeRun([
                    'promptTokens' => 300,
                    // completionTokens e totalTokens ausentes
                ]),
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(1, $result);
        $this->assertEquals(300, $result[0]['prompt_tokens']);
        $this->assertEquals(0,   $result[0]['completion_tokens']);
        $this->assertEquals(300, $result[0]['total_tokens']); // prompt + completion
    }

    /** Cenário 8: tokenUsage via json.response.tokenUsage (caminho alternativo) */
    public function test_token_usage_in_response_key(): void
    {
        $execution = $this->makeExecution([
            'Gemini Agent' => [
                [
                    'data' => [
                        'main' => [
                            [
                                [
                                    'json' => [
                                        'response' => [
                                            'tokenUsage' => [
                                                'promptTokens'     => 400,
                                                'completionTokens' => 80,
                                                'totalTokens'      => 480,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(1, $result);
        $this->assertEquals(400, $result[0]['prompt_tokens']);
        $this->assertEquals(80,  $result[0]['completion_tokens']);
        $this->assertEquals(480, $result[0]['total_tokens']);
    }

    /** Cenário 9: usageMetadata (formato Gemini API nativo) */
    public function test_usage_metadata_gemini_native_format(): void
    {
        $execution = $this->makeExecution([
            'Gemini Native' => [
                [
                    'data' => [
                        'main' => [
                            [
                                [
                                    'json' => [
                                        'usageMetadata' => [
                                            'promptTokenCount'     => 1200,
                                            'candidatesTokenCount' => 60,
                                            'totalTokenCount'      => 1260,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(1, $result);
        $this->assertEquals(1200, $result[0]['prompt_tokens']);
        $this->assertEquals(60,   $result[0]['completion_tokens']);
        $this->assertEquals(1260, $result[0]['total_tokens']);
    }

    /** Cenário 10: mix de nodes com e sem tokenUsage no mesmo workflow */
    public function test_mixed_nodes_only_collects_ai_nodes(): void
    {
        $execution = $this->makeExecution([
            'Webhook' => [
                [
                    'data' => ['main' => [[['json' => ['body' => 'hello']]]]],
                ],
            ],
            'Google Gemini Chat Model' => [
                $this->makeRun(['promptTokens' => 500, 'completionTokens' => 40, 'totalTokens' => 540]),
                $this->makeRun(['promptTokens' => 600, 'completionTokens' => 50, 'totalTokens' => 650]),
            ],
            'Code' => [
                [
                    'data' => ['main' => [[['json' => ['result' => 42]]]]],
                ],
            ],
        ]);

        $result = $this->service->extractAIUsage($execution);

        $this->assertCount(2, $result);
        $this->assertEquals('Google Gemini Chat Model', $result[0]['node_name']);
        $this->assertEquals('Google Gemini Chat Model', $result[1]['node_name']);
        $this->assertEquals(0, $result[0]['run_index']);
        $this->assertEquals(1, $result[1]['run_index']);
    }
}

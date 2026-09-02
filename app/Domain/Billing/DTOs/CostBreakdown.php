<?php

namespace App\Domain\Billing\DTOs;

/**
 * Resultado do cálculo de custo para um evento de uso.
 *
 * tokens → rate → custo USD → câmbio → custo BRL → créditos Nodal
 *
 * Créditos NUNCA são arredondados por chamada individual.
 * 1 Crédito Nodal = R$ 0,01 de custo-base de IA.
 */
readonly class CostBreakdown
{
    public function __construct(
        // Tokens efectivos para cobrança
        public int   $normalInputTokens,
        public int   $cachedInputTokens,
        public int   $outputBillableTokens, // output + thinking
        public int   $toolUsePromptTokens,

        // Custo em USD por componente
        public float $inputCostUsd,
        public float $cachedInputCostUsd,
        public float $outputCostUsd,

        // Totais
        public float $totalCostUsd,

        // Câmbio utilizado
        public float $exchangeRate,
        public float $fxBufferPercent,

        // Custo real em BRL
        public float $providerCostBrl,

        // Custo comercial de referência (com buffer aplicado)
        public float $commercialReferenceCostBrl,

        // Créditos Nodal (alta precisão, nunca arredondados)
        public float $creditsUsed,

        // IDs das entidades de referência usadas no cálculo
        public ?int  $modelRateId       = null,
        public ?int  $exchangeRateId    = null,

        // Componentes detalhados para ai_usage_cost_components
        public array $components        = [],
    ) {}

    /**
     * Custo total em BRL (real do provider, sem buffer)
     */
    public static function zero(): self
    {
        return new self(
            normalInputTokens: 0,
            cachedInputTokens: 0,
            outputBillableTokens: 0,
            toolUsePromptTokens: 0,
            inputCostUsd: 0,
            cachedInputCostUsd: 0,
            outputCostUsd: 0,
            totalCostUsd: 0,
            exchangeRate: 0,
            fxBufferPercent: 0,
            providerCostBrl: 0,
            commercialReferenceCostBrl: 0,
            creditsUsed: 0,
        );
    }
}

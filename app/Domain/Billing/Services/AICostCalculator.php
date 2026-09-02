<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\DTOs\CostBreakdown;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Models\AiModelRate;
use App\Domain\Billing\Models\BillingExchangeRate;

/**
 * Calculador central de custo de IA.
 *
 * Fluxo: tokens → rate → custo USD → câmbio → custo BRL → Créditos Nodal
 *
 * 1 Crédito Nodal = R$ 0,01 de custo-base de IA.
 * Créditos NUNCA são arredondados por chamada individual.
 */
class AICostCalculator
{
    /**
     * Calcula o custo e os créditos para um evento de uso de IA.
     *
     * @param UsageEventInput $input   Dados de consumo vindos do provider
     * @param \DateTime|null  $at      Momento do evento (para buscar rates históricas)
     */
    public function calculate(UsageEventInput $input, ?\DateTime $at = null): CostBreakdown
    {
        $at ??= new \DateTime();

        // 1. Encontrar rate vigente
        $rate = AiModelRate::activeFor($input->provider, $input->model, $at);

        if (!$rate) {
            // Sem rate configurada: registrar evento com custo zero para fins de contagem
            return CostBreakdown::zero();
        }

        // 2. Encontrar taxa de câmbio vigente
        $exchangeRateModel = BillingExchangeRate::activeFor('USD', 'BRL', $at);
        $exchangeRate      = $exchangeRateModel?->rate ?? 0;
        $fxBuffer          = $exchangeRateModel?->fx_buffer_percent ?? 0;
        $effectiveRate     = $exchangeRateModel?->effectiveRate() ?? 0;

        // 3. Tokens efetivos para cobrança (Gemini e similares)
        //
        // normal_input = max(prompt_tokens - cached_input_tokens, 0)
        // output_billable = output_tokens + thinking_tokens
        //   (thinking é cobrado como output no Gemini)
        //
        // tool_use_prompt_tokens: armazenar para analytics,
        //   mas NÃO cobrar separado se já estão contados no promptTokenCount do provider.
        $normalInputTokens    = max($input->promptTokens - $input->cachedInputTokens, 0);
        $cachedInputTokens    = $input->cachedInputTokens;
        $outputBillableTokens = $input->outputTokens + $input->thinkingTokens;

        // 4. Custo USD por componente (rate por 1.000.000 tokens)
        $inputCostUsd        = ($normalInputTokens / 1_000_000)    * $rate->input_rate_per_million;
        $cachedInputCostUsd  = ($cachedInputTokens / 1_000_000)   * ($rate->cached_input_rate_per_million ?? 0);
        $outputCostUsd       = ($outputBillableTokens / 1_000_000) * $rate->output_rate_per_million;
        $totalCostUsd        = $inputCostUsd + $cachedInputCostUsd + $outputCostUsd;

        // 5. Custo real em BRL (sem buffer — custo real do provider)
        $providerCostBrl = $totalCostUsd * $exchangeRate;

        // 6. Custo comercial de referência (com buffer cambial aplicado)
        //    Este é o valor base para conversão em Créditos Nodal
        $commercialReferenceCostBrl = $totalCostUsd * $effectiveRate;

        // 7. Créditos Nodal: 1 crédito = R$ 0,01
        //    NUNCA arredondar — preservar full precision
        $creditsUsed = $commercialReferenceCostBrl / 0.01;

        // 8. Montar componentes detalhados
        $components = [];

        if ($normalInputTokens > 0) {
            $components[] = [
                'component_type' => 'input_tokens',
                'quantity'       => $normalInputTokens,
                'unit'           => 'tokens',
                'rate'           => $rate->input_rate_per_million / 1_000_000,
                'currency'       => 'USD',
                'cost'           => $inputCostUsd,
            ];
        }

        if ($cachedInputTokens > 0 && $rate->cached_input_rate_per_million !== null) {
            $components[] = [
                'component_type' => 'cached_tokens',
                'quantity'       => $cachedInputTokens,
                'unit'           => 'tokens',
                'rate'           => $rate->cached_input_rate_per_million / 1_000_000,
                'currency'       => 'USD',
                'cost'           => $cachedInputCostUsd,
            ];
        }

        if ($input->outputTokens > 0) {
            $components[] = [
                'component_type' => 'output_tokens',
                'quantity'       => $input->outputTokens,
                'unit'           => 'tokens',
                'rate'           => $rate->output_rate_per_million / 1_000_000,
                'currency'       => 'USD',
                'cost'           => ($input->outputTokens / 1_000_000) * $rate->output_rate_per_million,
            ];
        }

        if ($input->thinkingTokens > 0) {
            $components[] = [
                'component_type' => 'thinking_tokens',
                'quantity'       => $input->thinkingTokens,
                'unit'           => 'tokens',
                'rate'           => $rate->output_rate_per_million / 1_000_000,
                'currency'       => 'USD',
                'cost'           => ($input->thinkingTokens / 1_000_000) * $rate->output_rate_per_million,
            ];
        }

        return new CostBreakdown(
            normalInputTokens:         $normalInputTokens,
            cachedInputTokens:         $cachedInputTokens,
            outputBillableTokens:      $outputBillableTokens,
            toolUsePromptTokens:       $input->toolUsePromptTokens,
            inputCostUsd:              $inputCostUsd,
            cachedInputCostUsd:        $cachedInputCostUsd,
            outputCostUsd:             $outputCostUsd,
            totalCostUsd:              $totalCostUsd,
            exchangeRate:              $exchangeRate,
            fxBufferPercent:           $fxBuffer,
            providerCostBrl:           $providerCostBrl,
            commercialReferenceCostBrl: $commercialReferenceCostBrl,
            creditsUsed:               $creditsUsed,
            modelRateId:               $rate->id,
            exchangeRateId:            $exchangeRateModel?->id,
            components:                $components,
        );
    }
}

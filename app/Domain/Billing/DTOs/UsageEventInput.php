<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Enums\BillingCategory;

/**
 * DTO para registrar um evento de uso de IA.
 *
 * Todos os campos de custo/preço são calculados pelo Laravel.
 * Somente usage facts (tokens) chegam do n8n.
 */
readonly class UsageEventInput
{
    public function __construct(
        public string          $provider,
        public string          $model,
        public string          $operation,
        public string          $idempotencyKey,
        public int             $promptTokens       = 0,
        public int             $cachedInputTokens  = 0,
        public int             $outputTokens       = 0,
        public int             $thinkingTokens     = 0,
        public int             $toolUsePromptTokens = 0,
        public int             $totalTokens        = 0,
        public ?string         $source             = null,
        public ?string         $requestUuid        = null,
        public ?string         $n8nExecutionId     = null,
        public bool            $billable           = true,
        public BillingCategory $billingCategory    = BillingCategory::USER_REQUEST,
        public array           $providerUsageJson  = [],
        public array           $metadataJson       = [],
        public ?\DateTime      $occurredAt         = null,
    ) {}
}

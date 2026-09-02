<?php

namespace App\Http\Controllers\Billing;

use App\Domain\AI\Models\Conversation;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Endpoint interno para registro de consumo de IA.
 *
 * Protegido por ai.gateway middleware.
 * Organization e User são resolvidos pelo middleware — nunca do body.
 *
 * O n8n envia SOMENTE usage facts (tokens).
 * O Laravel calcula CUSTO e CRÉDITOS.
 */
class AIUsageApiController extends Controller
{
    public function __construct(
        private readonly AIUsageService $usageService,
    ) {}

    /**
     * POST /api/ai/usage/events
     *
     * Body esperado:
     * {
     *   "provider": "google",
     *   "model": "gemini-3.5-flash",
     *   "operation": "assistant_chat",
     *   "source": "main_agent",
     *   "prompt_tokens": 1000,
     *   "cached_input_tokens": 0,
     *   "output_tokens": 300,
     *   "thinking_tokens": 200,
     *   "tool_use_prompt_tokens": 0,
     *   "total_tokens": 1500,
     *   "billable": true,
     *   "billing_category": "user_request",
     *   "request_uuid": "...",
     *   "n8n_execution_id": "...",
     *   "idempotency_key": "..."
     * }
     *
     * NÃO aceitar: organization_id, user_id, custo, créditos, preço.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider'              => ['required', 'string', 'max:50'],
            'model'                 => ['required', 'string', 'max:100'],
            'operation'             => ['required', 'string', 'max:100'],
            'source'                => ['nullable', 'string', 'max:100'],
            'prompt_tokens'         => ['required', 'integer', 'min:0'],
            'cached_input_tokens'   => ['nullable', 'integer', 'min:0'],
            'output_tokens'         => ['nullable', 'integer', 'min:0'],
            'thinking_tokens'       => ['nullable', 'integer', 'min:0'],
            'tool_use_prompt_tokens' => ['nullable', 'integer', 'min:0'],
            'total_tokens'          => ['nullable', 'integer', 'min:0'],
            'billable'              => ['nullable', 'boolean'],
            'billing_category'      => ['nullable', 'string', 'in:' . implode(',', array_column(BillingCategory::cases(), 'value'))],
            'request_uuid'          => ['nullable', 'string', 'max:36'],
            'n8n_execution_id'      => ['nullable', 'string', 'max:100'],
            'idempotency_key'       => ['required', 'string', 'max:128'],
            'provider_usage_json'   => ['nullable', 'array'],
            'metadata_json'         => ['nullable', 'array'],
        ]);

        // Organization e User são autoritativos do middleware — nunca do body
        /** @var Organization $organization */
        $organization = app(Organization::class);
        /** @var User $user */
        $user = app(User::class);
        $conversation = app()->bound(Conversation::class) ? app(Conversation::class) : null;

        $billingCategory = BillingCategory::tryFrom($validated['billing_category'] ?? 'user_request')
            ?? BillingCategory::USER_REQUEST;

        $input = new UsageEventInput(
            provider:             $validated['provider'],
            model:                $validated['model'],
            operation:            $validated['operation'],
            idempotencyKey:       $validated['idempotency_key'],
            promptTokens:         $validated['prompt_tokens'],
            cachedInputTokens:    $validated['cached_input_tokens'] ?? 0,
            outputTokens:         $validated['output_tokens'] ?? 0,
            thinkingTokens:       $validated['thinking_tokens'] ?? 0,
            toolUsePromptTokens:  $validated['tool_use_prompt_tokens'] ?? 0,
            totalTokens:          $validated['total_tokens'] ?? 0,
            source:               $validated['source'] ?? null,
            requestUuid:          $validated['request_uuid'] ?? null,
            n8nExecutionId:       $validated['n8n_execution_id'] ?? null,
            billable:             $validated['billable'] ?? true,
            billingCategory:      $billingCategory,
            providerUsageJson:    $validated['provider_usage_json'] ?? [],
            metadataJson:         $validated['metadata_json'] ?? [],
        );

        $event = $this->usageService->record(
            organization: $organization,
            input:        $input,
            user:         $user,
            conversation: $conversation,
        );

        return response()->json([
            'success'      => true,
            'event_uuid'   => $event->uuid,
            'credits_used' => $event->credits_used,
            'billable'     => $event->billable,
        ], 201);
    }
}

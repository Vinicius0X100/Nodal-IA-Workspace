<?php

namespace App\Jobs;

use App\Domain\AI\Services\N8nExecutionService;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\AI\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class CollectN8nAIUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tenta 5 vezes antes de falhar definitivamente.
     */
    public int $tries = 5;

    /**
     * Backoff exponencial: 5, 15, 30, 60, 120 segundos.
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function __construct(
        public readonly string $n8nExecutionId,
        public readonly string $organizationId,
        public readonly ?string $userId = null,
        public readonly ?string $conversationId = null,
        public readonly ?string $messageId = null,
    ) {}

    public function handle(N8nExecutionService $n8nService, AIUsageService $aiUsageService): void
    {
        if (!$n8nService->isAvailable()) {
            $this->fail(new \RuntimeException('CollectN8nAIUsageJob: N8N_BASE_URL ou N8N_API_KEY não configurados. Abortando.'));
            return;
        }

        try {
            $execution = $n8nService->getExecution($this->n8nExecutionId);
        } catch (ConnectionException $e) {
            // Transient error - retry
            throw $e;
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            
            // Fail fast without retry for auth or configuration errors
            if (str_contains($message, 'credenciais') || str_contains($message, 'não configurados')) {
                $this->fail($e);
                return;
            }

            // Retry for 404 (execution not yet persisted) or 5xx/429
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }

        $usages = $n8nService->extractAIUsage($execution);

        if (empty($usages)) {
            Log::warning('CollectN8nAIUsageJob: Execução não gerou consumos de IA identificáveis.', [
                'execution_id' => $this->n8nExecutionId,
                'organization_id' => $this->organizationId,
                'usage_count' => 0,
            ]);
            return;
        }

        $organization = Organization::find($this->organizationId);
        if (!$organization) {
            $this->fail(new \RuntimeException("CollectN8nAIUsageJob: Organization ID {$this->organizationId} não encontrada."));
            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;
        $conversation = $this->conversationId ? Conversation::find($this->conversationId) : null;

        foreach ($usages as $usage) {
            $nodeIdentifier = \Illuminate\Support\Str::slug($usage['node_name']);
            $idempotencyKey = "n8n:{$this->n8nExecutionId}:{$nodeIdentifier}:{$usage['run_index']}";

            $input = new UsageEventInput(
                provider: $usage['provider'],
                model: $usage['model'],
                operation: 'chat', // default behavior for this flow
                source: 'main_agent',
                promptTokens: $usage['prompt_tokens'],
                cachedInputTokens: 0,
                outputTokens: $usage['completion_tokens'],
                thinkingTokens: 0,
                toolUsePromptTokens: 0,
                totalTokens: $usage['total_tokens'],
                billable: true,
                billingCategory: BillingCategory::USER_REQUEST,
                requestUuid: null,
                n8nExecutionId: $this->n8nExecutionId,
                idempotencyKey: $idempotencyKey,
                providerUsageJson: json_encode($usage),
                metadataJson: json_encode([
                    'node_name' => $usage['node_name'],
                    'run_index' => $usage['run_index'],
                ])
            );

            $aiUsageService->record($organization, $input, $user, $conversation);
        }
    }
}

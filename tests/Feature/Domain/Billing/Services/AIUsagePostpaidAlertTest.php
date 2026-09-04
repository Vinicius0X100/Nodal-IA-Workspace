<?php

namespace Tests\Feature\Domain\Billing\Services;

use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AIUsagePostpaidAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_threshold_events_are_dispatched_and_idempotent()
    {
        Event::fake([AIUsageThresholdReached::class]);

        $organization = Organization::create([
            'name' => 'Org Test',
            'slug' => 'org-test-' . \Illuminate\Support\Str::random(5),
            'active' => true
        ]);

        $plan = BillingPlan::create([
            'code' => 'starter-test',
            'name' => 'Starter',
            'monthly_price_cents' => 9900,
            'included_ai_credits' => 1000,
            'overage_price_per_1000_credits_cents' => 10,
        ]);

        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'billing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now(),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
            'postpaid_enabled'     => true,
            'postpaid_limit_cents' => 100, // R$ 1,00 de limite = 100.000 creditos de overage
        ]);

        $period = AiUsagePeriod::create([
            'organization_id'       => $organization->id,
            'subscription_id'       => $subscription->id,
            'period_start'          => now()->startOfMonth(),
            'period_end'            => now()->endOfMonth(),
            'status'                => 'open',
            'included_credits'      => 1000,
            'billable_credits_used' => 690, // 69%
            'overage_credits'       => 0,
            'estimated_overage_cents' => 0,
        ]);

        $service = app(AIUsageService::class);

        // Input que não ultrapassa 70%
        $input = new UsageEventInput(
            idempotencyKey: 'test-1',
            provider: 'test',
            model: 'test',
            operation: 'chat',
            source: 'test',
            requestUuid: 'req-1',
            promptTokens: 0,
            cachedInputTokens: 0,
            outputTokens: 0,
            thinkingTokens: 0,
            totalTokens: 0,
            billable: true,
            billingCategory: BillingCategory::USER_REQUEST,
        );

        // Simulando que ele custou 0 creditos reais para testar o threshold no estado atual 690
        $service->record($organization, $input);
        Event::assertNotDispatched(AIUsageThresholdReached::class);

        // Atualizar periodo pra 710 creditos (71%) simulando outro processo
        $period->update(['billable_credits_used' => 710]);

        $input2 = new UsageEventInput(
            idempotencyKey: 'test-2',
            provider: 'test',
            model: 'test',
            operation: 'chat',
            source: 'test',
            requestUuid: 'req-2',
            promptTokens: 0,
            cachedInputTokens: 0,
            outputTokens: 0,
            thinkingTokens: 0,
            totalTokens: 0,
            billable: true,
            billingCategory: BillingCategory::USER_REQUEST,
        );

        $service->record($organization, $input2);

        Event::assertDispatched(AIUsageThresholdReached::class, function ($e) {
            return $e->alertType === AlertType::CREDIT_USAGE_70;
        });

        // Simulando que o listener rodou e gravou no banco o evento de alerta
        \App\Domain\Billing\Models\BillingAlertEvent::create([
            'organization_id' => $organization->id,
            'usage_period_id' => $period->id,
            'alert_type' => AlertType::CREDIT_USAGE_70->value,
            'threshold' => 70,
            'idempotency_key' => "org_{$organization->id}_period_{$period->id}_" . AlertType::CREDIT_USAGE_70->value,
            'triggered_at' => now(),
            'recipient_summary_json' => []
        ]);

        // Novo evento não deve disparar de novo 70%
        $input3 = new UsageEventInput(
            idempotencyKey: 'test-3',
            provider: 'test',
            model: 'test',
            operation: 'chat',
            source: 'test',
            requestUuid: 'req-3',
            promptTokens: 0,
            cachedInputTokens: 0,
            outputTokens: 0,
            thinkingTokens: 0,
            totalTokens: 0,
            billable: true,
            billingCategory: BillingCategory::USER_REQUEST,
        );

        Event::fake([AIUsageThresholdReached::class]); // reset fake
        $service->record($organization, $input3);
        Event::assertNotDispatched(AIUsageThresholdReached::class);
    }
}

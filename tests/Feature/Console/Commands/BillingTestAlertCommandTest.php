<?php

namespace Tests\Feature\Console\Commands;

use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingAlertRecipient;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BillingTestAlertCommandTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private OrganizationSubscription $subscription;
    private AiUsagePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'uuid' => \Illuminate\Support\Str::uuid(),
            'slug' => 'test-org',
        ]);
        
        $plan = BillingPlan::create([
            'name' => 'Test Plan',
            'code' => 'test-plan',
            'stripe_product_id' => 'prod_test',
            'stripe_price_id' => 'price_test',
            'monthly_price_cents' => 1000,
            'billing_cycle' => 'monthly',
            'included_ai_credits' => 10000,
            'overage_price_per_1000_credits_cents' => 1000, // R$ 10.00
            'is_active' => true,
        ]);

        $this->subscription = OrganizationSubscription::create([
            'organization_id' => $this->organization->id,
            'billing_plan_id' => $plan->id,
            'postpaid_enabled' => true,
            'postpaid_limit_cents' => 5000, // R$ 50.00 limit
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        // This is the REAL period that SHOULD NOT be mutated
        $this->period = AiUsagePeriod::create([
            'organization_id' => $this->organization->id,
            'included_credits' => 10000,
            'billable_credits_used' => 500, // Very small real usage
            'overage_credits' => 0,
            'estimated_overage_cents' => 0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
    }

    public function test_postpaid_75_simulates_correct_context_and_does_not_mutate_db()
    {
        Event::fake();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_75'
        ])->assertSuccessful();

        Event::assertDispatched(AIUsageThresholdReached::class, function (AIUsageThresholdReached $event) {
            $period = $event->period;
            
            // Postpaid limit is R$50.00 (5000 cents). 75% of 5000 = 3750 cents (R$ 37.50)
            // Price per 1000 is 1000 cents. So 3750 cents means 3750 overage credits.
            
            // 1. Never produce overage_credits = 0 for postpaid_75
            $this->assertGreaterThan(0, $period->overage_credits);
            $this->assertEquals(3750, $period->overage_credits);
            $this->assertEquals(3750, $period->estimated_overage_cents);
            
            // Total used = included + overage
            $this->assertEquals(10000 + 3750, $period->billable_credits_used);

            return true;
        });

        // 4. Persisted period is untouched
        $this->period->refresh();
        $this->assertEquals(500, $this->period->billable_credits_used);
        $this->assertEquals(0, $this->period->overage_credits);
    }

    public function test_postpaid_90_simulates_90_percent_of_limit()
    {
        Event::fake();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_90'
        ])->assertSuccessful();

        Event::assertDispatched(AIUsageThresholdReached::class, function (AIUsageThresholdReached $event) {
            // 90% of 5000 = 4500 cents
            $this->assertEquals(4500, $event->period->estimated_overage_cents);
            $this->assertEquals(4500, $event->period->overage_credits); // because price is 1000 cents per 1000 credits
            return true;
        });
    }

    public function test_postpaid_limit_simulates_100_percent_of_limit()
    {
        Event::fake();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_limit'
        ])->assertSuccessful();

        Event::assertDispatched(AIUsageThresholdReached::class, function (AIUsageThresholdReached $event) {
            // 100% of 5000 = 5000 cents
            $this->assertEquals(5000, $event->period->estimated_overage_cents);
            $this->assertEquals(5000, $event->period->overage_credits);
            return true;
        });
    }

    public function test_franchise_alerts_simulate_correctly()
    {
        Event::fake();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'credit_70'
        ])->assertSuccessful();

        Event::assertDispatched(AIUsageThresholdReached::class, function (AIUsageThresholdReached $event) {
            // Included is 10000. 70% is 7000.
            $this->assertEquals(7000, $event->period->billable_credits_used);
            $this->assertEquals(10000, $event->period->included_credits);
            $this->assertEquals(0, $event->period->overage_credits);
            $this->assertEquals(0, $event->period->estimated_overage_cents);
            return true;
        });
    }
}

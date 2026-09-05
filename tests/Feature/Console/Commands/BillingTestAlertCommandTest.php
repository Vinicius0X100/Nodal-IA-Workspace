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

    public function test_postpaid_75_pipeline_processes_queued_listener_and_creates_notifications()
    {
        // Don't fake events, we want the real pipeline to run.
        // We will mock the mailer so it doesn't try to send real emails.
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Notification::fake();
        // Since the listener is queued, we need to run jobs synchronously for the test
        config(['queue.default' => 'sync']);

        // Create a recipient so the notification is actually generated
        $user = \App\Domain\Identity\Models\User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'uuid' => \Illuminate\Support\Str::uuid(),
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($user, ['is_owner' => true]);
        
        BillingAlertRecipient::create([
            'organization_id' => $this->organization->id,
            'recipient_type' => \App\Domain\Billing\Enums\AlertRecipientType::USER,
            'user_id' => $user->id,
            'usage_alerts' => true,
            'is_active' => true,
        ]);

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_75'
        ])->assertSuccessful();

        // 4. BillingAlertEvent is created
        $this->assertDatabaseHas('billing_alert_events', [
            'organization_id' => $this->organization->id,
            'alert_type' => AlertType::POSTPAID_75->value,
            'threshold' => 75,
        ]);
        
        $alertEvent = \App\Domain\Billing\Models\BillingAlertEvent::first();
        $this->assertTrue($alertEvent->metadata_json['is_test'] ?? false);

        // 5. Notification database is created
        \Illuminate\Support\Facades\Notification::assertSentTo(
            [$user], \App\Domain\Billing\Notifications\UsageThresholdNotification::class
        );

        // 6. AiUsagePeriod real não é alterado
        $this->period->refresh();
        $this->assertEquals(500, $this->period->billable_credits_used);
        $this->assertEquals(0, $this->period->overage_credits);
        
        // 7. ai_usage_events não são alterados
        $this->assertDatabaseCount('ai_usage_events', 0);
    }

    public function test_postpaid_90_simulates_90_percent_of_limit()
    {
        Event::fake();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_90'
        ])->assertSuccessful();

        Event::assertDispatched(AIUsageThresholdReached::class, function (AIUsageThresholdReached $event) {
            $context = $event->simulationContext;
            // 90% of 5000 = 4500 cents
            $this->assertEquals(4500, $context['estimated_overage_cents']);
            $this->assertEquals(4500, $context['overage_credits']);
            
            // Event period should be persisted model
            $this->assertNotNull($event->period->id);
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
            $context = $event->simulationContext;
            // 100% of 5000 = 5000 cents
            $this->assertEquals(5000, $context['estimated_overage_cents']);
            $this->assertEquals(5000, $context['overage_credits']);
            
            // Event period should be persisted model
            $this->assertNotNull($event->period->id);
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
            $context = $event->simulationContext;
            // Included is 10000. 70% is 7000.
            $this->assertEquals(7000, $context['billable_credits_used']);
            $this->assertEquals(10000, $context['included_credits']);
            $this->assertEquals(0, $context['overage_credits']);
            $this->assertEquals(0, $context['estimated_overage_cents']);
            
            // Event period should be persisted model
            $this->assertNotNull($event->period->id);
            return true;
        });
    }
}

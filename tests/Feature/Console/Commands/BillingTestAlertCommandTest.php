<?php

namespace Tests\Feature\Console\Commands;

use App\Domain\Billing\Enums\AlertRecipientType;
use App\Domain\Billing\Enums\AlertType;
use App\Domain\Billing\Events\AIUsageThresholdReached;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingAlertRecipient;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Notifications\UsageThresholdNotification;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            'uuid' => Str::uuid(),
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
            'overage_price_per_1000_credits_cents' => 1000, // R$ 10.00 por 1000 créditos
            'is_active' => true,
        ]);

        $this->subscription = OrganizationSubscription::create([
            'organization_id' => $this->organization->id,
            'billing_plan_id' => $plan->id,
            'postpaid_enabled' => true,
            'postpaid_limit_cents' => 5000, // R$ 50.00 de limite pós-pago
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        // Período REAL que NÃO deve ser alterado pela simulação
        $this->period = AiUsagePeriod::create([
            'organization_id' => $this->organization->id,
            'included_credits' => 10000,
            'billable_credits_used' => 500, // Consumo real pequeno
            'overage_credits' => 0,
            'estimated_overage_cents' => 0,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'open',
        ]);
    }

    public function test_postpaid_75_full_pipeline_persists_simulation_context_and_renders_non_zero_mail()
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'uuid' => Str::uuid(),
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($user, ['is_owner' => true]);
        
        BillingAlertRecipient::create([
            'organization_id' => $this->organization->id,
            'recipient_type' => AlertRecipientType::USER,
            'user_id' => $user->id,
            'usage_alerts' => true,
            'is_active' => true,
        ]);

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_75'
        ])->assertSuccessful();

        // 1. BillingAlertEvent criado com flag de teste
        $this->assertDatabaseHas('billing_alert_events', [
            'organization_id' => $this->organization->id,
            'alert_type' => AlertType::POSTPAID_75->value,
            'threshold' => 75,
        ]);
        $alertEvent = \App\Domain\Billing\Models\BillingAlertEvent::first();
        $this->assertTrue($alertEvent->metadata_json['is_test'] ?? false);

        // 2. Notification persistida na tabela 'notifications' com dados simulados
        $dbNotification = DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $this->assertNotNull($dbNotification, 'A notificação deve ter sido salva na tabela notifications');
        
        $data = json_decode($dbNotification->data, true);
        $this->assertEquals(AlertType::POSTPAID_75->value, $data['alert_type']);
        $this->assertEquals(75, $data['threshold']);
        $this->assertEquals(75, $data['percentage']);
        $this->assertTrue($data['is_test']);
        
        // 75% de R$ 50,00 (5000 cents) = 3750 cents. Preço: R$ 10,00/1000 => 3750 créditos excedentes
        $this->assertGreaterThan(0, $data['overage_credits']);
        $this->assertEquals(3750, $data['overage_credits']);
        $this->assertEquals(3750, $data['estimated_overage_cents']);
        $this->assertEquals(5000, $data['postpaid_limit_cents']);
        $this->assertEquals(75, $data['postpaid_percentage']);
        $this->assertEquals(13750, $data['credits_used']); // 10000 + 3750

        // 3. E-mail renderizado com valores simulados coerentes (> R$ 0,00)
        $notificationInstance = new UsageThresholdNotification(
            organization: $this->organization,
            period: $this->period,
            alertType: AlertType::POSTPAID_75,
            threshold: 75,
            percentage: 75.0,
            isTest: true,
            simulationContext: [
                'included_credits' => 10000,
                'billable_credits_used' => 13750,
                'overage_credits' => 3750,
                'estimated_overage_cents' => 3750,
                'postpaid_limit_cents' => 5000,
                'postpaid_percentage' => 75,
            ]
        );
        $mailMessage = $notificationInstance->toMail($user);
        $renderedHtml = (string) $mailMessage->render();

        $this->assertStringContainsString('3.750,00 créditos (~R$ 37,50)', $renderedHtml);
        $this->assertStringNotContainsString('R$ 0,00', $renderedHtml);

        // 4. AiUsagePeriod real permanece estritamente inalterado
        $this->period->refresh();
        $this->assertEquals(500, $this->period->billable_credits_used);
        $this->assertEquals(0, $this->period->overage_credits);
        $this->assertEquals(0, $this->period->estimated_overage_cents);
        
        // 5. ai_usage_events reais não foram criados
        $this->assertDatabaseCount('ai_usage_events', 0);
    }

    public function test_real_notification_without_is_test_uses_real_period_values()
    {
        $user = User::create([
            'name' => 'Real User',
            'email' => 'real@example.com',
            'uuid' => Str::uuid(),
            'password' => bcrypt('password'),
        ]);

        $notification = new UsageThresholdNotification(
            organization: $this->organization,
            period: $this->period,
            alertType: AlertType::CREDIT_USAGE_70,
            threshold: 70,
            percentage: 70.0,
            isTest: false,
            simulationContext: null
        );

        $context = $notification->resolveUsageContext();
        $this->assertEquals(500, $context['billable_credits_used']);
        $this->assertEquals(10000, $context['included_credits']);
        $this->assertEquals(0, $context['overage_credits']);
        $this->assertEquals(0, $context['estimated_overage_cents']);
        $this->assertNull($context['postpaid_limit_cents']);
        $this->assertNull($context['postpaid_percentage']);

        $arrayData = $notification->toArray($user);
        $this->assertEquals(500, $arrayData['credits_used']);
        $this->assertFalse($arrayData['is_test']);
    }

    public function test_postpaid_alert_fails_when_organization_has_no_postpaid_limit()
    {
        $this->subscription->update(['postpaid_limit_cents' => null]);

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_75'
        ])
        ->expectsOutput('Esta organização não possui limite pós-pago configurado. Configure o limite antes de testar alertas pós-pagos.')
        ->assertExitCode(1);
    }

    public function test_postpaid_alert_fails_when_organization_has_no_active_subscription()
    {
        $this->subscription->delete();

        $this->artisan('billing:test-alert', [
            'organizationId' => $this->organization->id,
            'type' => 'postpaid_75'
        ])
        ->expectsOutput('Esta organização não possui uma assinatura ativa para testar alertas pós-pagos.')
        ->assertExitCode(1);
    }

    public function test_notification_queue_is_explicitly_set_to_notifications()
    {
        $notification = new UsageThresholdNotification(
            organization: $this->organization,
            period: $this->period,
            alertType: AlertType::POSTPAID_75,
            threshold: 75,
            percentage: 75.0,
        );

        $this->assertEquals('notifications', $notification->queue);
        $this->assertEquals([
            'mail' => 'notifications',
            'database' => 'notifications',
        ], $notification->viaQueues());
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
            // 90% de 5000 = 4500 cents => 4500 créditos
            $this->assertEquals(4500, $context['estimated_overage_cents']);
            $this->assertEquals(4500, $context['overage_credits']);
            $this->assertEquals(5000, $context['postpaid_limit_cents']);
            $this->assertEquals(90, $context['postpaid_percentage']);
            
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
            // 100% de 5000 = 5000 cents => 5000 créditos
            $this->assertEquals(5000, $context['estimated_overage_cents']);
            $this->assertEquals(5000, $context['overage_credits']);
            $this->assertEquals(5000, $context['postpaid_limit_cents']);
            $this->assertEquals(100, $context['postpaid_percentage']);
            
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
            // Included é 10000. 70% é 7000.
            $this->assertEquals(7000, $context['billable_credits_used']);
            $this->assertEquals(10000, $context['included_credits']);
            $this->assertEquals(0, $context['overage_credits']);
            $this->assertEquals(0, $context['estimated_overage_cents']);
            $this->assertNull($context['postpaid_limit_cents']);
            $this->assertNull($context['postpaid_percentage']);
            
            $this->assertNotNull($event->period->id);
            return true;
        });
    }
}

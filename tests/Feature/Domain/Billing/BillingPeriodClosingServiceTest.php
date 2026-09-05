<?php

namespace Tests\Feature\Domain\Billing;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\DTOs\UsageEventInput;
use App\Domain\Billing\Enums\BillingCategory;
use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Policies\BillingPolicy;
use App\Domain\Billing\Services\AIUsageService;
use App\Domain\Billing\Services\BillingPeriodClosingService;
use App\Domain\Billing\Services\BillingSubscriptionService;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingPeriodClosingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private BillingPlan $planBusiness;
    private OrganizationSubscription $subscription;
    private AiUsagePeriod $period;
    private BillingPeriodClosingService $closingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Acme Corp',
            'uuid' => Str::uuid(),
            'slug' => 'acme-corp',
        ]);

        $this->planBusiness = BillingPlan::create([
            'name'                                 => 'Business',
            'code'                                 => 'business',
            'stripe_product_id'                    => 'prod_biz',
            'stripe_price_id'                      => 'price_biz',
            'monthly_price_cents'                  => 199000, // R$ 1.990,00
            'billing_cycle'                        => 'monthly',
            'included_ai_credits'                  => 50000,  // 50.000 créditos
            'overage_price_per_1000_credits_cents' => 1000,   // R$ 10,00 por 1000 créditos
            'is_active'                            => true,
        ]);

        $this->subscription = OrganizationSubscription::create([
            'organization_id'      => $this->organization->id,
            'billing_plan_id'      => $this->planBusiness->id,
            'status'               => 'active',
            'postpaid_enabled'     => true,
            'postpaid_limit_cents' => 50000, // R$ 500,00 teto de pós-pago
            'current_period_start' => Carbon::parse('2026-09-01 00:00:00'),
            'current_period_end'   => Carbon::parse('2026-09-30 23:59:59'),
        ]);

        // Período de setembro em aberto, vencido (period_end no passado para o fechamento)
        $this->period = AiUsagePeriod::create([
            'organization_id'       => $this->organization->id,
            'subscription_id'       => $this->subscription->id,
            'period_start'          => Carbon::parse('2026-09-01 00:00:00'),
            'period_end'            => Carbon::parse('2026-09-30 23:59:59'),
            'included_credits'      => 50000,
            'billable_credits_used' => 20000, // dentro da franquia
            'overage_credits'       => 0,
            'status'                => 'open',
        ]);

        $this->closingService = app(BillingPeriodClosingService::class);
    }

    public function test_closing_without_overage_bills_only_monthly_subscription()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $invoice = $this->closingService->closePeriod($this->period);

        $this->assertNotNull($invoice);
        $this->assertEquals(199000, $invoice->subtotal_cents);
        $this->assertEquals(0, $invoice->overage_cents);
        $this->assertEquals(199000, $invoice->total_cents);
        $this->assertEquals('draft', $invoice->status->value);
        $this->assertEquals('Business', $invoice->plan_name);

        // Período deve estar com status 'invoiced'
        $this->period->refresh();
        $this->assertEquals('invoiced', $this->period->status);

        // Itens da fatura: apenas mensalidade
        $this->assertCount(1, $invoice->items);
        $subItem = $invoice->items->first();
        $this->assertEquals('subscription', $subItem->type);
        $this->assertEquals(199000, $subItem->amount_cents);
        $this->assertStringContainsString('Plano Business — Setembro/2026', $subItem->description);
    }

    public function test_closing_business_with_overage_bills_monthly_and_overage()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        // Usou 55.000 créditos (5.000 créditos excedentes = R$ 50,00 = 5000 cents)
        $this->period->update([
            'billable_credits_used' => 55000,
        ]);

        $invoice = $this->closingService->closePeriod($this->period);

        $this->assertNotNull($invoice);
        $this->assertEquals(5000, $invoice->overage_cents);
        $this->assertEquals(204000, $invoice->total_cents); // 199000 + 5000

        // Itens da fatura: mensalidade + uso adicional
        $this->assertCount(2, $invoice->items);

        $overageItem = $invoice->items->where('type', 'ai_overage')->first();
        $this->assertNotNull($overageItem);
        $this->assertEquals(5000, $overageItem->amount_cents);
        $this->assertEquals(5000, $overageItem->quantity);
        $this->assertStringContainsString('Uso adicional de IA — Setembro/2026', $overageItem->description);
        $this->assertEquals(5000, $overageItem->metadata_json['overage_credits']);
    }

    public function test_closing_starter_with_overage()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $planStarter = BillingPlan::create([
            'name'                                 => 'Starter',
            'code'                                 => 'starter',
            'monthly_price_cents'                  => 49000, // R$ 490,00
            'included_ai_credits'                  => 5000,
            'overage_price_per_1000_credits_cents' => 1500, // R$ 15,00 por 1000 créditos
            'is_active'                            => true,
        ]);

        $this->subscription->update([
            'billing_plan_id'      => $planStarter->id,
            'postpaid_limit_cents' => 20000,
        ]);

        $this->period->update([
            'included_credits'      => 5000,
            'billable_credits_used' => 7000, // 2000 créditos excedentes = 2 * 1500 = 3000 cents
        ]);

        $invoice = $this->closingService->closePeriod($this->period);

        $this->assertEquals(49000, $invoice->metadata_json['monthly_price_cents']);
        $this->assertEquals(3000, $invoice->overage_cents);
        $this->assertEquals(52000, $invoice->total_cents);
        $this->assertEquals('Starter', $invoice->plan_name);
    }

    public function test_closing_enterprise_with_custom_overrides()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->subscription->update([
            'custom_monthly_price_cents'                  => 500000, // R$ 5.000,00
            'custom_included_ai_credits'                  => 100000, // 100.000 créditos
            'custom_overage_price_per_1000_credits_cents' => 800,    // R$ 8,00 por 1000 créditos
            'postpaid_limit_cents'                        => 200000, // R$ 2.000,00
        ]);

        $this->period->update([
            'included_credits'      => 100000,
            'billable_credits_used' => 120000, // 20.000 créditos excedentes = 20 * 800 = 16000 cents (R$ 160,00)
        ]);

        $invoice = $this->closingService->closePeriod($this->period);

        $this->assertEquals(500000, $invoice->metadata_json['monthly_price_cents']);
        $this->assertEquals(16000, $invoice->overage_cents);
        $this->assertEquals(516000, $invoice->total_cents);
    }

    public function test_postpaid_disabled_does_not_bill_overage_but_preserves_measured_usage()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->subscription->update(['postpaid_enabled' => false]);

        $this->period->update([
            'billable_credits_used' => 60000, // 10.000 excedentes
        ]);

        $invoice = $this->closingService->closePeriod($this->period);

        // Não gera cobrança de excedente na invoice
        $this->assertEquals(0, $invoice->overage_cents);
        $this->assertEquals(199000, $invoice->total_cents);
        $this->assertCount(1, $invoice->items); // Apenas subscription
        $this->assertFalse($invoice->metadata_json['postpaid_enabled']);

        // Consumo medido no AiUsagePeriod é preservado integralmente
        $this->period->refresh();
        $this->assertEquals(60000, $this->period->billable_credits_used);
        $this->assertEquals(10000, $this->period->overage_credits);
    }

    public function test_postpaid_limit_caps_billed_amount_while_preserving_measured_usage()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        // Teto de R$ 500,00 (50000 cents)
        $this->subscription->update([
            'postpaid_enabled'     => true,
            'postpaid_limit_cents' => 50000,
        ]);

        // Consumo excedente de 62.000 créditos a R$ 10,00/1000 = R$ 620,00 (62000 cents)
        $this->period->update([
            'included_credits'      => 50000,
            'billable_credits_used' => 112000, // 112000 - 50000 = 62000 créditos
        ]);

        $invoice = $this->closingService->closePeriod($this->period);

        // Fatura deve cobrar no máximo o teto de R$ 500,00
        $this->assertEquals(50000, $invoice->overage_cents);
        $this->assertEquals(249000, $invoice->total_cents); // 199000 + 50000
        $this->assertTrue($invoice->metadata_json['postpaid_limit_applied']);
        $this->assertEquals(62000, $invoice->metadata_json['raw_calculated_overage_cents']);
        $this->assertEquals(50000, $invoice->metadata_json['billed_overage_cents']);

        // Item de excedente reflete o teto
        $overageItem = $invoice->items->where('type', 'ai_overage')->first();
        $this->assertEquals(50000, $overageItem->amount_cents);
        $this->assertTrue($overageItem->metadata_json['postpaid_limit_applied']);

        // Período preserva o consumo real de 112.000 créditos
        $this->period->refresh();
        $this->assertEquals(112000, $this->period->billable_credits_used);
        $this->assertEquals(62000, $this->period->overage_credits);
    }

    public function test_closed_or_invoiced_period_never_accepts_new_usage()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);
        $this->period->refresh();
        $this->assertEquals('invoiced', $this->period->status);

        $usageBefore = $this->period->billable_credits_used;

        // Tentar atualizar agregados diretamente no período fechado
        $subService = app(BillingSubscriptionService::class);
        $subService->updatePeriodAggregates($this->period, 100, 1.5, true);

        $this->period->refresh();
        $this->assertEquals($usageBefore, $this->period->billable_credits_used, 'Período faturado não deve receber novos agregados');
    }

    public function test_next_period_is_opened_with_renewed_credits_and_no_rollover()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);

        // Próximo período de outubro
        $nextPeriod = AiUsagePeriod::where('organization_id', $this->organization->id)
            ->where('status', 'open')
            ->first();

        $this->assertNotNull($nextPeriod);
        $this->assertEquals('2026-10-01 00:00:00', $nextPeriod->period_start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-31 23:59:59', $nextPeriod->period_end->format('Y-m-d H:i:s'));
        $this->assertEquals(50000, $nextPeriod->included_credits);
        $this->assertEquals(0, $nextPeriod->billable_credits_used);
        $this->assertEquals(0, $nextPeriod->overage_credits);
    }

    public function test_no_temporal_overlap_between_periods()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);

        $nextPeriod = AiUsagePeriod::where('organization_id', $this->organization->id)
            ->where('status', 'open')
            ->first();

        $this->assertEquals('2026-09-30 23:59:59', $this->period->period_end->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-01 00:00:00', $nextPeriod->period_start->format('Y-m-d H:i:s'));
        $this->assertTrue($nextPeriod->period_start->gt($this->period->period_end));
    }

    public function test_subscription_current_period_dates_updated_quietly()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);

        $this->subscription->refresh();
        $this->assertEquals('2026-10-01 00:00:00', $this->subscription->current_period_start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-31 23:59:59', $this->subscription->current_period_end->format('Y-m-d H:i:s'));
    }

    public function test_idempotency_running_closure_multiple_times_creates_only_one_invoice_and_next_period()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $invoice1 = $this->closingService->closePeriod($this->period);
        $invoice2 = $this->closingService->closePeriod($this->period);
        $invoice3 = $this->closingService->closePeriod($this->period);

        $this->assertEquals($invoice1->id, $invoice2->id);
        $this->assertEquals($invoice1->id, $invoice3->id);

        $this->assertEquals(1, BillingInvoice::where('organization_id', $this->organization->id)->count());
        $this->assertEquals(1, AiUsagePeriod::where('organization_id', $this->organization->id)->where('status', 'open')->count());
    }

    public function test_database_level_unique_constraint_prevents_duplicate_invoices()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);

        $this->expectException(QueryException::class);

        BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $this->organization->id,
            'usage_period_id'   => $this->period->id, // Mesma usage_period_id viola UNIQUE
            'period_start'      => $this->period->period_start,
            'period_end'        => $this->period->period_end,
            'status'            => 'draft',
            'subtotal_cents'    => 1000,
            'overage_cents'     => 0,
            'adjustments_cents' => 0,
            'total_cents'       => 1000,
        ]);
    }

    public function test_foreign_key_restrict_prevents_deleting_invoiced_usage_period()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $this->closingService->closePeriod($this->period);

        $this->expectException(QueryException::class);

        // ON DELETE RESTRICT impede exclusão do período faturado
        $this->period->delete();
    }

    public function test_historical_plan_snapshot_immutable_when_subscription_upgrades()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $invoice = $this->closingService->closePeriod($this->period);
        $this->assertEquals('Business', $invoice->plan_name);

        // Em outubro, a organização faz upgrade para Enterprise
        $planEnterprise = BillingPlan::create([
            'name'                => 'Enterprise',
            'code'                => 'enterprise',
            'monthly_price_cents' => 990000,
            'included_ai_credits' => 500000,
            'is_active'           => true,
        ]);

        $this->subscription->update([
            'billing_plan_id' => $planEnterprise->id,
        ]);

        // A fatura histórica de setembro DEVE continuar refletindo Business
        $invoice->refresh();
        $this->assertEquals('Business', $invoice->plan_name);
        $this->assertEquals('Business', $invoice->planDisplayName());
        $this->assertEquals('Business', $invoice->metadata_json['plan_name']);
        $this->assertStringContainsString('Plano Business', $invoice->items->first()->description);
    }

    public function test_usage_at_midnight_one_resolves_to_new_period_even_if_old_period_is_still_open()
    {
        // Setembro ainda está aberto e cron ainda não rodou
        $this->assertEquals('open', $this->period->status);

        $subService = app(BillingSubscriptionService::class);

        // Consumo às 00:01 de 01/10/2026
        $usageTime = Carbon::parse('2026-10-01 00:01:00');
        $resolvedPeriod = $subService->currentPeriod($this->organization, $usageTime);

        // Deve criar/resolver período de outubro, e não o de setembro!
        $this->assertNotEquals($this->period->id, $resolvedPeriod->id);
        $this->assertEquals('2026-10-01 00:00:00', $resolvedPeriod->period_start->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-31 23:59:59', $resolvedPeriod->period_end->format('Y-m-d H:i:s'));
    }

    public function test_dry_run_does_not_persist_any_changes()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $summary = $this->closingService->closeDuePeriods(dryRun: true);

        $this->assertEquals(1, $summary['found']);
        $this->assertEquals(1, $summary['closed']);
        $this->assertEquals(1, $summary['invoices_created']);

        // NENHUMA alteração persistida no banco
        $this->assertEquals(0, BillingInvoice::count());
        $this->period->refresh();
        $this->assertEquals('open', $this->period->status);
        $this->assertEquals(1, AiUsagePeriod::where('organization_id', $this->organization->id)->count());
    }

    public function test_artisan_command_dry_run_and_execution()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        // Dry-run command
        $this->artisan('billing:close-periods', ['--dry-run' => true])
            ->expectsOutputToContain('MODO SIMULAÇÃO')
            ->assertSuccessful();

        $this->assertEquals(0, BillingInvoice::count());

        // Execução real
        $this->artisan('billing:close-periods')
            ->expectsOutputToContain('Todos os períodos foram fechados e faturados com sucesso!')
            ->assertSuccessful();

        $this->assertEquals(1, BillingInvoice::count());
    }

    public function test_audit_log_records_period_closing_and_invoice_creation()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00'));

        $invoice = $this->closingService->closePeriod($this->period);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action'          => 'billing_period_closed',
            'entity_type'     => AiUsagePeriod::class,
            'entity_id'       => (string) $this->period->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action'          => 'billing_invoice_created',
            'entity_type'     => BillingInvoice::class,
            'entity_id'       => (string) $invoice->id,
        ]);
    }

    public function test_billing_policy_view_invoices_authorization()
    {
        $owner = User::create([
            'name'     => 'Owner User',
            'email'    => 'owner@example.com',
            'uuid'     => Str::uuid(),
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($owner, ['is_owner' => true]);

        $regular = User::create([
            'name'     => 'Regular User',
            'email'    => 'regular@example.com',
            'uuid'     => Str::uuid(),
            'password' => bcrypt('password'),
        ]);
        $this->organization->users()->attach($regular, ['is_owner' => false]);

        $policy = app(BillingPolicy::class);

        $this->assertTrue($policy->viewInvoices($owner, $this->organization));
        $this->assertFalse($policy->viewInvoices($regular, $this->organization));
    }
}

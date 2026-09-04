<?php

namespace Tests\Feature\Domain\Billing\Services;

use App\Domain\Billing\Models\AiUsagePeriod;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSubscriptionServiceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_period_when_subscription_created_and_plan_changed()
    {
        $organization = Organization::create([
            'name' => 'Org Test',
            'slug' => 'org-test-' . \Illuminate\Support\Str::random(5),
            'active' => true
        ]);

        // Cria período aberto com uso, mas sem assinatura (included = 0)
        $period = AiUsagePeriod::create([
            'organization_id'       => $organization->id,
            'period_start'          => now()->startOfMonth(),
            'period_end'            => now()->endOfMonth(),
            'status'                => 'open',
            'included_credits'      => 0,
            'billable_credits_used' => 59.5,
            'overage_credits'       => 59.5,
            'estimated_overage_cents' => 595,
        ]);

        // Assinatura Enterprise
        $plan = BillingPlan::create([
            'code' => 'enterprise-test',
            'name' => 'Enterprise',
            'monthly_price_cents' => 499000,
            'included_ai_credits' => 150000,
            'overage_price_per_1000_credits_cents' => 10,
        ]);

        // A criação deve disparar o Observer e sincronizar
        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'billing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now(),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
        ]);

        $period->refresh();
        
        $this->assertEquals($subscription->id, $period->subscription_id);
        $this->assertEquals(150000, $period->included_credits);
        $this->assertEquals(59.5, $period->billable_credits_used);
        $this->assertEquals(0, $period->overage_credits); // Ficou abaixo da franquia
        $this->assertEquals(0, $period->estimated_overage_cents);

        // Atualizar custom_included_ai_credits para menor que o uso (testar recálculo de overage)
        $subscription->update(['custom_included_ai_credits' => 50]);

        $period->refresh();
        $this->assertEquals(50, $period->included_credits);
        $this->assertEquals(9.5, $period->overage_credits); // 59.5 - 50 = 9.5
    }

    public function test_observer_ignores_irrelevant_fields()
    {
        $organization = Organization::create([
            'name' => 'Org Test 2',
            'slug' => 'org-test-' . \Illuminate\Support\Str::random(5),
            'active' => true
        ]);

        $period = AiUsagePeriod::create([
            'organization_id'       => $organization->id,
            'period_start'          => now()->startOfMonth(),
            'period_end'            => now()->endOfMonth(),
            'status'                => 'open',
            'included_credits'      => 0,
            'billable_credits_used' => 0,
            'overage_credits'       => 0,
            'estimated_overage_cents' => 0,
        ]);

        $plan = BillingPlan::create([
            'code' => 'starter-test',
            'name' => 'Starter',
            'monthly_price_cents' => 9900,
            'included_ai_credits' => 150000,
            'overage_price_per_1000_credits_cents' => 10,
        ]);

        $subscription = OrganizationSubscription::create([
            'organization_id' => $organization->id,
            'billing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now(),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
        ]);
        
        // A criação do subscription deve ter sincronizado o periodo para 150000
        $period->refresh();
        $this->assertEquals(150000, $period->included_credits);
        
        // Manualmente alterar o banco sem observer (bypassing model events se possivel, mas podemos apenas usar update e depois testar update no subscription)
        // Ao alterar o included_credits pra 999 diretamente no banco:
        AiUsagePeriod::where('id', $period->id)->update(['included_credits' => 999]);

        // Disparar update no subscription que é irrelevante
        $subscription->update(['metadata_json' => ['test' => 1]]);

        $period->refresh();
        // Não deve ter sincronizado novamente (permanece 999)
        $this->assertEquals(999, $period->included_credits);
    }
}

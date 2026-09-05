<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingInvoicesWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private BillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.api_url'       => 'https://api-sandbox.asaas.com/v3',
            'services.asaas.api_key'       => 'test_key',
            'services.asaas.webhook_token' => 'test_token',
        ]);

        $this->user = User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@sacratech.com',
            'password' => bcrypt('secret123'),
        ]);


        $this->org = Organization::create([
            'name' => 'SacraTech LTDA',
            'slug' => 'sacratech',
            'cnpj' => '12.345.678/0001-90',
        ]);

        $this->org->users()->attach($this->user->id, ['is_owner' => true]);

        $plan = BillingPlan::create([
            'code'                                 => 'business',
            'name'                                 => 'Business',
            'monthly_price_cents'                  => 199000,
            'included_ai_credits'                  => 50000,
            'overage_price_per_1000_credits_cents' => 1500,
            'is_active'                            => true,
        ]);

        $sub = OrganizationSubscription::create([
            'uuid'                     => (string) Str::uuid(),
            'organization_id'          => $this->org->id,
            'billing_plan_id'          => $plan->id,
            'status'                   => 'active',
            'preferred_payment_method' => PaymentMethod::PIX,
        ]);

        $this->invoice = BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $this->org->id,
            'subscription_id'   => $sub->id,
            'plan_name'         => 'Business',
            'period_start'      => Carbon::now()->startOfMonth(),
            'period_end'        => Carbon::now()->endOfMonth(),
            'status'            => InvoiceStatus::DRAFT,
            'subtotal_cents'    => 199000,
            'total_cents'       => 199000,
        ]);
    }

    public function test_invoices_page_loads_successfully_without_500(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->org->id])
            ->get('/settings/billing/invoices');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Billing/Invoices')
            ->has('invoices.data', 1)
        );
    }

    public function test_issue_invoice_via_web_action(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers*' => Http::response(['id' => 'cus_test'], 200),
            'https://api-sandbox.asaas.com/v3/payments/*/pixQrCode' => Http::response([
                'encodedImage' => 'qr_code_pix',
                'payload'      => 'pix_payload',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments*' => Http::response([
                'id'          => 'pay_web_1',
                'customer'    => 'cus_test',
                'value'       => 1990.00,
                'billingType' => 'PIX',
                'status'      => 'PENDING',
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->org->id])
            ->post("/settings/billing/invoices/{$this->invoice->uuid}/issue", [
                'payment_method' => 'pix',
            ]);

        $response->assertRedirect();
        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::ISSUED, $this->invoice->status);
    }

    public function test_payment_details_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->org->id])
            ->getJson("/settings/billing/invoices/{$this->invoice->uuid}/payment-details");

        $response->assertStatus(200);
        $response->assertJson([
            'invoice_status' => 'draft',
        ]);
    }
}

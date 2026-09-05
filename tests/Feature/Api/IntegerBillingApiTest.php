<?php

namespace Tests\Feature\Api;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingInvoiceItem;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Organizations\Models\CompanyVerification;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegerBillingApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private BillingInvoice $invoiceA;
    private BillingInvoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.system.api_key'         => 'system_master_key',
            'services.system.integer_api_key' => 'integer_scoped_key_999',
            'billing.fiscal_service_description' => 'Licenciamento de software SaaS',
        ]);

        // Organização A: com dados completos e verificação aprovada
        $this->orgA = Organization::create([
            'name' => 'Empresa Alfa LTDA',
            'slug' => 'empresa-alfa',
            'cnpj' => '11.111.111/0001-11',
        ]);

        CompanyVerification::create([
            'organization_id'      => $this->orgA->id,
            'company_name'         => 'Empresa Alfa LTDA',
            'trade_name'           => 'Alfa Softwares',
            'cnpj'                 => '11.111.111/0001-11',
            'corporate_email'      => 'financeiro@alfa.com',
            'responsible_name'     => 'Diretor Alfa',
            'responsible_position' => 'CEO',
            'verification_status'  => 'verified',
        ]);

        $planA = BillingPlan::create([
            'code'                                => 'business',
            'name'                                => 'Business',
            'monthly_price_cents'                 => 199000,
            'included_ai_credits'                 => 50000,
            'overage_price_per_1000_credits_cents'=> 1500,
            'is_active'                           => true,
        ]);

        $subA = OrganizationSubscription::create([
            'uuid'                     => (string) Str::uuid(),
            'organization_id'          => $this->orgA->id,
            'billing_plan_id'          => $planA->id,
            'status'                   => 'active',
            'preferred_payment_method' => PaymentMethod::PIX,
        ]);

        $this->invoiceA = BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $this->orgA->id,
            'subscription_id'   => $subA->id,
            'plan_name'         => 'Business',
            'plan_code'         => 'business',
            'period_start'      => Carbon::parse('2026-09-01'),
            'period_end'        => Carbon::parse('2026-09-30'),
            'status'            => InvoiceStatus::ISSUED,
            'subtotal_cents'    => 199750,
            'overage_cents'     => 750,
            'adjustments_cents' => 0,
            'total_cents'       => 199750,
            'metadata_json'     => [
                'customer_snapshot' => [
                    'legal_name'           => 'Empresa Alfa LTDA (Snapshot Histórico)',
                    'trade_name'           => 'Alfa Softwares',
                    'tax_id'               => '11.111.111/0001-11',
                    'billing_email'        => 'financeiro@alfa.com',
                    'fiscal_data_complete' => true,
                ],
                'fiscal_snapshot' => [
                    'service_description' => 'Licenciamento de software SaaS (Snapshot Histórico)',
                ],
                // Custos internos de IA que NUNCA devem vazar para a API do Integer
                'provider_cost_usd' => 1.25,
                'provider_cost_brl' => 6.85,
                'margin_percent'    => 65,
            ],
        ]);

        BillingInvoiceItem::create([
            'invoice_id'        => $this->invoiceA->id,
            'type'              => 'subscription',
            'description'       => 'Plano Business — Setembro/2026',
            'quantity'          => 1,
            'unit_amount_cents' => 199000,
            'amount_cents'      => 199000,
        ]);

        BillingInvoiceItem::create([
            'invoice_id'        => $this->invoiceA->id,
            'type'              => 'ai_overage',
            'description'       => 'Uso adicional de IA — Setembro/2026',
            'quantity'          => 500,
            'unit_amount_cents' => 1500,
            'amount_cents'      => 750,
        ]);

        BillingPayment::create([
            'uuid'                 => (string) Str::uuid(),
            'organization_id'      => $this->orgA->id,
            'billing_invoice_id'   => $this->invoiceA->id,
            'attempt_number'       => 1,
            'provider'             => 'asaas',
            'provider_external_id' => 'pay_alfa_1',
            'payment_method'       => PaymentMethod::PIX,
            'status'               => PaymentStatus::PAID,
            'amount_cents'         => 199750,
            'paid_amount_cents'    => 199750,
            'currency'             => 'BRL',
            'due_date'             => '2026-09-10',
            'paid_at'              => '2026-09-04 15:30:00',
            'idempotency_key'      => 'key_alfa',
        ]);

        // Organização B: sem CNPJ, fatura de competência anterior
        $this->orgB = Organization::create([
            'name' => 'Empresa Beta Inc',
            'slug' => 'empresa-beta',
            'cnpj' => null,
        ]);

        $this->invoiceB = BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $this->orgB->id,
            'period_start'      => Carbon::parse('2026-08-01'),
            'period_end'        => Carbon::parse('2026-08-31'),
            'status'            => InvoiceStatus::DRAFT,
            'subtotal_cents'    => 59900,
            'overage_cents'     => 0,
            'adjustments_cents' => 0,
            'total_cents'       => 59900,
        ]);
    }

    public function test_auth_rejects_missing_or_invalid_key(): void
    {
        // 1. Sem credencial
        $response = $this->getJson('/api/v1/internal/integer/billing/invoices');
        $response->assertStatus(401);

        // 2. Chave incorreta
        $responseWrong = $this->withHeaders([
            'Authorization' => 'Bearer wrong_token',
        ])->getJson('/api/v1/internal/integer/billing/invoices');
        $responseWrong->assertStatus(401);
    }

    public function test_auth_accepts_integer_system_api_key(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer integer_scoped_key_999',
        ])->getJson('/api/v1/internal/integer/billing/invoices');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_response_contract_matches_expected_structure(): void
    {
        $response = $this->withHeaders([
            'X-System-Api-Key' => 'integer_scoped_key_999',
        ])->getJson('/api/v1/internal/integer/billing/invoices?organization_uuid=' . $this->orgA->uuid);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertCount(1, $json['data']);

        $item = $json['data'][0];

        $this->assertSame($this->invoiceA->uuid, $item['invoice_uuid']);
        $this->assertSame($this->orgA->uuid, $item['organization_uuid']);

        // Snapshot histórico congelado deve prevalecer
        $this->assertSame('Empresa Alfa LTDA (Snapshot Histórico)', $item['company']['legal_name']);
        $this->assertSame('Alfa Softwares', $item['company']['trade_name']);
        $this->assertSame('11.111.111/0001-11', $item['company']['tax_id']);
        $this->assertSame('financeiro@alfa.com', $item['company']['billing_email']);
        $this->assertTrue($item['company']['fiscal_data_complete']);

        // Período
        $this->assertSame('2026-09-01', $item['period']['start']);
        $this->assertSame('2026-09-30', $item['period']['end']);
        $this->assertSame('2026-09', $item['period']['competence']);

        // Serviço
        $this->assertSame('Licenciamento de software SaaS (Snapshot Histórico)', $item['service']['description']);

        // Billing
        $this->assertSame('Business', $item['billing']['plan_name']);
        $this->assertSame(199000, $item['billing']['subscription_amount_cents']);
        $this->assertSame(750, $item['billing']['ai_overage_amount_cents']);
        $this->assertSame(199750, $item['billing']['total_amount_cents']);
        $this->assertSame('BRL', $item['billing']['currency']);

        // Invoice status
        $this->assertSame('issued', $item['invoice']['status']);

        // Payment status
        $this->assertSame('asaas', $item['payment']['provider']);
        $this->assertSame('pix', $item['payment']['method']);
        $this->assertSame('paid', $item['payment']['status']);
        $this->assertNotNull($item['payment']['paid_at']);

        // Paginação
        $this->assertArrayHasKey('meta', $json);
        $this->assertSame(1, $json['meta']['total']);
    }

    public function test_tenant_security_strictly_prevents_internal_cost_leakage(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer integer_scoped_key_999',
        ])->getJson('/api/v1/internal/integer/billing/invoices');

        $content = $response->getContent();

        $this->assertStringNotContainsString('provider_cost_usd', $content);
        $this->assertStringNotContainsString('provider_cost_brl', $content);
        $this->assertStringNotContainsString('margin_percent', $content);
        $this->assertStringNotContainsString('test_secret_key', $content);
        $this->assertStringNotContainsString('secret_webhook_token', $content);
    }

    public function test_filter_by_period_competence(): void
    {
        // Consulta Setembro/2026: deve retornar apenas Invoice A
        $resSep = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices?period=2026-09');

        $resSep->assertStatus(200);
        $this->assertCount(1, $resSep->json('data'));
        $this->assertSame($this->invoiceA->uuid, $resSep->json('data.0.invoice_uuid'));

        // Consulta Agosto/2026: deve retornar apenas Invoice B
        $resAug = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices?period=2026-08');

        $resAug->assertStatus(200);
        $this->assertCount(1, $resAug->json('data'));
        $this->assertSame($this->invoiceB->uuid, $resAug->json('data.0.invoice_uuid'));
    }

    public function test_filter_by_payment_status(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices?payment_status=paid');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->invoiceA->uuid, $response->json('data.0.invoice_uuid'));
    }

    public function test_company_without_tax_id_returns_fiscal_data_incomplete(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices?organization_uuid=' . $this->orgB->uuid);

        $response->assertStatus(200);
        $item = $response->json('data.0');

        $this->assertSame('Empresa Beta Inc', $item['company']['legal_name']);
        $this->assertNull($item['company']['tax_id']);
        $this->assertFalse($item['company']['fiscal_data_complete']);
    }

    public function test_show_single_invoice_by_uuid(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices/' . $this->invoiceA->uuid);

        $response->assertStatus(200);
        $this->assertSame($this->invoiceA->uuid, $response->json('data.invoice_uuid'));
        $this->assertSame('Business', $response->json('data.billing.plan_name'));
    }

    public function test_show_returns_404_when_not_found(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer integer_scoped_key_999'])
            ->getJson('/api/v1/internal/integer/billing/invoices/' . Str::uuid());

        $response->assertStatus(404);
    }
}

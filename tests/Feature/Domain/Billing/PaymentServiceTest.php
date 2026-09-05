<?php

namespace Tests\Feature\Domain\Billing;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentAlreadyActiveException;
use App\Domain\Billing\Exceptions\PaymentCustomerDataIncompleteException;
use App\Domain\Billing\Exceptions\PaymentInvalidStateException;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\OrganizationSubscription;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Organizations\Models\CompanyVerification;
use App\Domain\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private Organization $org;
    private OrganizationSubscription $subscription;
    private BillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.api_url'       => 'https://api-sandbox.asaas.com/v3',
            'services.asaas.api_key'       => 'test_secret_key',
            'services.asaas.webhook_token' => 'test_webhook_token',
        ]);

        $this->paymentService = app(PaymentService::class);

        $this->org = Organization::create([
            'name' => 'SacraTech Soluções LTDA',
            'slug' => 'sacratech',
            'cnpj' => '12.345.678/0001-90',
        ]);

        CompanyVerification::create([
            'organization_id'      => $this->org->id,
            'company_name'         => 'SacraTech Soluções LTDA',
            'trade_name'           => 'SacraTech',
            'cnpj'                 => '12.345.678/0001-90',
            'responsible_name'     => 'Vinicius',
            'responsible_position' => 'Diretor',
            'corporate_email'      => 'financeiro@sacratech.com',
            'verification_status'  => 'verified',
        ]);

        $plan = BillingPlan::create([
            'code'                                => 'business',
            'name'                                => 'Business',
            'monthly_price_cents'                 => 199000,
            'included_ai_credits'                 => 50000,
            'overage_price_per_1000_credits_cents'=> 1500,
            'is_active'                           => true,
        ]);

        $this->subscription = OrganizationSubscription::create([
            'uuid'                     => (string) Str::uuid(),
            'organization_id'          => $this->org->id,
            'billing_plan_id'          => $plan->id,
            'status'                   => 'active',
            'preferred_payment_method' => PaymentMethod::PIX,
        ]);

        $this->invoice = BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $this->org->id,
            'subscription_id'   => $this->subscription->id,
            'period_start'      => Carbon::parse('2026-09-01'),
            'period_end'        => Carbon::parse('2026-09-30'),
            'status'            => InvoiceStatus::DRAFT,
            'subtotal_cents'    => 199000,
            'overage_cents'     => 0,
            'adjustments_cents' => 0,
            'total_cents'       => 199000,
        ]);
    }

    private function fakeAsaasEndpoints(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers*' => Http::response([
                'id' => 'cus_000123',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments/*/pixQrCode' => Http::response([
                'encodedImage'   => 'qr_code_pix_base64',
                'payload'        => 'pix_copia_e_cola_code',
                'expirationDate' => '2026-09-10 23:59:59',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments/*/identificationField' => Http::response([
                'identificationField' => '34191.79001 01043.510047 91020.150008 5 99990000019900',
                'barCode'             => '34195999900000199001790001043510049102015000',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments*' => function ($request) {
                static $seq = 1;
                return Http::response([
                    'id'          => 'pay_test_' . ($seq++),
                    'customer'    => 'cus_000123',
                    'value'       => 1990.00,
                    'netValue'    => 1988.01,
                    'billingType' => 'PIX',
                    'status'      => 'PENDING',
                    'bankSlipUrl' => 'https://asaas.com/b/999',
                ], 200);
            },
        ]);
    }

    public function test_issue_invoice_successfully(): void
    {
        $this->fakeAsaasEndpoints();

        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $this->assertSame(1, $payment->attempt_number);
        $this->assertStringStartsWith('pay_test_', $payment->provider_external_id);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);

        $this->assertSame('qr_code_pix_base64', $payment->pix_qr_code);
        $this->assertSame('pix_copia_e_cola_code', $payment->pix_copy_paste);

        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::ISSUED, $this->invoice->status);
        $this->assertNotNull($this->invoice->issued_at);
        $this->assertNotNull($this->invoice->due_at);

        // Verifica auditoria
        $this->assertTrue(AuditLog::where('action', 'billing_payment_created')->exists());
        $this->assertTrue(AuditLog::where('action', 'billing_invoice_issued')->exists());
    }

    public function test_issue_invoice_idempotency_prevents_active_duplicate(): void
    {
        $this->fakeAsaasEndpoints();

        $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $this->expectException(PaymentAlreadyActiveException::class);
        $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);
    }

    public function test_issue_invoice_allows_new_attempt_when_previous_cancelled(): void
    {
        $this->fakeAsaasEndpoints();

        $firstPayment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);
        $firstPayment->update(['status' => PaymentStatus::CANCELLED]);

        // Segunda tentativa com Boleto
        $secondPayment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::BOLETO);

        $this->assertSame(2, $secondPayment->attempt_number);
        $this->assertSame(PaymentMethod::BOLETO, $secondPayment->payment_method);
        $this->assertSame(PaymentStatus::PENDING, $secondPayment->status);
    }

    public function test_issue_invoice_fails_if_customer_data_incomplete(): void
    {
        $orgNoCnpj = Organization::create([
            'name' => 'Empresa Sem CNPJ',
            'slug' => 'sem-cnpj',
        ]);

        $invoiceNoCnpj = BillingInvoice::create([
            'uuid'              => (string) Str::uuid(),
            'organization_id'   => $orgNoCnpj->id,
            'period_start'      => Carbon::now()->startOfMonth(),
            'period_end'        => Carbon::now()->endOfMonth(),
            'status'            => InvoiceStatus::DRAFT,
            'total_cents'       => 1000,
        ]);

        $this->expectException(PaymentCustomerDataIncompleteException::class);
        $this->paymentService->issueInvoice($invoiceNoCnpj, PaymentMethod::PIX);
    }

    public function test_cancel_invoice_voids_invoice_and_cancels_charge(): void
    {
        $this->fakeAsaasEndpoints();

        $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $cancelled = $this->paymentService->cancelInvoice($this->invoice, 'Solicitado pelo cliente');
        $this->assertTrue($cancelled);

        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::VOID, $this->invoice->status);

        $payment = BillingPayment::where('billing_invoice_id', $this->invoice->id)->first();
        $this->assertSame(PaymentStatus::CANCELLED, $payment->status);

        // Tentar cancelar novamente fatura quitada deve falhar
        $this->invoice->update(['status' => InvoiceStatus::PAID]);
        $this->expectException(PaymentInvalidStateException::class);
        $this->paymentService->cancelInvoice($this->invoice);
    }

    public function test_webhook_payment_received_exact_amount_marks_paid(): void
    {
        $this->fakeAsaasEndpoints();
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $event = BillingPaymentWebhookEvent::create([
            'provider'                     => 'asaas',
            'provider_event_id'            => 'evt_test_001',
            'event_name'                   => 'PAYMENT_RECEIVED',
            'provider_external_payment_id' => $payment->provider_external_id,
            'payload_json'                 => [
                'event'   => 'PAYMENT_RECEIVED',
                'payment' => [
                    'id'          => $payment->provider_external_id,
                    'value'       => 1990.00,
                    'netValue'    => 1988.01,
                    'paymentDate' => '2026-09-04',
                ],
            ],
            'status'      => 'received',
            'received_at' => now(),
        ]);

        $this->paymentService->processWebhookEvent($event);

        $payment->refresh();
        $this->invoice->refresh();

        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame(199000, $payment->paid_amount_cents);
        $this->assertSame(InvoiceStatus::PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);

        $this->assertTrue(AuditLog::where('action', 'billing_payment_paid')->exists());
        $this->assertTrue(AuditLog::where('action', 'billing_invoice_paid')->exists());
    }

    public function test_webhook_payment_received_discrepant_amount_marks_needs_review(): void
    {
        $this->fakeAsaasEndpoints();
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $event = BillingPaymentWebhookEvent::create([
            'provider'                     => 'asaas',
            'provider_event_id'            => 'evt_test_discrepancy',
            'event_name'                   => 'PAYMENT_RECEIVED',
            'provider_external_payment_id' => $payment->provider_external_id,
            'payload_json'                 => [
                'event'   => 'PAYMENT_RECEIVED',
                'payment' => [
                    'id'          => $payment->provider_external_id,
                    'value'       => 1500.00, // Divergente de 1990.00
                    'netValue'    => 1498.00,
                    'paymentDate' => '2026-09-04',
                ],
            ],
            'status'      => 'received',
            'received_at' => now(),
        ]);

        $this->paymentService->processWebhookEvent($event);

        $payment->refresh();
        $this->invoice->refresh();

        // Pagamento vai para needs_review e invoice permanece issued!
        $this->assertSame(PaymentStatus::NEEDS_REVIEW, $payment->status);
        $this->assertSame(150000, $payment->paid_amount_cents);
        $this->assertSame(InvoiceStatus::ISSUED, $this->invoice->status);
        $this->assertNull($this->invoice->paid_at);

        $this->assertTrue(AuditLog::where('action', 'billing_payment_discrepancy')->exists());
    }

    public function test_webhook_payment_overdue(): void
    {
        $this->fakeAsaasEndpoints();
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::BOLETO);

        $event = BillingPaymentWebhookEvent::create([
            'provider'                     => 'asaas',
            'provider_event_id'            => 'evt_test_overdue',
            'event_name'                   => 'PAYMENT_OVERDUE',
            'provider_external_payment_id' => $payment->provider_external_id,
            'payload_json'                 => [
                'event'   => 'PAYMENT_OVERDUE',
                'payment' => [
                    'id' => $payment->provider_external_id,
                ],
            ],
            'status'      => 'received',
            'received_at' => now(),
        ]);

        $this->paymentService->processWebhookEvent($event);

        $payment->refresh();
        $this->invoice->refresh();

        $this->assertSame(PaymentStatus::OVERDUE, $payment->status);
        $this->assertSame(InvoiceStatus::ISSUED, $this->invoice->status);
    }

    public function test_webhook_payment_refunded(): void
    {
        $this->fakeAsaasEndpoints();
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $event = BillingPaymentWebhookEvent::create([
            'provider'                     => 'asaas',
            'provider_event_id'            => 'evt_test_refund',
            'event_name'                   => 'PAYMENT_REFUNDED',
            'provider_external_payment_id' => $payment->provider_external_id,
            'payload_json'                 => [
                'event'   => 'PAYMENT_REFUNDED',
                'payment' => [
                    'id' => $payment->provider_external_id,
                ],
            ],
            'status'      => 'received',
            'received_at' => now(),
        ]);

        $this->paymentService->processWebhookEvent($event);

        $payment->refresh();
        $this->invoice->refresh();

        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        // Fatura NÃO se torna void
        $this->assertNotSame(InvoiceStatus::VOID, $this->invoice->status);
        $this->assertTrue(AuditLog::where('action', 'billing_payment_refunded')->exists());
    }

    public function test_issue_invoice_persists_exact_real_asaas_payload_and_preserves_commercial_due_date(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers*' => Http::response(['id' => 'cus_000123'], 200),
            'https://api-sandbox.asaas.com/v3/payments/*/pixQrCode' => Http::response([
                'success'        => true,
                'encodedImage'   => 'BASE64_REAL_CODE',
                'payload'        => 'PIX_PAYLOAD_REAL_STRING',
                'expirationDate' => '2027-09-10 23:59:59',
                'description'    => 'Fatura Nodal Business',
            ], 200),
            'https://api-sandbox.asaas.com/v3/payments*' => Http::response([
                'id'          => 'pay_jfsowtwo4p81cqf5',
                'customer'    => 'cus_000123',
                'value'       => 1990.00,
                'netValue'    => 1988.01,
                'billingType' => 'PIX',
                'status'      => 'PENDING',
                'dueDate'     => '2026-09-10',
            ], 200),
        ]);

        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX, dueDays: 5);

        // Asserts do PIX
        $this->assertSame('pay_jfsowtwo4p81cqf5', $payment->provider_external_id);
        $this->assertSame('BASE64_REAL_CODE', $payment->pix_qr_code);
        $this->assertSame('PIX_PAYLOAD_REAL_STRING', $payment->pix_copy_paste);
        $this->assertSame('2027-09-10T23:59:59.000000Z', $payment->metadata_json['pix_expires_at'] ?? null);

        // Assert crucial: o vencimento comercial da fatura NÃO foi sobrescrito pela validade de 2027 do Asaas!
        $this->invoice->refresh();
        $this->assertNotSame('2027-09-10', $this->invoice->due_at->format('Y-m-d'));
        $this->assertSame(now()->addDays(5)->format('Y-m-d'), $this->invoice->due_at->format('Y-m-d'));
    }

    public function test_issue_invoice_keeps_invoice_issued_and_payment_pending_when_pix_qr_retrieval_fails(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers*' => Http::response(['id' => 'cus_000123'], 200),
            // Falha temporária no endpoint do QR code
            'https://api-sandbox.asaas.com/v3/payments/*/pixQrCode' => Http::response([
                'errors' => [['description' => 'QR Code temporariamente indisponível.']],
            ], 500),
            'https://api-sandbox.asaas.com/v3/payments*' => Http::response([
                'id'          => 'pay_temporary_qr_fail',
                'customer'    => 'cus_000123',
                'value'       => 1990.00,
                'billingType' => 'PIX',
                'status'      => 'PENDING',
            ], 200),
        ]);

        // Não deve lançar exceção nem falhar a emissão da cobrança
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        $this->assertSame('pay_temporary_qr_fail', $payment->provider_external_id);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);
        $this->assertNull($payment->pix_qr_code);
        $this->assertNull($payment->pix_copy_paste);
        $this->assertNotNull($payment->metadata_json['instruction_retrieval_error'] ?? null);

        // Fatura permanece emitida normalmente
        $this->invoice->refresh();
        $this->assertSame(InvoiceStatus::ISSUED, $this->invoice->status);
    }

    public function test_refresh_payment_instructions_updates_existing_payment_without_creating_new_charge(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers*' => Http::response(['id' => 'cus_000123'], 200),
            // Primeira chamada no issueInvoice falha na obtenção do QR Code
            'https://api-sandbox.asaas.com/v3/payments/pay_existing_123/pixQrCode' => Http::sequence()
                ->push(['errors' => [['description' => 'Ainda processando']]], 500)
                ->push([
                    'success'        => true,
                    'encodedImage'   => 'RECOVERED_BASE64',
                    'payload'        => 'RECOVERED_PIX_PAYLOAD',
                    'expirationDate' => '2027-09-10 23:59:59',
                ], 200),
            'https://api-sandbox.asaas.com/v3/payments*' => Http::response([
                'id'          => 'pay_existing_123',
                'customer'    => 'cus_000123',
                'value'       => 1990.00,
                'billingType' => 'PIX',
                'status'      => 'PENDING',
            ], 200),
        ]);

        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);
        $this->assertNull($payment->pix_copy_paste);
        $initialAttempt = $payment->attempt_number;
        $initialIdempotency = $payment->idempotency_key;
        $initialAmount = $payment->amount_cents;

        // Agora executa o refresh isolado
        $refreshed = $this->paymentService->refreshPaymentInstructions($payment);

        $this->assertSame('RECOVERED_PIX_PAYLOAD', $refreshed->pix_copy_paste);
        $this->assertSame('RECOVERED_BASE64', $refreshed->pix_qr_code);
        $this->assertSame('2027-09-10T23:59:59.000000Z', $refreshed->metadata_json['pix_expires_at'] ?? null);

        // Garante que NADA estrutural ou financeiro foi alterado
        $this->assertSame($initialAttempt, $refreshed->attempt_number);
        $this->assertSame($initialIdempotency, $refreshed->idempotency_key);
        $this->assertSame($initialAmount, $refreshed->amount_cents);
        $this->assertSame('pay_existing_123', $refreshed->provider_external_id);
        $this->assertSame(1, BillingPayment::where('billing_invoice_id', $this->invoice->id)->count());

        // Garante que NENHUM novo POST para /payments foi disparado (apenas o POST inicial e chamadas GET)
        Http::assertSent(function ($request) {
            return true;
        });
        $postPaymentsCount = 0;
        foreach (Http::recorded() as [$request, $response]) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/payments')) {
                $postPaymentsCount++;
            }
        }
        $this->assertSame(1, $postPaymentsCount, 'Não deve criar nenhuma segunda cobrança no provedor!');
    }

    public function test_refresh_payment_instructions_validates_eligibility(): void
    {
        $this->fakeAsaasEndpoints();
        $payment = $this->paymentService->issueInvoice($this->invoice, PaymentMethod::PIX);

        // 1. Sem provider_external_id
        $payment->update(['provider_external_id' => null]);
        try {
            $this->paymentService->refreshPaymentInstructions($payment);
            $this->fail('Deveria ter lançado exceção para pagamento sem external ID');
        } catch (PaymentInvalidStateException $e) {
            $this->assertStringContainsString('identificador externo', $e->getMessage());
        }

        // 2. Status cancelado
        $payment->update([
            'provider_external_id' => 'pay_test_cancelled',
            'status'               => PaymentStatus::CANCELLED,
        ]);
        try {
            $this->paymentService->refreshPaymentInstructions($payment);
            $this->fail('Deveria ter lançado exceção para pagamento cancelado');
        } catch (PaymentInvalidStateException $e) {
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        // 3. Status já pago (não deve chamar provedor)
        $payment->update([
            'provider_external_id' => 'pay_test_paid',
            'status'               => PaymentStatus::PAID,
        ]);
        $returned = $this->paymentService->refreshPaymentInstructions($payment);
        $this->assertSame(PaymentStatus::PAID, $returned->status);
    }
}

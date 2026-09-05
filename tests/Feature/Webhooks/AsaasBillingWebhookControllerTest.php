<?php

namespace Tests\Feature\Webhooks;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Jobs\ProcessBillingPaymentWebhookJob;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AsaasBillingWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.webhook_token' => 'secret_webhook_token_123',
        ]);
    }

    public function test_webhook_rejects_missing_or_invalid_token(): void
    {
        // 1. Sem token
        $response = $this->postJson('/api/webhooks/billing/asaas', [
            'event' => 'PAYMENT_RECEIVED',
        ]);
        $response->assertStatus(401);

        // 2. Token incorreto
        $responseWrong = $this->withHeaders([
            'asaas-access-token' => 'invalid_token',
        ])->postJson('/api/webhooks/billing/asaas', [
            'event' => 'PAYMENT_RECEIVED',
        ]);
        $responseWrong->assertStatus(401);
    }

    public function test_webhook_accepts_valid_payload_persists_event_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'id'          => 'evt_asaas_1001',
            'event'       => 'PAYMENT_RECEIVED',
            'dateCreated' => '2026-09-04 22:00:00',
            'payment'     => [
                'id'          => 'pay_0001',
                'customer'    => 'cus_0001',
                'value'       => 1990.00,
                'billingType' => 'PIX',
                'status'      => 'RECEIVED',
            ],
        ];

        $response = $this->withHeaders([
            'asaas-access-token' => 'secret_webhook_token_123',
        ])->postJson('/api/webhooks/billing/asaas', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('billing_payment_webhook_events', [
            'provider'                     => 'asaas',
            'provider_event_id'            => 'evt_asaas_1001',
            'event_name'                   => 'PAYMENT_RECEIVED',
            'provider_external_payment_id' => 'pay_0001',
            'status'                       => 'received',
        ]);

        Queue::assertPushedOn('billing', ProcessBillingPaymentWebhookJob::class);
    }

    public function test_webhook_duplicate_acknowledged_idempotently(): void
    {
        Queue::fake();

        $payload = [
            'id'      => 'evt_asaas_dup',
            'event'   => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_0002',
            ],
        ];

        // Primeira chamada
        $res1 = $this->withHeaders(['asaas-access-token' => 'secret_webhook_token_123'])
            ->postJson('/api/webhooks/billing/asaas', $payload);
        $res1->assertStatus(200);

        // Segunda chamada idêntica
        $res2 = $this->withHeaders(['asaas-access-token' => 'secret_webhook_token_123'])
            ->postJson('/api/webhooks/billing/asaas', $payload);
        $res2->assertStatus(200);

        // Garante que só há 1 registro no banco
        $this->assertSame(1, BillingPaymentWebhookEvent::where('provider_event_id', 'evt_asaas_dup')->count());
        Queue::assertPushed(ProcessBillingPaymentWebhookJob::class, 1);
    }

    public function test_full_pipeline_webhook_to_job_to_database_persists_real_utc_timestamp(): void
    {
        // Cria organização, invoice e payment com valor de R$ 5,00 (500 centavos)
        $org = Organization::create([
            'name' => 'Acme Test',
            'slug' => 'acme-test',
        ]);

        $invoice = BillingInvoice::create([
            'uuid'               => (string) Str::uuid(),
            'organization_id'    => $org->id,
            'status'             => InvoiceStatus::ISSUED,
            'period_start'       => now()->subMonth(),
            'period_end'         => now(),
            'subtotal_cents'     => 500,
            'overage_cents'      => 0,
            'adjustments_cents'  => 0,
            'total_cents'        => 500,
            'currency'           => 'BRL',
            'plan_name'          => 'Nodal Business',
            'due_at'             => now()->addDays(3),
        ]);

        $payment = BillingPayment::create([
            'uuid'                 => (string) Str::uuid(),
            'organization_id'      => $org->id,
            'billing_invoice_id'   => $invoice->id,
            'attempt_number'       => 1,
            'provider'             => 'asaas',
            'provider_external_id' => 'pay_real_boleto_001',
            'payment_method'       => PaymentMethod::BOLETO,
            'status'               => PaymentStatus::PENDING,
            'amount_cents'         => 500,
            'currency'             => 'BRL',
            'due_date'             => now()->addDays(3)->toDateString(),
            'idempotency_key'      => 'test_pipeline_boleto_001',
        ]);

        $payload = [
            'id'          => 'evt_real_boleto_001',
            'event'       => 'PAYMENT_RECEIVED',
            'dateCreated' => '2026-09-05 11:29:07',
            'payment'     => [
                'id'            => 'pay_real_boleto_001',
                'value'         => 5.00,
                'paymentDate'   => '2026-09-05',
                'confirmedDate' => '2026-09-05',
            ],
        ];

        // 1. HTTP POST webhook -> Controller
        $response = $this->withHeaders([
            'asaas-access-token' => 'secret_webhook_token_123',
        ])->postJson('/api/webhooks/billing/asaas', $payload);

        $response->assertStatus(200);

        // 2. BillingPaymentWebhookEvent persistido e processado via queue sync
        $event = BillingPaymentWebhookEvent::where('provider_event_id', 'evt_real_boleto_001')->firstOrFail();
        $this->assertSame('processed', $event->status);

        // 4. Verificação no banco de dados
        $payment->refresh();
        $invoice->refresh();

        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);

        // Não pode ser midnight 00:00:00
        $this->assertNotSame('2026-09-05 00:00:00', $payment->paid_at->format('Y-m-d H:i:s'));
        $this->assertNotSame('00:00:00', $payment->paid_at->format('H:i:s'));
        $this->assertNotSame('2026-09-05 00:00:00', $invoice->paid_at->format('Y-m-d H:i:s'));
        $this->assertNotSame('00:00:00', $invoice->paid_at->format('H:i:s'));

        // Em UTC (app.timezone): 11:29:07 America/Sao_Paulo corresponde a 14:29:07 UTC
        $this->assertSame('2026-09-05 14:29:07', $payment->paid_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 14:29:07', $invoice->paid_at->setTimezone('UTC')->format('Y-m-d H:i:s'));

        // Em America/Sao_Paulo (origem do Asaas): corresponde a 11:29:07
        $this->assertSame('2026-09-05 11:29:07', $payment->paid_at->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 11:29:07', $invoice->paid_at->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'));
    }

    public function test_pipeline_async_queue_worker_execution_persists_exact_timestamp(): void
    {
        Queue::fake();

        $org = Organization::create([
            'name' => 'Acme Async',
            'slug' => 'acme-async',
        ]);

        $invoice = BillingInvoice::create([
            'uuid'               => (string) Str::uuid(),
            'organization_id'    => $org->id,
            'status'             => InvoiceStatus::ISSUED,
            'period_start'       => now()->subMonth(),
            'period_end'         => now(),
            'subtotal_cents'     => 500,
            'overage_cents'      => 0,
            'adjustments_cents'  => 0,
            'total_cents'        => 500,
            'currency'           => 'BRL',
            'plan_name'          => 'Nodal Business',
            'due_at'             => now()->addDays(3),
        ]);

        $payment = BillingPayment::create([
            'uuid'                 => (string) Str::uuid(),
            'organization_id'      => $org->id,
            'billing_invoice_id'   => $invoice->id,
            'attempt_number'       => 1,
            'provider'             => 'asaas',
            'provider_external_id' => 'pay_async_001',
            'payment_method'       => PaymentMethod::BOLETO,
            'status'               => PaymentStatus::PENDING,
            'amount_cents'         => 500,
            'currency'             => 'BRL',
            'due_date'             => now()->addDays(3)->toDateString(),
            'idempotency_key'      => 'test_async_001',
        ]);

        $payload = [
            'id'          => 'evt_async_001',
            'event'       => 'PAYMENT_RECEIVED',
            'dateCreated' => '2026-09-05 11:29:07',
            'payment'     => [
                'id'            => 'pay_async_001',
                'value'         => 5.00,
                'paymentDate'   => '2026-09-05',
                'confirmedDate' => '2026-09-05',
            ],
        ];

        $response = $this->withHeaders([
            'asaas-access-token' => 'secret_webhook_token_123',
        ])->postJson('/api/webhooks/billing/asaas', $payload);

        $response->assertStatus(200);

        $capturedEvent = null;
        Queue::assertPushedOn('billing', ProcessBillingPaymentWebhookJob::class, function ($job) use (&$capturedEvent) {
            $capturedEvent = BillingPaymentWebhookEvent::find($job->webhookEventId);
            return $capturedEvent && $capturedEvent->provider_event_id === 'evt_async_001';
        });

        $this->assertNotNull($capturedEvent);
        $this->assertSame('received', $capturedEvent->status);

        // Simula a execução do worker da fila billing
        (new ProcessBillingPaymentWebhookJob($capturedEvent->id))->handle(app(PaymentService::class));

        $capturedEvent->refresh();
        $this->assertSame('processed', $capturedEvent->status);

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);
        $this->assertSame('2026-09-05 14:29:07', $payment->paid_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 14:29:07', $invoice->paid_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 11:29:07', $payment->paid_at->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-05 11:29:07', $invoice->paid_at->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'));
    }
}

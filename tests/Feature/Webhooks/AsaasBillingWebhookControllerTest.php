<?php

namespace Tests\Feature\Webhooks;

use App\Domain\Billing\Jobs\ProcessBillingPaymentWebhookJob;
use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
}

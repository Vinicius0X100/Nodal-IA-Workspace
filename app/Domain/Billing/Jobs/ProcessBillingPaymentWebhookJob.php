<?php

namespace App\Domain\Billing\Jobs;

use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use App\Domain\Billing\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBillingPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->onQueue('billing');
    }

    public function handle(PaymentService $paymentService): void
    {
        $event = BillingPaymentWebhookEvent::find($this->webhookEventId);

        if (!$event) {
            Log::warning("ProcessBillingPaymentWebhookJob: Evento {$this->webhookEventId} não encontrado.");
            return;
        }

        if ($event->status === 'processed') {
            return; // Idempotente
        }

        $event->update(['status' => 'processing']);

        try {
            $paymentService->processWebhookEvent($event);
        } catch (\Throwable $e) {
            $event->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Erro ao processar webhook de pagamento {$this->webhookEventId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

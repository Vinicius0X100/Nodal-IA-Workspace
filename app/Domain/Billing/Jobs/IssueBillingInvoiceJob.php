<?php

namespace App\Domain\Billing\Jobs;

use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueBillingInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $invoiceId,
    ) {
        $this->onQueue('billing');
    }

    public function handle(PaymentService $paymentService): void
    {
        $invoice = BillingInvoice::find($this->invoiceId);

        if (!$invoice) {
            Log::warning("IssueBillingInvoiceJob: Fatura {$this->invoiceId} não encontrada.");
            return;
        }

        try {
            $paymentService->issueInvoice($invoice);
        } catch (\Throwable $e) {
            Log::error("Erro ao emitir cobrança automática para fatura {$this->invoiceId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

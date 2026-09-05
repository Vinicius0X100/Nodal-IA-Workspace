<?php

namespace App\Console\Commands;

use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Services\PaymentService;
use Illuminate\Console\Command;

class BillingRefreshPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:refresh-payment {payment_id : ID local do BillingPayment ou provider_external_id do Asaas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recupera e atualiza com segurança as instruções de pagamento (PIX QR code / Boleto) sem criar nova cobrança externa';

    /**
     * Execute the console command.
     */
    public function handle(PaymentService $paymentService): int
    {
        $idOrExternal = trim($this->argument('payment_id'));

        $payment = is_numeric($idOrExternal)
            ? BillingPayment::with('invoice')->find($idOrExternal)
            : BillingPayment::with('invoice')->where('provider_external_id', $idOrExternal)->first();

        if (!$payment) {
            $this->error("BillingPayment '{$idOrExternal}' não foi encontrado no banco de dados.");
            return Command::FAILURE;
        }

        $this->info("Localizado BillingPayment #{$payment->id} (Provider ID: {$payment->provider_external_id})");
        $this->line("Status atual: {$payment->status->value} | Método: {$payment->payment_method->value}");

        try {
            $updated = $paymentService->refreshPaymentInstructions($payment);

            $this->newLine();
            $this->info("Payment #{$updated->id} atualizado com sucesso no Asaas.");

            $pixCopyPasteStatus = !empty($updated->pix_copy_paste)
                ? 'preenchido (' . strlen($updated->pix_copy_paste) . ' chars)'
                : 'vazio';

            $pixQrCodeStatus = !empty($updated->pix_qr_code)
                ? 'preenchido (' . strlen($updated->pix_qr_code) . ' chars / ~' . round(strlen($updated->pix_qr_code) * 0.75 / 1024, 1) . ' KB)'
                : 'vazio';

            $pixExpiresAt = $updated->metadata_json['pix_expires_at'] ?? 'não informado';
            $invoiceDueAt = $updated->invoice?->due_at?->format('d/m/Y') ?? 'não definido';

            $this->table(
                ['Propriedade', 'Valor'],
                [
                    ['Payment ID', $updated->id],
                    ['Provider', $updated->provider],
                    ['External ID', $updated->provider_external_id],
                    ['Status', $updated->status->value],
                    ['Método', $updated->payment_method->value],
                    ['Valor', 'R$ ' . number_format($updated->amount_cents / 100, 2, ',', '.')],
                    ['PIX Copia e Cola', $pixCopyPasteStatus],
                    ['QR Code Base64', $pixQrCodeStatus],
                    ['Expiração PIX (Provedor)', $pixExpiresAt],
                    ['Vencimento Comercial Fatura', $invoiceDueAt],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Falha ao atualizar instruções do pagamento: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

<?php

namespace App\Domain\Billing\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Contracts\PaymentProviderInterface;
use App\Domain\Billing\DTOs\CreatePaymentData;
use App\Domain\Billing\DTOs\PaymentResult;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentAlreadyActiveException;
use App\Domain\Billing\Exceptions\PaymentInvalidStateException;
use App\Domain\Billing\Exceptions\PaymentProviderException;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Models\BillingPayment;
use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use App\Domain\Billing\Services\Asaas\AsaasPaymentStatusMapper;
use App\Domain\Billing\Support\MoneyConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
        private readonly PaymentCustomerService $customerService,
    ) {}

    /**
     * Emite uma cobrança oficial para uma BillingInvoice.
     * Segue rigorosamente o fluxo em 2 transações sem prender lock/transação durante a chamada HTTP externa.
     *
     * @throws PaymentInvalidStateException
     * @throws PaymentAlreadyActiveException
     * @throws PaymentProviderException
     */
    public function issueInvoice(
        BillingInvoice $invoice,
        ?PaymentMethod $method = null,
        ?int $dueDays = null
    ): BillingPayment {
        $dueDays = $dueDays ?? (int) config('billing.due_days', 5);

        // ═══════════════════════════════════════════════════════════════════
        // TRANSAÇÃO 1: Validação de estado e reserva local da tentativa
        // ═══════════════════════════════════════════════════════════════════
        [$payment, $customer, $resolvedMethod, $dueDate, $externalReference] = DB::transaction(function () use ($invoice, $method, $dueDays) {
            $lockedInvoice = BillingInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === InvoiceStatus::PAID) {
                throw new PaymentInvalidStateException('A fatura já se encontra paga.');
            }

            if ($lockedInvoice->status === InvoiceStatus::VOID) {
                throw new PaymentInvalidStateException('Não é possível emitir cobrança para uma fatura cancelada.');
            }

            // Verifica se já existe cobrança ativa ou pendente de resolução
            $activePayment = BillingPayment::where('billing_invoice_id', $lockedInvoice->id)
                ->whereIn('status', [
                    PaymentStatus::PENDING->value,
                    PaymentStatus::PROCESSING->value,
                    PaymentStatus::OVERDUE->value,
                    PaymentStatus::NEEDS_REVIEW->value,
                ])
                ->first();

            if ($activePayment) {
                if ($activePayment->status === PaymentStatus::NEEDS_REVIEW) {
                    throw new PaymentInvalidStateException('A fatura possui uma cobrança com divergência em análise (needs_review).');
                }
                throw new PaymentAlreadyActiveException("Já existe uma cobrança ativa para esta fatura (Tentativa #{$activePayment->attempt_number}, Status: {$activePayment->status->value}).");
            }

            // Determina método de pagamento
            $subscription = $lockedInvoice->subscription;
            $resolvedMethod = $method
                ?? $subscription?->preferred_payment_method
                ?? null;

            if (!$resolvedMethod) {
                throw new PaymentInvalidStateException('Nenhum método de pagamento selecionado ou configurado para a organização.');
            }

            // Calcula o número da tentativa
            $lastAttemptNumber = (int) BillingPayment::where('billing_invoice_id', $lockedInvoice->id)->max('attempt_number');
            $attemptNumber = $lastAttemptNumber + 1;

            // Resolve cliente no Asaas
            $customer = $this->customerService->getOrCreateCustomer($lockedInvoice->organization);

            // Garante snapshot do cliente e fiscal na fatura se ainda não existirem
            $currentMetadata = $lockedInvoice->metadata_json ?? [];
            if (empty($currentMetadata['customer_snapshot'])) {
                $currentMetadata['customer_snapshot'] = $this->customerService->buildCustomerSnapshot($lockedInvoice->organization);
            }
            if (empty($currentMetadata['fiscal_snapshot'])) {
                $currentMetadata['fiscal_snapshot'] = [
                    'service_description' => config('billing.fiscal_service_description', 'Licenciamento de software SaaS'),
                ];
            }
            $lockedInvoice->update(['metadata_json' => $currentMetadata]);

            $dueDate = Carbon::now()->addDays($dueDays)->endOfDay();
            $externalReference = "nodal:invoice:{$lockedInvoice->uuid}:attempt_{$attemptNumber}";
            $idempotencyKey = "pay:asaas:{$lockedInvoice->uuid}:attempt_{$attemptNumber}:{$resolvedMethod->value}";

            // Cria o registro local da tentativa em status 'processing'
            $payment = BillingPayment::create([
                'uuid'                  => (string) Str::uuid(),
                'organization_id'       => $lockedInvoice->organization_id,
                'billing_invoice_id'    => $lockedInvoice->id,
                'attempt_number'        => $attemptNumber,
                'provider'              => $this->paymentProvider->providerName(),
                'provider_external_id'  => null,
                'payment_method'        => $resolvedMethod,
                'status'                => PaymentStatus::PROCESSING,
                'amount_cents'          => $lockedInvoice->total_cents,
                'paid_amount_cents'     => null,
                'fee_cents'             => null,
                'currency'              => 'BRL',
                'due_date'              => $dueDate->toDateString(),
                'expires_at'            => $dueDate,
                'idempotency_key'       => $idempotencyKey,
                'metadata_json'         => [
                    'external_reference' => $externalReference,
                    'created_by_user_id' => auth()->id(),
                ],
            ]);

            return [$payment, $customer, $resolvedMethod, $dueDate, $externalReference];
        });

        // ═══════════════════════════════════════════════════════════════════
        // FORA DA TRANSAÇÃO: Chamada HTTP externa ao Asaas
        // ═══════════════════════════════════════════════════════════════════
        $chargeResult = null;
        try {
            $createData = new CreatePaymentData(
                externalCustomerId: $customer->external_customer_id,
                amountCents: $payment->amount_cents,
                dueDate: $dueDate,
                paymentMethod: $resolvedMethod,
                description: "Fatura Nodal — {$invoice->planDisplayName()}",
                externalReference: $externalReference,
                idempotencyKey: $payment->idempotency_key,
            );

            $chargeResult = $this->paymentProvider->createCharge($createData);
        } catch (\Throwable $e) {
            Log::error('Erro ao chamar Asaas para criar cobrança', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);

            // Em caso de timeout/erro de rede: tenta reconciliar buscando se a cobrança foi criada no Asaas
            try {
                $chargeResult = $this->paymentProvider->findChargeByExternalReference($externalReference);
            } catch (\Throwable $reconcileError) {
                Log::error('Falha ao tentar reconciliar cobrança Asaas pós-timeout', [
                    'payment_id' => $payment->id,
                    'error'      => $reconcileError->getMessage(),
                ]);
            }

            // Se de fato não foi criada, marca tentativa como falha na Transaction 2
            if (!$chargeResult) {
                DB::transaction(function () use ($payment, $e) {
                    $lockedPayment = BillingPayment::where('id', $payment->id)->lockForUpdate()->first();
                    $lockedPayment?->update([
                        'status'        => PaymentStatus::FAILED,
                        'failed_at'     => now(),
                        'metadata_json' => array_merge($lockedPayment->metadata_json ?? [], [
                            'failure_error' => $e->getMessage(),
                        ]),
                    ]);
                });

                throw new PaymentProviderException("Falha na comunicação com o Asaas: {$e->getMessage()}", 0, $e);
            }
        }

        // Obtém detalhes específicos do método (PIX QR code ou Boleto linha digitável)
        $pixData = null;
        $boletoData = null;
        try {
            if ($resolvedMethod === PaymentMethod::PIX) {
                $pixData = $this->paymentProvider->getPixData($chargeResult->providerExternalId);
            } elseif ($resolvedMethod === PaymentMethod::BOLETO) {
                $boletoData = $this->paymentProvider->getBoletoData($chargeResult->providerExternalId);
            }
        } catch (\Throwable $detailError) {
            Log::warning('Não foi possível obter dados complementares do método imediatamente', [
                'charge_id' => $chargeResult->providerExternalId,
                'error'     => $detailError->getMessage(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // TRANSAÇÃO 2: Persistência do ID externo, atualização de estado e auditoria
        // ═══════════════════════════════════════════════════════════════════
        return DB::transaction(function () use ($payment, $invoice, $chargeResult, $pixData, $boletoData, $dueDate) {
            $lockedPayment = BillingPayment::where('id', $payment->id)->lockForUpdate()->firstOrFail();
            $lockedInvoice = BillingInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            $lockedPayment->update([
                'provider_external_id'  => $chargeResult->providerExternalId,
                'status'                => PaymentStatus::PENDING,
                'fee_cents'             => $chargeResult->feeCents,
                'pix_copy_paste'        => $pixData?->copyPaste,
                'pix_qr_code'           => $pixData?->qrCodeBase64,
                'boleto_barcode'        => $boletoData?->barcode ?? $boletoData?->identificationField,
                'boleto_url'            => $chargeResult->bankSlipUrl,
                'provider_payload_json' => $chargeResult->rawResponse,
            ]);

            $lockedInvoice->update([
                'status'    => InvoiceStatus::ISSUED,
                'issued_at' => $lockedInvoice->issued_at ?? now(),
                'due_at'    => $dueDate,
            ]);

            AuditLog::create([
                'organization_id' => $lockedInvoice->organization_id,
                'user_id'         => auth()->id(),
                'action'          => 'billing_payment_created',
                'entity_type'     => BillingPayment::class,
                'entity_id'       => (string) $lockedPayment->id,
                'metadata'        => [
                    'invoice_id'           => $lockedInvoice->id,
                    'attempt_number'       => $lockedPayment->attempt_number,
                    'payment_method'       => $lockedPayment->payment_method->value,
                    'amount_cents'         => $lockedPayment->amount_cents,
                    'provider'             => $lockedPayment->provider,
                    'provider_external_id' => $lockedPayment->provider_external_id,
                ],
            ]);

            AuditLog::create([
                'organization_id' => $lockedInvoice->organization_id,
                'user_id'         => auth()->id(),
                'action'          => 'billing_invoice_issued',
                'entity_type'     => BillingInvoice::class,
                'entity_id'       => (string) $lockedInvoice->id,
                'metadata'        => [
                    'invoice_uuid' => $lockedInvoice->uuid,
                    'total_cents'  => $lockedInvoice->total_cents,
                    'payment_id'   => $lockedPayment->id,
                    'due_at'       => $dueDate->toIsoString(),
                ],
            ]);

            return $lockedPayment;
        });
    }

    /**
     * Cancela uma fatura não paga e sua respectiva cobrança ativa no provedor externo.
     */
    public function cancelInvoice(BillingInvoice $invoice, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $lockedInvoice = BillingInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status === InvoiceStatus::PAID) {
                throw new PaymentInvalidStateException('Não é permitido cancelar como void uma fatura já quitada.');
            }

            if ($lockedInvoice->status === InvoiceStatus::VOID) {
                return true; // Idempotente
            }

            // Localiza cobrança ativa para cancelar no provedor
            $activePayment = BillingPayment::where('billing_invoice_id', $lockedInvoice->id)
                ->whereIn('status', [PaymentStatus::PENDING->value, PaymentStatus::PROCESSING->value, PaymentStatus::OVERDUE->value])
                ->lockForUpdate()
                ->first();

            if ($activePayment && $activePayment->provider_external_id) {
                try {
                    $this->paymentProvider->cancelCharge($activePayment->provider_external_id);
                } catch (\Throwable $e) {
                    Log::warning('Erro ao cancelar cobrança no provedor Asaas', [
                        'payment_id' => $activePayment->id,
                        'error'      => $e->getMessage(),
                    ]);
                }

                $activePayment->update([
                    'status'       => PaymentStatus::CANCELLED,
                    'cancelled_at' => now(),
                    'metadata_json' => array_merge($activePayment->metadata_json ?? [], [
                        'cancel_reason' => $reason,
                    ]),
                ]);

                AuditLog::create([
                    'organization_id' => $lockedInvoice->organization_id,
                    'user_id'         => auth()->id(),
                    'action'          => 'billing_payment_cancelled',
                    'entity_type'     => BillingPayment::class,
                    'entity_id'       => (string) $activePayment->id,
                    'metadata'        => [
                        'reason' => $reason,
                    ],
                ]);
            }

            $lockedInvoice->update([
                'status' => InvoiceStatus::VOID,
            ]);

            AuditLog::create([
                'organization_id' => $lockedInvoice->organization_id,
                'user_id'         => auth()->id(),
                'action'          => 'billing_invoice_voided',
                'entity_type'     => BillingInvoice::class,
                'entity_id'       => (string) $lockedInvoice->id,
                'metadata'        => [
                    'reason' => $reason,
                ],
            ]);

            return true;
        });
    }

    /**
     * Processa um evento de webhook persistido do Asaas na fila dedicada 'billing'.
     */
    public function processWebhookEvent(BillingPaymentWebhookEvent $event): void
    {
        $payload = $event->payload_json ?? [];
        $eventName = (string) ($event->event_name ?? ($payload['event'] ?? ''));
        $paymentPayload = $payload['payment'] ?? [];

        $externalPaymentId = $event->provider_external_payment_id
            ?? ($paymentPayload['id'] ?? null);

        if (!$externalPaymentId) {
            $event->update([
                'status'        => 'ignored',
                'error_message' => 'Evento sem ID de pagamento externo no payload.',
                'processed_at'  => now(),
            ]);
            return;
        }

        DB::transaction(function () use ($event, $eventName, $paymentPayload, $externalPaymentId) {
            $payment = BillingPayment::where('provider', 'asaas')
                ->where('provider_external_id', $externalPaymentId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                $event->update([
                    'status'        => 'ignored',
                    'error_message' => "Nenhum BillingPayment local associado ao ID Asaas: {$externalPaymentId}",
                    'processed_at'  => now(),
                ]);
                return;
            }

            $invoice = BillingInvoice::where('id', $payment->billing_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $targetStatus = AsaasPaymentStatusMapper::fromEvent($eventName);

            // ── 1. Confirmação / Recebimento de Pagamento ────────────────────
            if (in_array($eventName, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
                $providerAmountCents = MoneyConverter::toCents($paymentPayload['value'] ?? 0);
                $netAmountCents      = isset($paymentPayload['netValue']) ? MoneyConverter::toCents($paymentPayload['netValue']) : null;
                $feeCents            = ($netAmountCents !== null && $providerAmountCents >= $netAmountCents)
                    ? ($providerAmountCents - $netAmountCents)
                    : $payment->fee_cents;

                // Verificação estrita de centavos
                if ($providerAmountCents === $invoice->total_cents) {
                    $paymentDate = isset($paymentPayload['paymentDate'])
                        ? Carbon::parse($paymentPayload['paymentDate'])
                        : now();

                    $payment->update([
                        'status'            => PaymentStatus::PAID,
                        'paid_amount_cents' => $providerAmountCents,
                        'fee_cents'         => $feeCents,
                        'paid_at'           => $paymentDate,
                    ]);

                    $invoice->update([
                        'status'  => InvoiceStatus::PAID,
                        'paid_at' => $paymentDate,
                    ]);

                    AuditLog::create([
                        'organization_id' => $invoice->organization_id,
                        'action'          => 'billing_payment_paid',
                        'entity_type'     => BillingPayment::class,
                        'entity_id'       => (string) $payment->id,
                        'metadata'        => [
                            'amount_cents'      => $providerAmountCents,
                            'fee_cents'         => $feeCents,
                            'paid_at'           => $paymentDate->toIsoString(),
                            'provider_event_id' => $event->provider_event_id,
                        ],
                    ]);

                    AuditLog::create([
                        'organization_id' => $invoice->organization_id,
                        'action'          => 'billing_invoice_paid',
                        'entity_type'     => BillingInvoice::class,
                        'entity_id'       => (string) $invoice->id,
                        'metadata'        => [
                            'total_cents' => $invoice->total_cents,
                            'paid_at'     => $paymentDate->toIsoString(),
                        ],
                    ]);
                } else {
                    // Divergência de centavos: NUNCA marcar invoice como paid
                    $payment->update([
                        'status'            => PaymentStatus::NEEDS_REVIEW,
                        'paid_amount_cents' => $providerAmountCents,
                        'metadata_json'     => array_merge($payment->metadata_json ?? [], [
                            'discrepancy' => [
                                'expected_cents' => $invoice->total_cents,
                                'received_cents' => $providerAmountCents,
                                'detected_at'    => now()->toIsoString(),
                            ],
                        ]),
                    ]);

                    // Toca a fatura para que o Integer receba a atualização no sync incremental
                    $invoice->touch();

                    AuditLog::create([
                        'organization_id' => $invoice->organization_id,
                        'action'          => 'billing_payment_discrepancy',
                        'entity_type'     => BillingPayment::class,
                        'entity_id'       => (string) $payment->id,
                        'metadata'        => [
                            'expected_cents' => $invoice->total_cents,
                            'received_cents' => $providerAmountCents,
                            'invoice_status' => $invoice->status->value,
                        ],
                    ]);
                }
            }

            // ── 2. Vencimento da Cobrança (PAYMENT_OVERDUE) ─────────────────
            elseif ($eventName === 'PAYMENT_OVERDUE') {
                if ($payment->status !== PaymentStatus::PAID) {
                    $payment->update([
                        'status' => PaymentStatus::OVERDUE,
                    ]);
                    // A fatura permanece issued; toca para sync do Integer
                    $invoice->touch();
                }
            }

            // ── 3. Cobrança Deletada / Cancelada no Provedor ─────────────────
            elseif ($eventName === 'PAYMENT_DELETED') {
                if ($payment->status !== PaymentStatus::PAID) {
                    $payment->update([
                        'status'       => PaymentStatus::CANCELLED,
                        'cancelled_at' => now(),
                    ]);
                    $invoice->touch();
                }
            }

            // ── 4. Reembolso Efetuado no Provedor (PAYMENT_REFUNDED) ─────────
            elseif ($eventName === 'PAYMENT_REFUNDED') {
                $payment->update([
                    'status' => PaymentStatus::REFUNDED,
                    'metadata_json' => array_merge($payment->metadata_json ?? [], [
                        'refunded_at' => now()->toIsoString(),
                        'requires_admin_review' => true,
                    ]),
                ]);

                // NÃO transformar invoice em void; apenas auditar e tocar para sync
                $invoice->touch();

                AuditLog::create([
                    'organization_id' => $invoice->organization_id,
                    'action'          => 'billing_payment_refunded',
                    'entity_type'     => BillingPayment::class,
                    'entity_id'       => (string) $payment->id,
                    'metadata'        => [
                        'notice' => 'Pagamento reembolsado no provedor Asaas. Requer tratamento administrativo.',
                    ],
                ]);
            }

            // ── 5. Outros Eventos Mapeados ──────────────────────────────────
            elseif ($targetStatus) {
                $payment->update(['status' => $targetStatus]);
                $invoice->touch();
            }

            $event->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);
        });
    }
}

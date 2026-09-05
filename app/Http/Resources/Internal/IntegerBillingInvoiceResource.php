<?php

namespace App\Http\Resources\Internal;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\BillingInvoice;
use App\Domain\Billing\Services\IntegerBillingExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingInvoice
 */
class IntegerBillingInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var IntegerBillingExportService $exportService */
        $exportService = app(IntegerBillingExportService::class);

        $companyData = $exportService->resolveCompanyData($this->resource);
        $serviceDescription = $exportService->resolveServiceDescription($this->resource);

        // Mensalidade calculada pelo item histórico ou subtotal - overage
        $subscriptionItem = $this->items->firstWhere('type', 'subscription');
        $subscriptionAmountCents = $subscriptionItem
            ? $subscriptionItem->amount_cents
            : max($this->subtotal_cents - $this->overage_cents, 0);

        // Pagamento mais recente
        $latestPayment = $this->latestPayment;
        $paymentStatus = $latestPayment?->status?->value
            ?? ($this->status === InvoiceStatus::PAID ? 'paid' : 'pending');
        $paymentMethod = $latestPayment?->payment_method?->value;
        $paidAt = $latestPayment?->paid_at?->toIsoString()
            ?? $this->paid_at?->toIsoString();

        return [
            'invoice_uuid'      => $this->uuid,
            'organization_uuid' => $this->organization->uuid,

            'company' => [
                'legal_name'           => $companyData['legal_name'] ?? null,
                'trade_name'           => $companyData['trade_name'] ?? null,
                'tax_id'               => $companyData['tax_id'] ?? null,
                'billing_email'        => $companyData['billing_email'] ?? null,
                'phone'                => $companyData['phone'] ?? null,
                'fiscal_data_complete' => (bool) ($companyData['fiscal_data_complete'] ?? false),
            ],

            'period' => [
                'start'      => $this->period_start->toDateString(),
                'end'        => $this->period_end->toDateString(),
                'competence' => $this->period_start->format('Y-m'),
            ],

            'service' => [
                'description' => $serviceDescription,
            ],

            'billing' => [
                'plan_name'                 => $this->planDisplayName(),
                'subscription_amount_cents' => $subscriptionAmountCents,
                'ai_overage_amount_cents'   => $this->overage_cents,
                'total_amount_cents'        => $this->total_cents,
                'currency'                  => 'BRL',
            ],

            'invoice' => [
                'status'    => $this->status->value,
                'issued_at' => $this->issued_at?->toIsoString(),
                'due_at'    => $this->due_at?->toIsoString(),
            ],

            'payment' => [
                'provider' => $latestPayment?->provider ?? 'asaas',
                'method'   => $paymentMethod,
                'status'   => $paymentStatus,
                'paid_at'  => $paidAt,
            ],

            'source_updated_at' => $this->sourceUpdatedAt()->toIsoString(),
            'updated_at'        => $this->updated_at->toIsoString(),
        ];
    }
}

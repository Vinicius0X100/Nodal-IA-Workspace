<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\BillingInvoice;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class IntegerBillingExportService
{
    public function __construct(
        private readonly PaymentCustomerService $customerService,
    ) {}

    /**
     * Consulta faturas com filtros administrativos e paginação para o Integer.
     */
    public function queryInvoices(array $filters = []): LengthAwarePaginator
    {
        $query = BillingInvoice::query()
            ->with([
                'organization.verification',
                'organization.users' => fn ($q) => $q->wherePivot('is_owner', true),
                'items',
                'payments',
                'latestPayment',
            ]);

        $this->applyFilters($query, $filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->orderBy('period_start', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Consulta uma fatura específica por UUID para o Integer.
     */
    public function findInvoiceByUuid(string $uuid): ?BillingInvoice
    {
        return BillingInvoice::where('uuid', $uuid)
            ->with([
                'organization.verification',
                'organization.users' => fn ($q) => $q->wherePivot('is_owner', true),
                'items',
                'payments',
                'latestPayment',
            ])
            ->first();
    }

    /**
     * Aplica os filtros autorizados da API do Integer.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        // 1. Filtro por Competência: YYYY-MM
        if (!empty($filters['period'])) {
            try {
                $date = Carbon::createFromFormat('Y-m', trim($filters['period']))->startOfMonth();
                $query->whereYear('period_start', $date->year)
                    ->whereMonth('period_start', $date->month);
            } catch (\Throwable) {
                // Se formato inválido, ignora ou restringe query
            }
        }

        // 2. Filtro por Status da Fatura
        if (!empty($filters['status'])) {
            $query->where('status', trim($filters['status']));
        }

        // 3. Filtro por Status do Pagamento
        if (!empty($filters['payment_status'])) {
            $paymentStatus = trim($filters['payment_status']);
            $query->whereHas('payments', function (Builder $pq) use ($paymentStatus) {
                $pq->where('status', $paymentStatus);
            });
        }

        // 4. Filtro por UUID da Organização
        if (!empty($filters['organization_uuid'])) {
            $orgUuid = trim($filters['organization_uuid']);
            $query->whereHas('organization', function (Builder $oq) use ($orgUuid) {
                $oq->where('uuid', $orgUuid);
            });
        }

        // 5. Filtro Incremental: updated_since (considera atualização da fatura OU de qualquer pagamento)
        if (!empty($filters['updated_since'])) {
            try {
                $since = Carbon::parse($filters['updated_since']);
                $query->where(function (Builder $q) use ($since) {
                    $q->where('billing_invoices.updated_at', '>=', $since)
                      ->orWhereHas('payments', function (Builder $pq) use ($since) {
                          $pq->where('billing_payments.updated_at', '>=', $since);
                      });
                });
            } catch (\Throwable) {
                // Formato de data inválido ignorado
            }
        }
    }

    /**
     * Resolve os dados cadastrais da empresa preferindo o snapshot congelado da fatura.
     */
    public function resolveCompanyData(BillingInvoice $invoice): array
    {
        $metadata = $invoice->metadata_json ?? [];

        if (!empty($metadata['customer_snapshot']) && is_array($metadata['customer_snapshot'])) {
            return $metadata['customer_snapshot'];
        }

        // Fallback para faturas legadas sem snapshot prévio
        return $this->customerService->buildCustomerSnapshot($invoice->organization);
    }

    /**
     * Resolve a descrição do serviço preferindo o snapshot fiscal da fatura.
     */
    public function resolveServiceDescription(BillingInvoice $invoice): string
    {
        $metadata = $invoice->metadata_json ?? [];

        if (!empty($metadata['fiscal_snapshot']['service_description'])) {
            return (string) $metadata['fiscal_snapshot']['service_description'];
        }

        return (string) config('billing.fiscal_service_description', 'Licenciamento de software SaaS');
    }
}

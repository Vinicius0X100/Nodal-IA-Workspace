<?php

namespace App\Http\Controllers\Api\Internal;

use App\Domain\Billing\Services\IntegerBillingExportService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Internal\IntegerBillingInvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegerBillingApiController extends Controller
{
    public function __construct(
        private readonly IntegerBillingExportService $exportService,
    ) {}

    /**
     * Lista faturas consolidadas de todas as organizações para o sistema administrativo Integer.
     *
     * Filtros suportados:
     * - period: YYYY-MM
     * - status: draft, issued, paid, void
     * - payment_status: pending, paid, overdue, etc.
     * - organization_uuid: string
     * - updated_since: ISO8601 string
     * - per_page: int (1 a 100)
     * - page: int
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period'            => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'status'            => ['nullable', 'string', 'in:draft,issued,paid,void'],
            'payment_status'    => ['nullable', 'string', 'in:pending,processing,paid,failed,cancelled,expired,overdue,refunded,needs_review'],
            'organization_uuid' => ['nullable', 'string', 'size:36'],
            'updated_since'     => ['nullable', 'string'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'              => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->exportService->queryInvoices($validated);

        return response()->json([
            'success' => true,
            'data'    => IntegerBillingInvoiceResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Detalhes de uma fatura específica por UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        $invoice = $this->exportService->findInvoiceByUuid($uuid);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Fatura não encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new IntegerBillingInvoiceResource($invoice),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\Jobs\ProcessBillingPaymentWebhookJob;
use App\Domain\Billing\Models\BillingPaymentWebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasBillingWebhookController extends Controller
{
    /**
     * Endpoint público que recebe eventos do Asaas via POST.
     * Protegido exclusivamente pelo middleware VerifyAsaasWebhookToken.
     * Retorna HTTP 200 rápido e delega o processamento financeiro para a fila dedicada 'billing'.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventId = (string) ($payload['id'] ?? '');
        $eventName = (string) ($payload['event'] ?? '');
        $externalPaymentId = $payload['payment']['id'] ?? null;

        if (empty($eventId)) {
            // Se o Asaas não enviar campo 'id', gera chave determinística pelo event e paymentId
            $eventId = $eventName . ':' . ($externalPaymentId ?? md5(json_encode($payload)));
        }

        try {
            $event = BillingPaymentWebhookEvent::firstOrCreate(
                [
                    'provider'          => 'asaas',
                    'provider_event_id' => $eventId,
                ],
                [
                    'event_name'                   => $eventName,
                    'provider_external_payment_id' => $externalPaymentId,
                    'payload_json'                 => $payload,
                    'status'                       => 'received',
                    'received_at'                  => now(),
                ]
            );

            // Somente enfileira o Job se o evento for recém-criado (proteção contra replay e concorrência)
            if ($event->wasRecentlyCreated) {
                ProcessBillingPaymentWebhookJob::dispatch($event->id)->onQueue('billing');
            }
        } catch (QueryException $e) {
            // Tratamento contra race condition em webhook duplicado no mesmo milissegundo
            Log::info("Webhook Asaas duplicado recebido concorrentemente: {$eventId}");
            return response()->json(['success' => true, 'message' => 'Duplicate event acknowledged.'], 200);
        } catch (\Throwable $e) {
            Log::error("Erro ao persistir webhook do Asaas: {$e->getMessage()}", [
                'event_id' => $eventId,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to process webhook event.'], 500);
        }

        return response()->json(['success' => true], 200);
    }
}

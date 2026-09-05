<?php

namespace App\Domain\Billing\Providers;

use App\Domain\Billing\Contracts\PaymentProviderInterface;
use App\Domain\Billing\DTOs\BoletoData;
use App\Domain\Billing\DTOs\CreatePaymentData;
use App\Domain\Billing\DTOs\PaymentCustomerData;
use App\Domain\Billing\DTOs\PaymentCustomerResult;
use App\Domain\Billing\DTOs\PaymentResult;
use App\Domain\Billing\DTOs\PaymentWebhookData;
use App\Domain\Billing\DTOs\PixData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentProviderException;
use App\Domain\Billing\Services\Asaas\AsaasPaymentStatusMapper;
use App\Domain\Billing\Support\MoneyConverter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasPaymentProvider implements PaymentProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $userAgent;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.asaas.api_url', 'https://api-sandbox.asaas.com/v3'), '/');
        $this->apiKey    = (string) config('services.asaas.api_key', '');
        $this->userAgent = (string) config('services.asaas.user_agent', 'Nodal-Billing/1.0');
        $this->timeout   = (int) config('services.asaas.timeout', 15);
    }

    public function providerName(): string
    {
        return 'asaas';
    }

    /**
     * Cliente HTTP centralizado e autenticado para a API do Asaas.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'access_token' => $this->apiKey,
                'User-Agent'   => $this->userAgent,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout($this->timeout);
    }

    public function syncCustomer(PaymentCustomerData $data, ?string $existingExternalId = null): PaymentCustomerResult
    {
        $cleanCpfCnpj = preg_replace('/\D/', '', $data->cpfCnpj);

        $payload = array_filter([
            'name'                 => $data->name,
            'cpfCnpj'              => $cleanCpfCnpj,
            'email'                => $data->email,
            'phone'                => $data->phone ? preg_replace('/\D/', '', $data->phone) : null,
            'mobilePhone'          => $data->mobilePhone ? preg_replace('/\D/', '', $data->mobilePhone) : null,
            'address'              => $data->address,
            'addressNumber'        => $data->addressNumber,
            'postalCode'           => $data->postalCode ? preg_replace('/\D/', '', $data->postalCode) : null,
            'province'             => $data->province,
            'externalReference'    => $data->externalReference,
            'notificationDisabled' => true,
        ], fn ($val) => !is_null($val) && $val !== '');

        // 1. Se já possui ID externo, faz update no Asaas para refletir eventuais alterações cadastrais
        if ($existingExternalId) {
            $response = $this->client()->put("/customers/{$existingExternalId}", $payload);

            if ($response->successful()) {
                return new PaymentCustomerResult(
                    externalCustomerId: $existingExternalId,
                    isNew: false,
                    rawResponse: $response->json() ?? [],
                );
            }

            // Se retornar 404 (cliente deletado no Asaas), prossegue para busca/criação
            if ($response->status() !== 404) {
                $this->handleErrorResponse($response, "Falha ao atualizar cliente {$existingExternalId} no Asaas");
            }
        }

        // 2. Busca por externalReference para evitar duplicatas em retry
        if ($data->externalReference) {
            $found = $this->findCustomerByExternalReference($data->externalReference);
            if ($found) {
                return $found;
            }
        }

        // 3. Busca por CPF/CNPJ para reutilizar cliente existente
        if ($cleanCpfCnpj) {
            $found = $this->findCustomerByCpfCnpj($cleanCpfCnpj);
            if ($found) {
                // Atualiza a externalReference no cliente existente
                if ($data->externalReference) {
                    $this->client()->put("/customers/{$found->externalCustomerId}", [
                        'externalReference' => $data->externalReference,
                    ]);
                }
                return $found;
            }
        }

        // 4. Criação de novo cliente
        $response = $this->client()->post('/customers', $payload);

        if (!$response->successful()) {
            $this->handleErrorResponse($response, 'Falha ao criar cliente no Asaas');
        }

        $responseData = $response->json();
        $id = $responseData['id'] ?? null;

        if (!$id) {
            throw new PaymentProviderException('Asaas não retornou ID de cliente válido na criação.');
        }

        return new PaymentCustomerResult(
            externalCustomerId: $id,
            isNew: true,
            rawResponse: $responseData,
        );
    }

    public function findCustomerByCpfCnpj(string $cpfCnpj): ?PaymentCustomerResult
    {
        $cleanCpfCnpj = preg_replace('/\D/', '', $cpfCnpj);
        $response = $this->client()->get('/customers', ['cpfCnpj' => $cleanCpfCnpj]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        if (empty($data) || !isset($data[0]['id'])) {
            return null;
        }

        return new PaymentCustomerResult(
            externalCustomerId: $data[0]['id'],
            isNew: false,
            rawResponse: $data[0],
        );
    }

    public function findCustomerByExternalReference(string $externalReference): ?PaymentCustomerResult
    {
        $response = $this->client()->get('/customers', ['externalReference' => $externalReference]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        if (empty($data) || !isset($data[0]['id'])) {
            return null;
        }

        return new PaymentCustomerResult(
            externalCustomerId: $data[0]['id'],
            isNew: false,
            rawResponse: $data[0],
        );
    }

    public function createCharge(CreatePaymentData $data): PaymentResult
    {
        $payload = [
            'customer'          => $data->externalCustomerId,
            'billingType'       => $data->paymentMethod === PaymentMethod::PIX ? 'PIX' : 'BOLETO',
            'value'             => MoneyConverter::toDecimal($data->amountCents),
            'dueDate'           => $data->dueDate->format('Y-m-d'),
            'description'       => $data->description,
            'externalReference' => $data->externalReference,
            'postalService'     => false,
        ];

        $response = $this->client()->post('/payments', $payload);

        if (!$response->successful()) {
            $this->handleErrorResponse($response, 'Falha ao criar cobrança no Asaas');
        }

        $resp = $response->json();
        return $this->parseChargeResponse($resp);
    }

    public function getCharge(string $providerExternalId): PaymentResult
    {
        $response = $this->client()->get("/payments/{$providerExternalId}");

        if (!$response->successful()) {
            $this->handleErrorResponse($response, "Falha ao consultar cobrança {$providerExternalId} no Asaas");
        }

        return $this->parseChargeResponse($response->json());
    }

    public function findChargeByExternalReference(string $externalReference): ?PaymentResult
    {
        $response = $this->client()->get('/payments', ['externalReference' => $externalReference]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json('data');
        if (empty($data) || !isset($data[0]['id'])) {
            return null;
        }

        return $this->parseChargeResponse($data[0]);
    }

    public function cancelCharge(string $providerExternalId): bool
    {
        $response = $this->client()->delete("/payments/{$providerExternalId}");

        if ($response->successful()) {
            return true;
        }

        // Se já foi deletado (404), considera cancelado com sucesso
        if ($response->status() === 404) {
            return true;
        }

        $this->handleErrorResponse($response, "Falha ao cancelar cobrança {$providerExternalId} no Asaas");
        return false;
    }

    public function getPixData(string $providerExternalId): PixData
    {
        $id = trim($providerExternalId);
        $response = $this->client()->get("/payments/{$id}/pixQrCode");

        if (!$response->successful()) {
            $this->handleErrorResponse($response, "Falha ao obter QR Code PIX para cobrança {$id}");
        }

        $data = $response->json();

        return new PixData(
            copyPaste: (string) ($data['payload'] ?? ''),
            qrCodeBase64: (string) ($data['encodedImage'] ?? ''),
            expiresAt: isset($data['expirationDate']) ? Carbon::parse($data['expirationDate']) : null,
        );
    }

    public function getBoletoData(string $providerExternalId): BoletoData
    {
        $id = trim($providerExternalId);
        $response = $this->client()->get("/payments/{$id}/identificationField");

        if (!$response->successful()) {
            $this->handleErrorResponse($response, "Falha ao obter linha digitável do Boleto para {$id}");
        }

        $data = $response->json();

        return new BoletoData(
            barcode: $data['barCode'] ?? null,
            identificationField: $data['identificationField'] ?? null,
            url: null, // Será complementado com bankSlipUrl da cobrança
        );
    }

    public function refundCharge(string $providerExternalId, ?int $amountCents = null): bool
    {
        $payload = [];
        if ($amountCents !== null && $amountCents > 0) {
            $payload['value'] = MoneyConverter::toDecimal($amountCents);
        }

        $response = $this->client()->post("/payments/{$providerExternalId}/refund", $payload);

        if (!$response->successful()) {
            $this->handleErrorResponse($response, "Falha ao solicitar estorno para cobrança {$providerExternalId}");
        }

        return true;
    }

    /**
     * Mapeia array retornado pela API Asaas para o DTO PaymentResult tipado.
     */
    private function parseChargeResponse(array $data): PaymentResult
    {
        $id = (string) ($data['id'] ?? '');
        $statusRaw = (string) ($data['status'] ?? 'PENDING');
        $status = AsaasPaymentStatusMapper::fromChargeStatus($statusRaw) ?? PaymentStatus::PENDING;

        $amountCents = MoneyConverter::toCents($data['value'] ?? 0);
        $netAmountCents = isset($data['netValue']) ? MoneyConverter::toCents($data['netValue']) : null;
        $feeCents = ($netAmountCents !== null && $amountCents >= $netAmountCents)
            ? ($amountCents - $netAmountCents)
            : null;

        $dueDate = isset($data['dueDate']) ? Carbon::parse($data['dueDate']) : null;

        $billingType = strtoupper((string) ($data['billingType'] ?? ''));
        $paymentMethod = match ($billingType) {
            'PIX'    => PaymentMethod::PIX,
            'BOLETO' => PaymentMethod::BOLETO,
            default  => null,
        };

        return new PaymentResult(
            providerExternalId: $id,
            status: $status,
            amountCents: $amountCents,
            netAmountCents: $netAmountCents,
            feeCents: $feeCents,
            dueDate: $dueDate,
            paymentMethod: $paymentMethod,
            bankSlipUrl: $data['bankSlipUrl'] ?? null,
            invoiceUrl: $data['invoiceUrl'] ?? null,
            rawResponse: $data,
        );
    }

    /**
     * Tratamento seguro de erro da API Asaas sem vazar credenciais em logs ou exceptions.
     */
    private function handleErrorResponse(Response $response, string $prefixMessage): void
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $errors = $body['errors'] ?? [];

        $errorMessage = $prefixMessage;
        if (!empty($errors) && is_array($errors)) {
            $desc = $errors[0]['description'] ?? ($errors[0]['code'] ?? '');
            if ($desc) {
                $errorMessage .= ": {$desc}";
            }
        } else {
            $errorMessage .= " (HTTP {$status})";
        }

        Log::warning('Asaas API Error', [
            'status'  => $status,
            'message' => $errorMessage,
            'errors'  => $errors,
        ]);

        throw new PaymentProviderException($errorMessage, $status);
    }

    /**
     * Realiza o parsing estruturado do payload de webhook do Asaas para o DTO PaymentWebhookData.
     * Preserva o timestamp real e auditável dateCreated (convertido de America/Sao_Paulo para UTC).
     * Nunca permite que paymentDate/confirmedDate date-only sejam convertidos em timestamps.
     */
    public function parseWebhook(array $payload, ?CarbonInterface $fallbackReceivedAt = null): PaymentWebhookData
    {
        $eventId = (string) ($payload['id'] ?? '');
        $eventName = (string) ($payload['event'] ?? '');
        $paymentPayload = $payload['payment'] ?? [];
        $externalPaymentId = $paymentPayload['id'] ?? null;

        $targetStatus = AsaasPaymentStatusMapper::fromEvent($eventName);

        $valueCents = isset($paymentPayload['value']) ? MoneyConverter::toCents($paymentPayload['value']) : null;
        $netValueCents = isset($paymentPayload['netValue']) ? MoneyConverter::toCents($paymentPayload['netValue']) : null;
        $feeCents = ($valueCents !== null && $netValueCents !== null && $valueCents >= $netValueCents)
            ? ($valueCents - $netValueCents)
            : null;

        // 1. Timestamp raiz do evento: dateCreated com horário (fuso America/Sao_Paulo do Asaas)
        $eventOccurredAt = null;
        $dateCreatedRaw = $payload['dateCreated'] ?? ($paymentPayload['dateCreated'] ?? null);

        if (!empty($dateCreatedRaw) && is_string($dateCreatedRaw) && strlen(trim($dateCreatedRaw)) > 10) {
            try {
                $asaasTz = config('services.asaas.timezone', 'America/Sao_Paulo');
                $eventOccurredAt = Carbon::parse($dateCreatedRaw, $asaasTz)->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Throwable) {
                // Em caso de falha no parse, segue para fallbacks
            }
        }

        // 2. Fallback: received_at do webhook persistido no banco
        if (!$eventOccurredAt && $fallbackReceivedAt) {
            $eventOccurredAt = Carbon::instance($fallbackReceivedAt)->setTimezone(config('app.timezone', 'UTC'));
        }

        // 3. Fallback final: now()
        if (!$eventOccurredAt) {
            $eventOccurredAt = now();
        }

        return new PaymentWebhookData(
            eventId: $eventId,
            eventName: $eventName,
            providerExternalPaymentId: $externalPaymentId,
            status: $targetStatus,
            valueCents: $valueCents,
            netValueCents: $netValueCents,
            feeCents: $feeCents,
            eventOccurredAt: $eventOccurredAt,
            rawPaymentDate: isset($paymentPayload['paymentDate']) ? (string) $paymentPayload['paymentDate'] : null,
            rawConfirmedDate: isset($paymentPayload['confirmedDate']) ? (string) $paymentPayload['confirmedDate'] : null,
            rawPayload: $payload,
        );
    }
}

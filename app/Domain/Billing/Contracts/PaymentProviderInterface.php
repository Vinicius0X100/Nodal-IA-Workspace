<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\BoletoData;
use App\Domain\Billing\DTOs\CreatePaymentData;
use App\Domain\Billing\DTOs\PaymentCustomerData;
use App\Domain\Billing\DTOs\PaymentCustomerResult;
use App\Domain\Billing\DTOs\PaymentResult;
use App\Domain\Billing\DTOs\PaymentWebhookData;
use App\Domain\Billing\DTOs\PixData;

interface PaymentProviderInterface
{
    /**
     * Identificador do provedor (ex: 'asaas').
     */
    public function providerName(): string;

    /**
     * Sincroniza ou cria o cliente no provedor.
     * Se $existingExternalId for informado, atualiza o cadastro no provedor se necessário.
     */
    public function syncCustomer(PaymentCustomerData $data, ?string $existingExternalId = null): PaymentCustomerResult;

    /**
     * Localiza cliente existente pelo CPF/CNPJ para evitar duplicação.
     */
    public function findCustomerByCpfCnpj(string $cpfCnpj): ?PaymentCustomerResult;

    /**
     * Localiza cliente existente pela referência externa estável.
     */
    public function findCustomerByExternalReference(string $externalReference): ?PaymentCustomerResult;

    /**
     * Cria cobrança no provedor (PIX ou Boleto).
     */
    public function createCharge(CreatePaymentData $data): PaymentResult;

    /**
     * Consulta cobrança existente no provedor pelo ID externo.
     */
    public function getCharge(string $providerExternalId): PaymentResult;

    /**
     * Consulta cobrança existente pela referência externa para reconciliação em caso de timeout.
     */
    public function findChargeByExternalReference(string $externalReference): ?PaymentResult;

    /**
     * Cancela uma cobrança no provedor.
     */
    public function cancelCharge(string $providerExternalId): bool;

    /**
     * Obtém dados do PIX (QR Code e Copia e Cola) para uma cobrança.
     */
    public function getPixData(string $providerExternalId): PixData;

    /**
     * Obtém linha digitável e dados do Boleto para uma cobrança.
     */
    public function getBoletoData(string $providerExternalId): BoletoData;

    /**
     * Previsto na interface para o futuro (reembolso administrativo).
     */
    public function refundCharge(string $providerExternalId, ?int $amountCents = null): bool;

    /**
     * Realiza o parsing estruturado do payload de webhook do provedor para o DTO PaymentWebhookData.
     */
    public function parseWebhook(array $payload, ?\Carbon\CarbonInterface $fallbackReceivedAt = null): PaymentWebhookData;
}

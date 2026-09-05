<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\PaymentProviderInterface;
use App\Domain\Billing\DTOs\PaymentCustomerData;
use App\Domain\Billing\Exceptions\PaymentCustomerDataIncompleteException;
use App\Domain\Billing\Models\OrganizationPaymentCustomer;
use App\Domain\Organizations\Models\Organization;

class PaymentCustomerService
{
    public function __construct(
        private readonly PaymentProviderInterface $paymentProvider,
    ) {}

    /**
     * Extrai e valida os dados cadastrais da organização a partir de CompanyVerification (se aprovada)
     * ou dos dados da Organization e do proprietário.
     *
     * @throws PaymentCustomerDataIncompleteException
     */
    public function resolveCustomerData(Organization $organization): PaymentCustomerData
    {
        $verification = $organization->verification;
        $isVerified = $verification && $verification->isVerified();

        $legalName = $isVerified ? $verification->company_name : $organization->name;
        $tradeName = $isVerified ? ($verification->trade_name ?: $verification->company_name) : $organization->name;
        $cnpj      = $isVerified ? $verification->cnpj : $organization->cnpj;

        // E-mail financeiro: da verificação ou e-mail do proprietário da organização
        $email = $isVerified ? $verification->corporate_email : null;
        if (!$email) {
            $owner = $organization->users()->wherePivot('is_owner', true)->first();
            $email = $owner?->email;
        }

        $phone = $isVerified ? $verification->phone : null;
        $address = $organization->address;

        $missing = [];
        if (empty($legalName)) {
            $missing[] = 'legal_name';
        }
        if (empty($cnpj)) {
            $missing[] = 'cnpj';
        }

        if (!empty($missing)) {
            throw new PaymentCustomerDataIncompleteException(
                message: "Dados cadastrais incompletos para emissão financeira: " . implode(', ', $missing),
                missingFields: $missing
            );
        }

        return new PaymentCustomerData(
            name: $legalName,
            cpfCnpj: $cnpj,
            email: $email,
            phone: $phone,
            address: $address,
            externalReference: "nodal:org:{$organization->uuid}",
        );
    }

    /**
     * Obtém ou sincroniza o cliente da organização no provedor de pagamento.
     * Se já existir mapping local, sincroniza/atualiza no provedor caso tenha mudado.
     */
    public function getOrCreateCustomer(Organization $organization): OrganizationPaymentCustomer
    {
        $customerData = $this->resolveCustomerData($organization);

        $existing = OrganizationPaymentCustomer::where('organization_id', $organization->id)
            ->where('provider', $this->paymentProvider->providerName())
            ->first();

        if ($existing) {
            // Sincroniza eventuais atualizações cadastrais no Asaas
            $this->paymentProvider->syncCustomer($customerData, $existing->external_customer_id);

            $existing->update([
                'metadata_json' => array_merge($existing->metadata_json ?? [], [
                    'last_synced_at' => now()->toIsoString(),
                    'legal_name'     => $customerData->name,
                    'cpfCnpj'        => $customerData->cpfCnpj,
                    'email'          => $customerData->email,
                ]),
            ]);

            return $existing;
        }

        // Cria ou busca idempotente no Asaas
        $result = $this->paymentProvider->syncCustomer($customerData);

        return OrganizationPaymentCustomer::create([
            'organization_id'      => $organization->id,
            'provider'             => $this->paymentProvider->providerName(),
            'external_customer_id' => $result->externalCustomerId,
            'metadata_json'        => [
                'created_at' => now()->toIsoString(),
                'legal_name' => $customerData->name,
                'cpfCnpj'    => $customerData->cpfCnpj,
                'email'      => $customerData->email,
            ],
        ]);
    }

    /**
     * Constrói o snapshot cadastral para congelamento histórico na BillingInvoice.
     */
    public function buildCustomerSnapshot(Organization $organization): array
    {
        try {
            $data = $this->resolveCustomerData($organization);
            $verification = $organization->verification;
            $tradeName = ($verification && $verification->isVerified() && $verification->trade_name)
                ? $verification->trade_name
                : $organization->name;

            return [
                'legal_name'           => $data->name,
                'trade_name'           => $tradeName,
                'tax_id'               => $data->cpfCnpj,
                'billing_email'        => $data->email,
                'phone'                => $data->phone,
                'fiscal_data_complete' => true,
            ];
        } catch (PaymentCustomerDataIncompleteException $e) {
            return [
                'legal_name'           => $organization->name,
                'trade_name'           => $organization->name,
                'tax_id'               => $organization->cnpj,
                'billing_email'        => $organization->users()->wherePivot('is_owner', true)->first()?->email,
                'phone'                => null,
                'fiscal_data_complete' => false,
                'missing_fields'       => $e->missingFields,
            ];
        }
    }
}

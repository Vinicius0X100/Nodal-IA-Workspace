<?php

namespace Tests\Feature\Domain\Billing;

use App\Domain\Billing\Contracts\PaymentProviderInterface;
use App\Domain\Billing\DTOs\CreatePaymentData;
use App\Domain\Billing\DTOs\PaymentCustomerData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentProviderException;
use App\Domain\Billing\Providers\AsaasPaymentProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private PaymentProviderInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.api_url'       => 'https://api-sandbox.asaas.com/v3',
            'services.asaas.api_key'       => 'test_secret_key',
            'services.asaas.webhook_token' => 'test_webhook_token',
            'services.asaas.user_agent'    => 'Nodal-Test/1.0',
        ]);

        $this->provider = app(AsaasPaymentProvider::class);
    }

    public function test_sync_customer_creates_new_customer(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers?externalReference=*' => Http::response(['data' => []], 200),
            'https://api-sandbox.asaas.com/v3/customers?cpfCnpj=*'           => Http::response(['data' => []], 200),
            'https://api-sandbox.asaas.com/v3/customers'                     => Http::response([
                'id'                => 'cus_000001',
                'name'              => 'Empresa Teste LTDA',
                'cpfCnpj'           => '12345678000195',
                'email'             => 'financeiro@empresa.com',
                'externalReference' => 'nodal:org:test-uuid',
            ], 200),
        ]);

        $data = new PaymentCustomerData(
            name: 'Empresa Teste LTDA',
            cpfCnpj: '12.345.678/0001-95',
            email: 'financeiro@empresa.com',
            externalReference: 'nodal:org:test-uuid',
        );

        $result = $this->provider->syncCustomer($data);

        $this->assertSame('cus_000001', $result->externalCustomerId);
        $this->assertTrue($result->isNew);

        Http::assertSent(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/customers')) {
                return $request->hasHeader('access_token', 'test_secret_key')
                    && $request->hasHeader('User-Agent', 'Nodal-Test/1.0')
                    && ($request['cpfCnpj'] ?? null) === '12345678000195';
            }
            return true;
        });

    }

    public function test_sync_customer_reuses_existing_by_cpf_cnpj(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/customers?externalReference=*' => Http::response(['data' => []], 200),
            'https://api-sandbox.asaas.com/v3/customers?cpfCnpj=*'           => Http::response([
                'data' => [
                    ['id' => 'cus_existing_99', 'name' => 'Empresa Existente'],
                ]
            ], 200),
            'https://api-sandbox.asaas.com/v3/customers/cus_existing_99'     => Http::response(['id' => 'cus_existing_99'], 200),
        ]);

        $data = new PaymentCustomerData(
            name: 'Empresa Teste',
            cpfCnpj: '12.345.678/0001-95',
            externalReference: 'nodal:org:test-uuid',
        );

        $result = $this->provider->syncCustomer($data);

        $this->assertSame('cus_existing_99', $result->externalCustomerId);
        $this->assertFalse($result->isNew);
    }

    public function test_create_charge_for_pix(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'id'                => 'pay_pix_123',
                'customer'          => 'cus_000001',
                'value'             => 1990.00,
                'netValue'          => 1988.01,
                'billingType'       => 'PIX',
                'status'            => 'PENDING',
                'dueDate'           => '2026-09-10',
                'externalReference' => 'nodal:invoice:uuid-1:attempt_1',
            ], 200),
        ]);

        $createData = new CreatePaymentData(
            externalCustomerId: 'cus_000001',
            amountCents: 199000,
            dueDate: Carbon::parse('2026-09-10'),
            paymentMethod: PaymentMethod::PIX,
            description: 'Fatura Teste',
            externalReference: 'nodal:invoice:uuid-1:attempt_1',
            idempotencyKey: 'key-123',
        );

        $result = $this->provider->createCharge($createData);

        $this->assertSame('pay_pix_123', $result->providerExternalId);
        $this->assertSame(PaymentStatus::PENDING, $result->status);
        $this->assertSame(199000, $result->amountCents);
        $this->assertSame(198801, $result->netAmountCents);
        $this->assertSame(199, $result->feeCents);
        $this->assertSame(PaymentMethod::PIX, $result->paymentMethod);
    }

    public function test_get_pix_data(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments/pay_pix_123/pixQrCode' => Http::response([
                'encodedImage'   => 'base64_image_data',
                'payload'        => '00020126580014br.gov.bcb.pix...',
                'expirationDate' => '2026-09-10 23:59:59',
            ], 200),
        ]);

        $pixData = $this->provider->getPixData('pay_pix_123');

        $this->assertSame('base64_image_data', $pixData->qrCodeBase64);
        $this->assertSame('00020126580014br.gov.bcb.pix...', $pixData->copyPaste);
        $this->assertNotNull($pixData->expiresAt);
    }

    public function test_get_boleto_data(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments/pay_bol_123/identificationField' => Http::response([
                'identificationField' => '34191.79001 01043.510047 91020.150008 5 99990000019900',
                'barCode'             => '34195999900000199001790001043510049102015000',
            ], 200),
        ]);

        $boletoData = $this->provider->getBoletoData('pay_bol_123');

        $this->assertSame('34191.79001 01043.510047 91020.150008 5 99990000019900', $boletoData->identificationField);
        $this->assertSame('34195999900000199001790001043510049102015000', $boletoData->barcode);
    }

    public function test_cancel_charge(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments/pay_123' => Http::response(['deleted' => true], 200),
        ]);

        $cancelled = $this->provider->cancelCharge('pay_123');
        $this->assertTrue($cancelled);
    }

    public function test_handles_asaas_error_response(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'errors' => [
                    ['description' => 'Saldo insuficiente ou data inválida.'],
                ],
            ], 400),
        ]);

        $this->expectException(PaymentProviderException::class);
        $this->expectExceptionMessage('Saldo insuficiente ou data inválida.');

        $createData = new CreatePaymentData(
            externalCustomerId: 'cus_000001',
            amountCents: 1000,
            dueDate: Carbon::now(),
            paymentMethod: PaymentMethod::PIX,
            description: 'Teste',
            externalReference: 'ref-1',
            idempotencyKey: 'key-1',
        );

        $this->provider->createCharge($createData);
    }
}

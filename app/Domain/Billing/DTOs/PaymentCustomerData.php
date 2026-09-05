<?php

namespace App\Domain\Billing\DTOs;

readonly class PaymentCustomerData
{
    public function __construct(
        public string $name,
        public string $cpfCnpj,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $mobilePhone = null,
        public ?string $address = null,
        public ?string $addressNumber = null,
        public ?string $postalCode = null,
        public ?string $province = null,
        public ?string $externalReference = null,
    ) {}
}

<?php

namespace App\Domain\Billing\DTOs;

readonly class PaymentCustomerResult
{
    public function __construct(
        public string $externalCustomerId,
        public bool $isNew = false,
        public array $rawResponse = [],
    ) {}
}

<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Enums\PaymentMethod;
use Carbon\CarbonInterface;

readonly class CreatePaymentData
{
    public function __construct(
        public string $externalCustomerId,
        public int $amountCents,
        public CarbonInterface $dueDate,
        public PaymentMethod $paymentMethod,
        public string $description,
        public string $externalReference,
        public string $idempotencyKey,
    ) {}
}

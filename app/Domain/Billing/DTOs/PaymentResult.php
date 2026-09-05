<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use Carbon\CarbonInterface;

readonly class PaymentResult
{
    public function __construct(
        public string $providerExternalId,
        public PaymentStatus $status,
        public int $amountCents,
        public ?int $netAmountCents = null,
        public ?int $feeCents = null,
        public ?CarbonInterface $dueDate = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $bankSlipUrl = null,
        public ?string $invoiceUrl = null,
        public array $rawResponse = [],
    ) {}
}

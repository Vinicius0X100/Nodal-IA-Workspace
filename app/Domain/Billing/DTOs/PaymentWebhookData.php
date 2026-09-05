<?php

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Enums\PaymentStatus;
use Carbon\CarbonInterface;

readonly class PaymentWebhookData
{
    public function __construct(
        public string $eventId,
        public string $eventName,
        public ?string $providerExternalPaymentId,
        public ?PaymentStatus $status = null,
        public ?int $paidAmountCents = null,
        public ?CarbonInterface $paymentDate = null,
        public array $rawPayload = [],
    ) {}
}

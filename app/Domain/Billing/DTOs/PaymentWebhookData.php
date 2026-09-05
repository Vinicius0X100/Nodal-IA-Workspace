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
        public ?int $valueCents = null,
        public ?int $netValueCents = null,
        public ?int $feeCents = null,
        public ?CarbonInterface $eventOccurredAt = null,
        public ?string $rawPaymentDate = null,
        public ?string $rawConfirmedDate = null,
        public array $rawPayload = [],
    ) {}
}

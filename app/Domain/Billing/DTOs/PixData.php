<?php

namespace App\Domain\Billing\DTOs;

use Carbon\CarbonInterface;

readonly class PixData
{
    public function __construct(
        public string $copyPaste,
        public string $qrCodeBase64,
        public ?CarbonInterface $expiresAt = null,
    ) {}
}

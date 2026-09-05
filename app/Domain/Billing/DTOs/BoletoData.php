<?php

namespace App\Domain\Billing\DTOs;

readonly class BoletoData
{
    public function __construct(
        public ?string $barcode = null,
        public ?string $identificationField = null,
        public ?string $url = null,
    ) {}
}

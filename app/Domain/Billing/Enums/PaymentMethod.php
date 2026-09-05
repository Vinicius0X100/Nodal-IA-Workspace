<?php

namespace App\Domain\Billing\Enums;

enum PaymentMethod: string
{
    case PIX    = 'pix';
    case BOLETO = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::PIX    => 'PIX',
            self::BOLETO => 'Boleto Bancário',
        };
    }
}

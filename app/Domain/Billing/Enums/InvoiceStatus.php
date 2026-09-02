<?php

namespace App\Domain\Billing\Enums;

enum InvoiceStatus: string
{
    case DRAFT   = 'draft';
    case ISSUED  = 'issued';
    case PAID    = 'paid';
    case VOID    = 'void';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT  => 'Rascunho',
            self::ISSUED => 'Emitida',
            self::PAID   => 'Paga',
            self::VOID   => 'Cancelada',
        };
    }
}

<?php

namespace App\Domain\Billing\Enums;

enum PaymentStatus: string
{
    case PENDING      = 'pending';
    case PROCESSING   = 'processing';
    case PAID         = 'paid';
    case FAILED       = 'failed';
    case CANCELLED    = 'cancelled';
    case EXPIRED      = 'expired';
    case OVERDUE      = 'overdue';
    case REFUNDED     = 'refunded';
    case NEEDS_REVIEW = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::PENDING      => 'Pendente',
            self::PROCESSING   => 'Processando',
            self::PAID         => 'Pago',
            self::FAILED       => 'Falhou',
            self::CANCELLED    => 'Cancelado',
            self::EXPIRED      => 'Expirado',
            self::OVERDUE      => 'Vencido',
            self::REFUNDED     => 'Reembolsado',
            self::NEEDS_REVIEW => 'Requer Análise',
        };
    }

    /**
     * Determina se a cobrança ainda está em estado ativo/aguardando liquidação.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING, self::OVERDUE], true);
    }

    /**
     * Determina se uma nova tentativa de cobrança pode ser gerada.
     */
    public function allowsRetry(): bool
    {
        return in_array($this, [self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }
}

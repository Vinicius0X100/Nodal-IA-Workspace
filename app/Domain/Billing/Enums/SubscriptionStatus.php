<?php

namespace App\Domain\Billing\Enums;

enum SubscriptionStatus: string
{
    case TRIAL      = 'trial';
    case ACTIVE     = 'active';
    case PAST_DUE   = 'past_due';
    case SUSPENDED  = 'suspended';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TRIAL     => 'Trial',
            self::ACTIVE    => 'Ativo',
            self::PAST_DUE  => 'Em atraso',
            self::SUSPENDED => 'Suspenso',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::TRIAL, self::ACTIVE]);
    }
}

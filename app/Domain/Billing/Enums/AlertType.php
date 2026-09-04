<?php

namespace App\Domain\Billing\Enums;

enum AlertType: string
{
    case CREDIT_USAGE_70        = 'credit_usage_70';
    case CREDIT_USAGE_85        = 'credit_usage_85';
    case CREDIT_USAGE_95        = 'credit_usage_95';
    case CREDIT_USAGE_100       = 'credit_usage_100';
    case POSTPAID_STARTED       = 'postpaid_started';
    case POSTPAID_75            = 'postpaid_75';
    case POSTPAID_90            = 'postpaid_90';
    case POSTPAID_LIMIT_REACHED = 'postpaid_limit_reached';
    case INVOICE_ISSUED         = 'invoice_issued';
    case PAYMENT_DUE            = 'payment_due';

    /** Returns thresholds for credit usage alerts (global defaults) */
    public static function creditUsageThresholds(): array
    {
        return [70, 85, 95, 100];
    }

    /** Returns thresholds for postpaid limit alerts */
    public static function postpaidThresholds(): array
    {
        return [75, 90, 100];
    }

    public static function fromCreditThreshold(int $threshold): self
    {
        return match ($threshold) {
            70  => self::CREDIT_USAGE_70,
            85  => self::CREDIT_USAGE_85,
            95  => self::CREDIT_USAGE_95,
            100 => self::CREDIT_USAGE_100,
            default => throw new \InvalidArgumentException("Unknown credit threshold: {$threshold}"),
        };
    }

    public static function fromPostpaidThreshold(int $threshold): self
    {
        return match ($threshold) {
            75  => self::POSTPAID_75,
            90  => self::POSTPAID_90,
            100 => self::POSTPAID_LIMIT_REACHED,
            default => throw new \InvalidArgumentException("Unknown postpaid threshold: {$threshold}"),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_USAGE_70        => '70% dos créditos utilizados',
            self::CREDIT_USAGE_85        => '85% dos créditos utilizados',
            self::CREDIT_USAGE_95        => '95% dos créditos utilizados',
            self::CREDIT_USAGE_100       => '100% dos créditos utilizados',
            self::POSTPAID_STARTED       => 'Uso adicional iniciado',
            self::POSTPAID_75            => '75% do limite adicional atingido',
            self::POSTPAID_90            => '90% do limite adicional atingido',
            self::POSTPAID_LIMIT_REACHED => 'Limite adicional atingido',
            self::INVOICE_ISSUED         => 'Nova fatura emitida',
            self::PAYMENT_DUE            => 'Pagamento pendente',
        };
    }
}

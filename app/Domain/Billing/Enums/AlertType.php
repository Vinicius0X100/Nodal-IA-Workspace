<?php

namespace App\Domain\Billing\Enums;

enum AlertType: string
{
    case CREDIT_USAGE_70     = 'credit_usage_70';
    case CREDIT_USAGE_85     = 'credit_usage_85';
    case CREDIT_USAGE_95     = 'credit_usage_95';
    case CREDIT_USAGE_100    = 'credit_usage_100';
    case POSTPAID_LIMIT_50   = 'postpaid_limit_50';
    case POSTPAID_LIMIT_80   = 'postpaid_limit_80';
    case POSTPAID_LIMIT_100  = 'postpaid_limit_100';
    case INVOICE_ISSUED      = 'invoice_issued';
    case PAYMENT_DUE         = 'payment_due';

    /** Returns thresholds for credit usage alerts (global defaults) */
    public static function creditUsageThresholds(): array
    {
        return [70, 85, 95, 100];
    }

    public static function fromCreditThreshold(int $threshold): self
    {
        return match ($threshold) {
            70  => self::CREDIT_USAGE_70,
            85  => self::CREDIT_USAGE_85,
            95  => self::CREDIT_USAGE_95,
            100 => self::CREDIT_USAGE_100,
            default => throw new \InvalidArgumentException("Unknown threshold: {$threshold}"),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_USAGE_70   => '70% dos créditos utilizados',
            self::CREDIT_USAGE_85   => '85% dos créditos utilizados',
            self::CREDIT_USAGE_95   => '95% dos créditos utilizados',
            self::CREDIT_USAGE_100  => '100% dos créditos utilizados',
            self::POSTPAID_LIMIT_50 => '50% do limite pós-pago atingido',
            self::POSTPAID_LIMIT_80 => '80% do limite pós-pago atingido',
            self::POSTPAID_LIMIT_100 => 'Limite pós-pago atingido',
            self::INVOICE_ISSUED    => 'Nova fatura emitida',
            self::PAYMENT_DUE       => 'Pagamento pendente',
        };
    }
}

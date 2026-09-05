<?php

namespace App\Domain\Billing\Services\Asaas;

use App\Domain\Billing\Enums\PaymentStatus;

class AsaasPaymentStatusMapper
{
    /**
     * Mapeia o campo "event" enviado no Webhook do Asaas para o status interno tipado.
     */
    public static function fromEvent(string $event): ?PaymentStatus
    {
        return match (strtoupper(trim($event))) {
            'PAYMENT_CREATED', 'PAYMENT_RESTORED' => PaymentStatus::PENDING,
            'PAYMENT_AWAITING_RISK_ANALYSIS'      => PaymentStatus::PROCESSING,
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => PaymentStatus::PAID,
            'PAYMENT_OVERDUE'                     => PaymentStatus::OVERDUE,
            'PAYMENT_DELETED'                     => PaymentStatus::CANCELLED,
            'PAYMENT_REFUNDED'                    => PaymentStatus::REFUNDED,
            'PAYMENT_RECEIVED_IN_CASH_UNDONE',
            'PAYMENT_CHARGEBACK_REQUESTED',
            'PAYMENT_CHARGEBACK_DISPUTE',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => PaymentStatus::NEEDS_REVIEW,
            default                               => null,
        };
    }

    /**
     * Mapeia o campo "status" retornado nas consultas diretas de cobrança do Asaas.
     */
    public static function fromChargeStatus(string $status): ?PaymentStatus
    {
        return match (strtoupper(trim($status))) {
            'PENDING'                 => PaymentStatus::PENDING,
            'AWAITING_RISK_ANALYSIS'  => PaymentStatus::PROCESSING,
            'CONFIRMED', 'RECEIVED',
            'RECEIVED_IN_CASH',
            'DUNNING_RECEIVED'        => PaymentStatus::PAID,
            'OVERDUE', 'DUNNING_REQUESTED' => PaymentStatus::OVERDUE,
            'REFUNDED', 'REFUND_REQUESTED' => PaymentStatus::REFUNDED,
            'CHARGEBACK_REQUESTED',
            'CHARGEBACK_DISPUTE',
            'AWAITING_CHARGEBACK_REVERSAL' => PaymentStatus::NEEDS_REVIEW,
            default                   => null,
        };
    }
}

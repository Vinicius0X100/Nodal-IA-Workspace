<?php

namespace App\Domain\Billing\Enums;

enum BillingCategory: string
{
    case USER_REQUEST       = 'user_request';
    case AGENT_REASONING    = 'agent_reasoning';
    case DOCUMENT_ANALYSIS  = 'document_analysis';
    case TOOL_PROCESSING    = 'tool_processing';
    case INTERNAL_RETRY     = 'internal_retry';
    case SYSTEM_OPERATION   = 'system_operation';
    case ADJUSTMENT         = 'adjustment';

    /**
     * Whether this category counts for the org's billable quota.
     * internal_retry e system_operation registram custo real mas não cobram do cliente.
     */
    public function isBillableByDefault(): bool
    {
        return match ($this) {
            self::INTERNAL_RETRY,
            self::SYSTEM_OPERATION,
            self::ADJUSTMENT    => false,
            default             => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::USER_REQUEST      => 'Solicitação do usuário',
            self::AGENT_REASONING   => 'Raciocínio do agente',
            self::DOCUMENT_ANALYSIS => 'Análise de documento',
            self::TOOL_PROCESSING   => 'Processamento de ferramenta',
            self::INTERNAL_RETRY    => 'Retry interno',
            self::SYSTEM_OPERATION  => 'Operação do sistema',
            self::ADJUSTMENT        => 'Ajuste',
        };
    }
}

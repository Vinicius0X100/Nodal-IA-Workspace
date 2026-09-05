<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auto Issue Invoices
    |--------------------------------------------------------------------------
    |
    | Determina se as faturas fechadas pelo BillingPeriodClosingService devem
    | ser automaticamente emitidas no provedor de pagamento (Asaas).
    | Mantido como false inicialmente até validação completa em Sandbox.
    |
    */
    'auto_issue' => (bool) env('BILLING_AUTO_ISSUE', false),

    /*
    |--------------------------------------------------------------------------
    | Provedor de Pagamento Padrão
    |--------------------------------------------------------------------------
    |
    */
    'default_provider' => env('PAYMENT_PROVIDER', 'asaas'),

    /*
    |--------------------------------------------------------------------------
    | Descrição Fiscal do Serviço
    |--------------------------------------------------------------------------
    |
    | Descrição comercial/fiscal apresentada no faturamento e disponibilizada
    | para o Integer e contabilidade para emissão manual da NFS-e.
    | Congelada no snapshot histórico de cada fatura.
    |
    */
    'fiscal_service_description' => env('BILLING_FISCAL_SERVICE_DESCRIPTION', 'Licenciamento de software SaaS'),

    /*
    |--------------------------------------------------------------------------
    | Prazo de Vencimento Padrão (em dias)
    |--------------------------------------------------------------------------
    |
    | Quantidade de dias corridos a partir da emissão para o vencimento da fatura.
    |
    */
    'due_days' => (int) env('BILLING_DUE_DAYS', 5),

];

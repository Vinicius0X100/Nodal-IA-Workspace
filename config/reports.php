<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Threshold de custo para execução assíncrona (em "pontos")
    |--------------------------------------------------------------------------
    | Consultas com custo estimado >= este valor são automaticamente roteadas
    | para processamento assíncrono via Job/Queue.
    | O frontend e o AI Agent nunca decidem — o backend é autoridade.
    */
    'async_threshold' => env('REPORTS_ASYNC_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Forçar processamento assíncrono (Apenas para Desenvolvimento/Testes)
    |--------------------------------------------------------------------------
    | Útil para forçar o AI Agent a sempre usar a fila de relatórios para testar
    | o comportamento do worker localmente. É ignorado em produção.
    */
    'force_async' => env('REPORTS_FORCE_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Fatores de custo estimado (InsightsCostEstimator)
    |--------------------------------------------------------------------------
    | Cada fator adiciona pontos ao custo total estimado da consulta.
    | Configuráveis sem alterar código.
    */
    'cost_factors' => [
        'level_ad'          => (int) env('REPORTS_COST_LEVEL_AD', 50),
        'days_over_14'      => (int) env('REPORTS_COST_DAYS_14', 30),
        'days_over_30'      => (int) env('REPORTS_COST_DAYS_30', 60),
        'resource_extra'    => (int) env('REPORTS_COST_RESOURCE_EXTRA', 20), // por resource além do primeiro
        'campaigns_over_20' => (int) env('REPORTS_COST_CAMPAIGNS_20', 15),
        'adsets_over_50'    => (int) env('REPORTS_COST_ADSETS_50', 20),
        'ads_over_100'      => (int) env('REPORTS_COST_ADS_100', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout de execução síncrona (segundos)
    |--------------------------------------------------------------------------
    | Se uma consulta marcada inicialmente como síncrona exceder esse tempo,
    | é promovida para assíncrona (partial fallback).
    */
    'sync_timeout_seconds' => (int) env('REPORTS_SYNC_TIMEOUT_SECONDS', 25),

    /*
    |--------------------------------------------------------------------------
    | Storage de resultados grandes
    |--------------------------------------------------------------------------
    | resultado_pequeno → banco (coluna result)
    | resultado_grande  → Storage no disco configurado
    | Disk configurável: 'private' (local) ou 's3' (produção)
    */
    'result_size_threshold_kb' => (int) env('REPORTS_RESULT_SIZE_KB', 512),
    'result_disk'              => env('REPORTS_RESULT_DISK', 'private'),
    'result_ttl_hours'         => (int) env('REPORTS_RESULT_TTL_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Retenção e cleanup de reports
    |--------------------------------------------------------------------------
    */
    'retention_days'             => (int) env('REPORTS_RETENTION_DAYS', 30),
    'cleanup_chunk_size'         => (int) env('REPORTS_CLEANUP_CHUNK', 100),

    /*
    |--------------------------------------------------------------------------
    | Paginação e concorrência
    |--------------------------------------------------------------------------
    */
    'max_pages_per_job'         => (int) env('REPORTS_MAX_PAGES', 500),
    'max_concurrent_per_org'    => (int) env('REPORTS_MAX_CONCURRENT_PER_ORG', 5),

    /*
    |--------------------------------------------------------------------------
    | Deduplicação (idempotência)
    |--------------------------------------------------------------------------
    | Janela de tempo em que um report 'completed' pode ser reutilizado
    | para a mesma consulta (idempotency window).
    */
    'idempotency_completed_ttl_minutes' => (int) env('REPORTS_IDEMPOTENCY_TTL_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Versão da query (para invalidar caches ao mudar lógica de normalização)
    |--------------------------------------------------------------------------
    */
    'query_version' => env('REPORTS_QUERY_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Polling: sugestão de retry_after_seconds por status
    |--------------------------------------------------------------------------
    */
    'polling_interval' => [
        'queued'  => 5,
        'running' => 2,
        'partial' => 30,
    ],
];

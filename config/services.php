<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'base_url'    => env('N8N_BASE_URL'),
        'api_key'     => env('N8N_API_KEY'),
        'ai_models'   => [
            'Google Gemini Chat Model'       => 'gemini-3.5-flash',
            'Google Gemini Chat Model Retry' => 'gemini-3.5-flash',
        ],
    ],

    'ai_gateway' => [
        'token' => env('AI_GATEWAY_TOKEN'),
    ],

    'meta' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
    ],

    // Driver usado pelo Socialite sob o capô
    'facebook' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
    ],

    'google_workspace' => [
        'client_id' => env('GOOGLE_WORKSPACE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_WORKSPACE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_WORKSPACE_REDIRECT_URI'),
        'service_account_client_id' => env('GOOGLE_WORKSPACE_SERVICE_ACCOUNT_CLIENT_ID'),
        'service_account_json' => env('GOOGLE_WORKSPACE_SERVICE_ACCOUNT_JSON'),
    ],

    'asaas' => [
        'environment'   => env('ASAAS_ENVIRONMENT', 'sandbox'),
        'api_url'       => rtrim(env('ASAAS_API_URL', 'https://api-sandbox.asaas.com/v3'), '/'),
        'api_key'       => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'user_agent'    => env('ASAAS_USER_AGENT', 'Nodal-Billing/1.0'),
        'timeout'       => (int) env('ASAAS_TIMEOUT', 15),
        'timezone'      => env('ASAAS_TIMEZONE', 'America/Sao_Paulo'),
    ],

    'system' => [
        'api_key'         => env('SYSTEM_API_KEY'),
        'integer_api_key' => env('INTEGER_SYSTEM_API_KEY'),
    ],

];


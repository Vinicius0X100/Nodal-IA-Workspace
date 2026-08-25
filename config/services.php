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

];

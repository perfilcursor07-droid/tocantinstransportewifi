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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    /*
    |--------------------------------------------------------------------------
    | Ntfy.sh - Push Notifications Gratuitas
    |--------------------------------------------------------------------------
    | Serviço gratuito de push notifications para celular.
    | Instale o app "ntfy" no celular e inscreva-se no tópico configurado.
    | https://ntfy.sh
    */
    'ntfy' => [
        'enabled' => env('NTFY_ENABLED', false),
        'server_url' => env('NTFY_SERVER_URL', 'https://ntfy.sh'),
        'topic' => env('NTFY_TOPIC', ''),
        'topic_starlink' => env('NTFY_TOPIC_STARLINK', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Together.ai — IA que responde no chat antes de escalar para humano
    |--------------------------------------------------------------------------
    */
    'together' => [
        'enabled' => env('CHAT_AI_ENABLED', false),
        'api_key' => env('TOGETHER_API_KEY'),
        'api_url' => env('TOGETHER_API_URL', 'https://api.together.xyz/v1/chat/completions'),
        'model' => env('CHAT_AI_MODEL', 'deepseek-ai/DeepSeek-V3'),
        'timeout' => env('CHAT_AI_TIMEOUT', 15),
        'max_turns' => env('CHAT_AI_MAX_TURNS', 6),
        'verify_ssl' => env('CHAT_AI_VERIFY_SSL', true),
    ],

];

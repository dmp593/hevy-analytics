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

    'hevy' => [
        'base_url' => env('HEVY_BASE_URL', 'https://api.hevyapp.com'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        // `deepseek-chat` was retired on 24 July 2026 and now remaps to
        // V4-Flash. Pin the model explicitly rather than relying on an alias.
        'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
        'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 2000),
    ],

    /*
     * Hard caps on AI generation. These bound third-party spend: without them,
     * unlimited accounts each able to force an uncached call have nothing
     * between them and the operator's API balance but a per-minute throttle.
     * Set the global ceiling to 0 to disable the circuit breaker.
     */
    'ai' => [
        'monthly_limit_per_user' => (int) env('AI_MONTHLY_LIMIT_PER_USER', 30),
        'monthly_limit_global' => (int) env('AI_MONTHLY_LIMIT_GLOBAL', 5000),
    ],

    'fitnessvolt' => [
        'base_url' => env('FITNESSVOLT_BASE_URL', 'https://fitnessvolt.com/wp-json/rpe-training/v1'),
        'enabled' => env('FITNESSVOLT_ENABLED', true),
    ],

    'openpowerlifting' => [
        'csv_url' => env('OPL_CSV_URL', 'https://openpowerlifting.gitlab.io/opl-csv/files/openpowerlifting-latest.zip'),
    ],

];

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

        // On login, queue an incremental sync if the last one is older than
        // this. 0 disables login syncs entirely.
        'auto_sync_hours' => (int) env('HEVY_AUTO_SYNC_HOURS', 6),
    ],

    /*
     * The operator's own AI provider — the "included with your subscription"
     * path. Athletes may instead supply their own key in Settings, in which case
     * this is not used and the limits below do not apply to them.
     *
     * The limits bound third-party spend: without them, accounts each able to
     * force an uncached call have nothing between them and the operator's API
     * balance but a per-minute throttle. Set the global ceiling to 0 to disable
     * the circuit breaker.
     */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'deepseek'),
        'key' => env('AI_API_KEY', env('DEEPSEEK_API_KEY')),
        'model' => env('AI_MODEL', env('DEEPSEEK_MODEL', 'deepseek-v4-flash')),
        // Empty falls back to the provider's default endpoint in ProviderRegistry.
        'base_url' => env('AI_BASE_URL', env('DEEPSEEK_BASE_URL')),

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

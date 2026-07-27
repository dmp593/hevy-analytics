<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locally hosted providers
    |--------------------------------------------------------------------------
    | Whether a user may point the app at a loopback address (Ollama, LM Studio,
    | llama.cpp and similar).
    |
    | OFF by default, and it must stay off for any deployment serving people
    | other than the operator: the app makes an authenticated outbound request to
    | whatever base URL a user supplies, so allowing loopback lets any account
    | reach services bound to localhost on the server. It is safe to enable when
    | you are the only user and the app runs on your own machine.
    |
    | Enabling this never opens the private network or link-local range — the
    | cloud metadata endpoint stays blocked either way. See App\Services\AI\UrlGuard.
    */
    'allow_local_providers' => env('AI_ALLOW_LOCAL_PROVIDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Request limits
    |--------------------------------------------------------------------------
    | A generated analysis is a single non-streaming call. The timeout is
    | generous because reasoning models are slow, and the response cap bounds
    | what one call can cost.
    */
    'timeout' => (int) env('AI_TIMEOUT', 120),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),

];

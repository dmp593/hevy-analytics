<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The plan
    |--------------------------------------------------------------------------
    | One paid tier, deliberately. A second tier would have to be built out of
    | the same analysis — the only lever left is rationing something the athlete
    | can already see, and charging twice for one product is how a small app
    | annoys the people paying for it.
    |
    | `price_id` is the Paddle price identifier (pri_...), not the product.
    */
    'price_id' => env('PADDLE_PRICE_ID'),
    'display_price' => env('BILLING_DISPLAY_PRICE', '€8'),

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    | Card-free: the trial is granted locally at registration and Paddle never
    | sees it. Asking for a card before someone has synced a single workout is
    | asking them to decide before they have anything to decide with.
    */
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | What each state gets
    |--------------------------------------------------------------------------
    | The wall is HISTORY DEPTH, and that is a deliberate choice. Every number
    | worth paying for here compounds with history — a trend needs months, the
    | adaptive maintenance measurement needs four weeks of logging, a projection
    | needs a year. Capping history leaves the free tier genuinely useful for
    | someone starting out while making the paid tier obviously better for
    | someone who has been training a while, without hiding anything or
    | pretending a metric does not exist.
    |
    | `history_days` of null means unlimited.
    */
    'entitlements' => [

        'free' => [
            'history_days' => 30,
            'ai_analyses_per_month' => 0,
            'progress_photos' => 10,
        ],

        // The trial shows the whole product. A trial that hides the thing you
        // are trialling is not a trial.
        'trial' => [
            'history_days' => null,
            'ai_analyses_per_month' => 5,
            'progress_photos' => 100,
        ],

        'subscribed' => [
            'history_days' => null,
            'ai_analyses_per_month' => (int) env('AI_MONTHLY_LIMIT_PER_USER', 30),
            'progress_photos' => 500,
        ],
    ],

];

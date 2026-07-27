<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Paddle\Cashier;

/**
 * Reports whether billing is configured, and what is missing.
 *
 * Exists because the failure modes here are quiet. A missing price id shows as
 * "subscriptions are not available"; a missing webhook secret shows as nothing
 * at all until someone forges an event. Rather than leave the operator to work
 * out which of six values they forgot, this says so.
 */
class CheckBillingCommand extends Command
{
    protected $signature = 'app:billing-check {--offline : Skip the live call to Paddle}';

    protected $description = 'Check the Paddle billing configuration and report what is missing';

    public function handle(): int
    {
        $sandbox = (bool) config('cashier.sandbox');

        $this->newLine();
        $this->line('  Mode: <options=bold>'.($sandbox ? 'SANDBOX' : 'LIVE').'</>');
        $this->line('  Webhook URL: <options=bold>'.url(config('cashier.path', 'paddle').'/webhook').'</>');
        $this->newLine();

        $checks = [
            ['PADDLE_SELLER_ID', config('cashier.seller_id'), true, 'Paddle > Developer tools > Authentication'],
            ['PADDLE_CLIENT_SIDE_TOKEN', config('cashier.client_side_token'), true, 'Paddle > Developer tools > Authentication > Client-side tokens'],
            ['PADDLE_API_KEY', config('cashier.api_key'), true, 'Paddle > Developer tools > Authentication > API keys'],
            ['PADDLE_WEBHOOK_SECRET', config('cashier.webhook_secret'), true, 'Paddle > Developer tools > Notifications > your destination'],
            ['PADDLE_PRICE_ID', config('billing.price_id'), true, 'Paddle > Catalog > Products > your price (starts pri_)'],
            ['CASHIER_CURRENCY', config('cashier.currency'), false, 'Defaults to USD — set to EUR if you price in euros'],
        ];

        $missing = 0;

        foreach ($checks as [$key, $value, $required, $where]) {
            if (filled($value)) {
                // Secrets are confirmed as present, never echoed — this output
                // ends up pasted into chat threads and issue reports.
                $shown = $required ? '' : ' <fg=gray>('.$value.')</>';
                $this->line("  <fg=green>✓</> {$key}{$shown}");

                continue;
            }

            $missing += $required ? 1 : 0;
            $this->line('  <fg='.($required ? 'red' : 'yellow').'>'.($required ? '✗' : '!')."</> {$key} — {$where}");
        }

        $this->newLine();

        // Currency is not "missing" so much as silently wrong: Cashier formats
        // every amount it shows with this, so a euro price renders with a dollar
        // sign until it is set.
        if (config('cashier.currency') === 'USD' && str_contains((string) config('billing.display_price'), '€')) {
            $this->warn('  CASHIER_CURRENCY is USD but your displayed price is in euros.');
            $this->line('  Paddle amounts will render with a $ sign. Set CASHIER_CURRENCY=EUR.');
            $this->newLine();
        }

        if ($missing > 0) {
            $this->error("  Billing is not ready: {$missing} required value(s) missing.");
            $this->line('  Until then the app runs on trial/free tiers and the billing page says so.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Billing is configured.');
        $this->newLine();

        if (! $this->option('offline') && ! $this->verifyAgainstPaddle()) {
            return self::FAILURE;
        }

        $this->line('  In Paddle > Developer tools > Notifications, the destination must be');
        $this->line('  subscribed to exactly these events, or Cashier will not see them:');
        $this->newLine();

        foreach ([
            'customer.updated',
            'transaction.completed',
            'transaction.updated',
            'subscription.created',
            'subscription.updated',
            'subscription.paused',
            'subscription.canceled',
        ] as $event) {
            $this->line("    · {$event}");
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Actually call Paddle, rather than only checking the values look right.
     *
     * Fetching the configured price proves three things at once that no amount
     * of string validation can: the API key is accepted, the price exists, and
     * it exists in the environment the key belongs to. Sandbox and live keys
     * look identical, and a live price id with a sandbox key fails at checkout
     * with an error the athlete sees rather than the operator.
     */
    private function verifyAgainstPaddle(): bool
    {
        $this->line('  Asking Paddle whether these actually work…');
        $this->newLine();

        try {
            $response = Cashier::api('GET', 'prices/'.config('billing.price_id'));
        } catch (\Throwable $e) {
            $this->error('  Could not verify with Paddle.');
            $this->line('  '.$e->getMessage());
            $this->newLine();
            $this->line('  Most likely: the API key belongs to the other environment');
            $this->line('  (sandbox key with PADDLE_SANDBOX=false, or the reverse),');
            $this->line('  or the price id is from the other environment.');
            $this->newLine();

            return false;
        }

        $price = $response['data'] ?? null;

        if (! $price) {
            $this->error('  Paddle accepted the key but returned no price for that id.');
            $this->newLine();

            return false;
        }

        $amount = $price['unit_price']['amount'] ?? null;
        $currency = $price['unit_price']['currency_code'] ?? '?';
        $interval = $price['billing_cycle']['interval'] ?? null;

        $this->line('  <fg=green>✓</> API key accepted');
        $this->line('  <fg=green>✓</> Price found: <options=bold>'.($price['description'] ?? $price['id']).'</>');

        if ($amount !== null) {
            // Paddle returns minor units, so 800 is 8.00.
            $this->line('    '.number_format($amount / 100, 2).' '.$currency
                .($interval ? ' per '.$interval : ''));
        }

        // A one-off price would take a payment and never create a subscription,
        // and the app would sit there waiting for a subscription.created that
        // is never coming.
        if ($interval === null) {
            $this->newLine();
            $this->error('  That price is one-time, not recurring.');
            $this->line('  A subscription needs a recurring price — create one and use its id.');
            $this->newLine();

            return false;
        }

        if ($currency !== config('cashier.currency')) {
            $this->newLine();
            $this->warn("  Price is in {$currency} but CASHIER_CURRENCY is ".config('cashier.currency').'.');
            $this->line('  Amounts shown in the app will carry the wrong symbol.');
        }

        $this->newLine();

        return true;
    }
}

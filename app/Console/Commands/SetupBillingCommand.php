<?php

namespace App\Console\Commands;

use App\Support\EnvFile;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Walks the operator through the Paddle credentials and writes them to .env.
 *
 * The values themselves can only come from Paddle's dashboard — there is no API
 * that hands them out, and the account has to be created by a real person with
 * a real tax number. What this removes is everything after that: knowing which
 * of six env vars each value belongs in, that sandbox and live credentials are
 * not interchangeable, and that the currency default will quietly render euro
 * prices with a dollar sign.
 */
class SetupBillingCommand extends Command
{
    protected $signature = 'app:billing-setup';

    protected $description = 'Set up Paddle billing step by step';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (! (new EnvFile($envPath))->exists()) {
            $this->error('No .env file found. Run `cp .env.example .env && php artisan key:generate` first.');

            return self::FAILURE;
        }

        note(
            "This asks for five values from your Paddle dashboard and writes them to .env.\n".
            "Nothing is sent anywhere — the values only go into that file.\n\n".
            'You need a Paddle account already. Signing up needs your tax number and '.
            'bank details, so it has to be done by you at paddle.com.'
        );

        $sandbox = select(
            label: 'Which Paddle environment are these credentials from?',
            options: [
                'sandbox' => 'Sandbox — for testing, no real money (start here)',
                'live' => 'Live — real payments',
            ],
            default: 'sandbox',
            hint: 'Sandbox and live are separate accounts. Credentials never work across both.',
        );

        $base = $sandbox === 'sandbox' ? 'https://sandbox-vendors.paddle.com' : 'https://vendors.paddle.com';

        note("Open {$base} and sign in. Everything below is under Developer tools, except the price.");

        $values = [
            'PADDLE_SANDBOX' => $sandbox === 'sandbox' ? 'true' : 'false',
        ];

        $values['PADDLE_SELLER_ID'] = text(
            label: 'Seller ID',
            placeholder: '123456',
            required: true,
            hint: 'Developer tools → Authentication. It is a plain number.',
            validate: fn (string $v) => preg_match('/^\d+$/', trim($v))
                ? null
                : 'That should be digits only — it is the number shown as your seller ID.',
        );

        $values['PADDLE_CLIENT_SIDE_TOKEN'] = text(
            label: 'Client-side token',
            placeholder: $sandbox === 'sandbox' ? 'test_...' : 'live_...',
            required: true,
            hint: 'Developer tools → Authentication → Client-side tokens → Generate. This one is public.',
            validate: fn (string $v) => str_starts_with(trim($v), 'test_') || str_starts_with(trim($v), 'live_')
                ? null
                : 'A client-side token starts with test_ or live_. You may have copied the API key instead.',
        );

        // password(), not text(): this is a secret and should not sit in the
        // terminal scrollback or the shell history of whoever runs this.
        $values['PADDLE_API_KEY'] = password(
            label: 'API key (input hidden)',
            required: true,
            hint: 'Developer tools → Authentication → API keys → Generate. Paddle shows it once.',
            validate: fn (string $v) => strlen(trim($v)) >= 20
                ? null
                : 'That looks too short for an API key.',
        );

        note(
            "Now the webhook. In Paddle: Developer tools → Notifications → New destination.\n\n".
            'URL:  '.url(config('cashier.path', 'paddle').'/webhook')."\n\n".
            "Subscribe it to exactly these events:\n".
            "  customer.updated\n  transaction.completed\n  transaction.updated\n".
            "  subscription.created\n  subscription.updated\n  subscription.paused\n  subscription.canceled\n\n".
            'Save it, then copy the secret key it shows you.'
        );

        $values['PADDLE_WEBHOOK_SECRET'] = password(
            label: 'Webhook secret (input hidden)',
            required: true,
            hint: 'Without this, anyone who knows the webhook URL could grant themselves a subscription.',
            validate: fn (string $v) => strlen(trim($v)) >= 20
                ? null
                : 'That looks too short for a webhook secret.',
        );

        note(
            "Last one: the price. In Paddle: Catalog → Products → New product.\n".
            "Name it whatever you like, then add a RECURRING price — monthly, in EUR.\n".
            'Open that price and copy its ID.'
        );

        $values['PADDLE_PRICE_ID'] = text(
            label: 'Price ID',
            placeholder: 'pri_01abc...',
            required: true,
            hint: 'Starts with pri_. A pro_ id is the product, which is not what goes here.',
            validate: function (string $v) {
                $v = trim($v);

                if (str_starts_with($v, 'pro_')) {
                    return 'That is the product id. Open the price underneath it and copy the pri_ id.';
                }

                return str_starts_with($v, 'pri_') ? null : 'A price id starts with pri_.';
            },
        );

        $values['BILLING_DISPLAY_PRICE'] = text(
            label: 'How should the price read on the subscription page?',
            default: config('billing.display_price') ?: '€8',
            hint: 'Just for display. The amount actually charged is whatever you set in Paddle.',
        );

        // Cashier formats every Paddle amount with this and defaults to USD, so
        // a euro price renders with a dollar sign until it is set.
        $values['CASHIER_CURRENCY'] = str_contains($values['BILLING_DISPLAY_PRICE'], '€') ? 'EUR' : 'USD';

        $this->newLine();
        $this->line('  About to write to .env:');
        $this->newLine();

        foreach ($values as $key => $value) {
            $secret = in_array($key, ['PADDLE_API_KEY', 'PADDLE_WEBHOOK_SECRET'], true);
            $this->line("    {$key}=".($secret ? str_repeat('•', 12).' (hidden)' : $value));
        }

        $this->newLine();

        if (! confirm('Write these to .env?', default: true)) {
            $this->warn('  Nothing was written.');

            return self::SUCCESS;
        }

        (new EnvFile($envPath))->set($values, 'Added by app:billing-setup');

        $this->newLine();
        $this->info('  Written to .env.');
        $this->newLine();
        $this->line('  Checking it over…');
        $this->newLine();

        $this->call('config:clear');
        $this->call('app:billing-check');

        note(
            "If that came back green, open /billing in the app and you should see a\n".
            'Subscribe button. In sandbox, Paddle gives you test card numbers to try it with.'
        );

        return self::SUCCESS;
    }
}

# Local development

## Requirements

PHP 8.3+, Composer, Node 22+, Postgres 16+.

Postgres specifically, not MySQL or SQLite. The analytics layer uses
`ilike`, `date_trunc` and JSON columns, and the test suite runs against the
same engine production does — a suite that passes on SQLite and fails on
Postgres is worse than no suite.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Point `DB_*` at a Postgres database you have created, then:

```bash
php artisan migrate
php artisan app:demo          # a populated account: 40 weeks of training
composer dev                  # server + queue worker + logs + vite, together
```

Sign in at <http://127.0.0.1:8000> as `demo@example.test` / `password`.

> **Use `composer dev`, not `php artisan serve` alone.** Syncing is a queued
> job; with no worker the app says "Sync queued" and nothing happens. The
> dashboard tells you when that is the case, but starting the worker is easier
> than reading the warning.

### Local defaults worth knowing

| Setting | Local | Why |
|---|---|---|
| `MAIL_MAILER=log` | Emails go to `storage/logs/laravel.log` | Including the verification link, which you will need |
| `PHOTO_DISK=local` | Photos in `storage/app/private` | No cloud credentials needed to work on the feature |
| `AUTO_VERIFY_EMAIL=false` | Registration requires verifying | Matches production once mail is configured |
| Paddle unset | Billing is off | The app runs on trial and free tiers; the subscription page says so |
| `AI_*` unset | No included AI | Add your own provider key in Profile to work on that feature |

## Using your own Hevy data

Register, verify (the link is in the log), then open **Profile** and add:

- your **Hevy API key** — stored encrypted, never logged, never sent to the AI provider
- **height, age, sex** — these drive FFMI, BMR and macro targets
- your **timezone** — decides which day and week each session counts toward

Then press **Sync Hevy**, or `php artisan hevy:sync you@example.com`.

## Tests

```bash
php artisan test                # ~850 tests; needs Postgres running
./vendor/bin/pint --test        # code style
npm run build                   # production assets
npm run test:browser            # Playwright; needs the app running
```

The browser suite is small on purpose and answers only questions a browser can:
do charts actually paint pixels, is any text below WCAG AA contrast, does the
layout hold at 390px, are tap targets reachable with a thumb. Everything else
belongs in the much faster PHP suite.

```bash
PLAYWRIGHT_CHROMIUM_PATH=/opt/pw-browsers/chromium \
APP_URL=http://127.0.0.1:8000 npm run test:browser
```

### What the unusual tests are for

Several test classes are not about coverage — they are guards on properties
that are easy to break invisibly. If one fails and looks strange, read its
docblock; each names the specific mistake it exists to catch.

| Class | Guards |
|---|---|
| `DesignTokensTest` | No raw palette colours in Blade — they cannot flip with the theme |
| `LocalisationTest` | Every language defines the same keys, files and placeholders; no page renders a raw key |
| `LocalisationCoverageTest` | No English hardcoded into a template, and no `__('English string')` keys |
| `HonestClaimsTest` | Every claim the UI makes is one the code actually implements |
| `AnalyticsCorrectnessTest` | The maths, against hand-computed fixtures |
| `SecurityTest`, `UrlGuardTest` | SSRF, ownership checks, secret leakage |
| `mobile.spec.js` | 40px tap targets, 11px text floor, no sideways scroll at 390px |

## Useful commands

```bash
php artisan app:demo                     # reseed the demo account
php artisan app:demo --missing-only      # only if it is missing (used at boot)
php artisan app:make-admin you@x.com     # grant admin
php artisan app:grant-access you@x.com --reason="me"   # free access, no expiry
php artisan app:billing-setup            # interactive Paddle wizard
php artisan app:billing-check            # verify Paddle configuration
php artisan app:send-trial-emails --dry-run
php artisan hevy:sync you@x.com          # sync one account inline
```

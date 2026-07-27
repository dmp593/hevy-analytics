# Hevy Analytics

Turns your [Hevy](https://hevy.com) training log into evidence-based analytics:
weekly hard sets per muscle graded against hypertrophy landmarks, strength
percentiles against a real powerlifting cohort, body-composition trends that say
when they are not reliable, and nutrition targets that adapt to what your weight
is actually doing.

It syncs from the Hevy API into your own database. Nothing is computed in the
cloud, and no training data leaves the app unless you turn on the optional AI
review.

---

## Deploying

`docs/DEPLOY.md` walks through two free options (Render + Neon, or Laravel
Cloud's sandbox tier). The repo ships a `Dockerfile` that runs the web server,
queue worker and scheduler in one container, and a `render.yaml` blueprint.
Operator accounts are created on first boot from `BOOTSTRAP_*` environment
variables — never from anything committed here.

## Running it locally

You need PHP 8.3+, Composer, Node 22+, and Postgres 16+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Point `.env` at a Postgres database you have created, then:

```bash
php artisan migrate
php artisan app:demo      # a populated account to look at
composer dev              # server + queue worker + logs + vite
```

Sign in at <http://127.0.0.1:8000> with `demo@example.test` / `password`.

> **Run `composer dev`, not `php artisan serve` on its own.** Syncing is a queued
> job. Without a worker the app will say "Sync queued" and nothing will happen.
> The dashboard warns you when that is the case, but it is easier to just start
> the worker.

### Using your own Hevy data

Register an account, verify your email (in local development the link is written
to `storage/logs/laravel.log`), then open **Profile** and add:

- your Hevy API key — stored encrypted, never logged, never sent to the AI provider
- height, age, sex — these drive FFMI, BMR and macro targets
- your timezone — decides which day and week each session counts toward

Then press **Sync Hevy**, or run `php artisan hevy:sync you@example.com`.

---

## Checks

```bash
php artisan test          # 121 tests
./vendor/bin/pint --test  # code style
npm run build             # production assets
npm run test:browser      # Playwright, needs the app running
```

CI runs all four on every pull request, against Postgres on PHP 8.3 and 8.4.

The browser tests exist for a specific reason: charts silently rendered as blank
boxes for months and nothing in a PHP test suite could see it. A blank canvas is
still a canvas, so those tests read pixels.

---

## How it fits together

```
app/
├─ Science/     Pure formulas. No database, no framework. Unit-tested.
├─ Services/    Orchestration: combines models, Science and external APIs.
├─ Http/        Thin controllers — validate, call a service, return a view.
├─ Jobs/        Queued work (syncing).
└─ Models/      Eloquent models: data and relationships only.
```

The rule is that layers only call downward:
`Controller → Service → (Model | Science)`. `Science/` never touches the
database; controllers never contain formulas. If you find yourself doing maths in
a controller or writing a query in `Science/`, it belongs somewhere else.

`AGENTS.md` is the full map — read that before changing code.

---

## What it will not do

- It will not invent data. Projections refuse to forecast from too few points or
  too short a span, and trends report when the fit is too scattered to trust.
- It will not estimate a one-rep max from a set far outside the range those
  formulas were validated on.
- It will not score a woman with the men's body-fat equation, or bucket your
  week using someone else's timezone.

Being quietly wrong is worse than being visibly unsure, and this codebase has the
scars to prove it.

---

## The landing page

`/` serves `resources/views/landing.blade.php` to visitors and redirects anyone
signed in to their dashboard.

It is held to the same rule as the rest of the app. Every number on it —
the price, the trial length, the free history cap, the AI allowance — is read
from `config/billing.php` rather than written into the copy, and
`LandingPageTest` changes that config and asserts the page follows it. The sample
verdicts are rendered from the same `lang/*/app.php` keys the product itself
uses, so rewording a verdict cannot leave the sales page quoting a sentence the
app no longer says.

The "what it does not do" section is not decoration. `HonestClaimsTest` pins the
claims that the code has to back — that staging a routine progression writes
nothing to Hevy, that registration starts the trial without contacting Paddle,
that the free history cap is really enforced. If you add a claim to that page,
add the test that would fail when it stops being true.

---

## Languages

English and Portuguese, switchable from the header (guests included) or from
Profile. A signed-in user's choice follows them between devices; otherwise the
app honours the browser's `Accept-Language`.

Adding a language is one config entry and one `lang/<code>/` directory. Three
tests keep it honest, and each exists because it caught something:

- **Parity** — every language must define the same keys, files and placeholders
  as English (`LocalisationTest`).
- **No raw keys** — every page is rendered in every language and checked for
  text like `guide.volume.mv`, which is what a missing key renders as. It found
  three on the AI page.
- **No hardcoded English** — Blade templates are compiled and whatever text
  survives the removal of every echo and directive is text no language file can
  reach (`LocalisationCoverageTest`). This is what a parity check cannot see: the
  guide page sat entirely untranslated while the suite was green. It also rejects
  `__('Log in')`-style string keys, which resolve from a `lang/<code>.json` this
  app does not ship and so render in English everywhere.

## Optional integrations

| Service | What it adds | Without it |
|---|---|---|
| Hevy API | All training data | Nothing to analyse |
| An AI provider | Written analysis of your metrics | Every metric still works; the AI page says so |
| FitnessVolt / OpenPowerlifting | Strength percentiles against real lifters | Falls back to a built-in model |

### AI providers

There are two ways to have an AI provider, and they are independent.

**Your own key**, set in **Profile & settings**. OpenAI, Anthropic, DeepSeek, or
anything speaking the OpenAI chat-completions schema — Groq, Together,
OpenRouter, vLLM. Keys are encrypted at rest, sent only to the provider you
picked, and never written to logs or included in your data export. Analyses on
your own key are not rationed, because you are paying for them.

**The operator's key**, set in `.env` as `AI_API_KEY`. This is the
"included with your account" path. It is capped per user and app-wide
(`AI_MONTHLY_LIMIT_PER_USER`, `AI_MONTHLY_LIMIT_GLOBAL`), counted by requests
attempted rather than analyses stored, so failed calls cannot be used to run up
a bill. Leave the key empty to ship without an included allowance.

A user's own key always takes priority.

> **A custom endpoint is a security decision.** The server makes an
> authenticated request to whatever address is configured, so
> `App\Services\AI\UrlGuard` requires https and a publicly routable address, and
> refuses redirects. Private networks, loopback, cloud metadata endpoints
> (including Alibaba's and Oracle's, which PHP's own filter flags call "public"),
> CGNAT and the NAT64 translation prefix are all blocked.
>
> The hostname is converted to the exact ASCII form libcurl will use before it is
> resolved. Without that step a host like `ⓛocalhost.attacker.com` is checked as
> one name and connected to as another, because curl applies UTS-46 mapping and
> PHP's resolver does not.
>
> The connection is then pinned to the addresses that were approved, so the name
> is never resolved a second time — re-checking more often does not close a
> DNS-rebinding window, it just adds another lookup that can be raced.
>
> `AI_ALLOW_LOCAL_PROVIDERS=true` permits loopback for a single-user install; it
> must stay off anywhere other people have accounts, and it does not open the
> private network even when on.

### Subscriptions

Billing runs on [Paddle](https://paddle.com), chosen because Paddle is the
*merchant of record*: it registers for, charges and remits VAT in every
jurisdiction. Selling a digital subscription across the EU otherwise means OSS
registration and quarterly filings, and the UK has no threshold at all for
non-established sellers.

One paid tier. The free tier is capped on **history depth**, not on which pages
exist — every page works and every metric is computed, over 30 days instead of
your whole history. That is the honest lever, because a trend needs months, the
adaptive-maintenance measurement needs four weeks of logging, and a projection
needs a year. Someone two weeks into training loses almost nothing.

Never capped: your current numbers, and your data export.

The 14-day trial is card-free and granted locally at registration — Paddle is
never contacted, so sign-up cannot fail because a payment provider is down.

Leave `PADDLE_PRICE_ID` empty to run without billing entirely.

#### Getting the Paddle credentials

Run `php artisan app:billing-check` at any point — it lists what is still
missing, where in Paddle to find it, and which webhook events to subscribe to.

Sandbox and live are **separate accounts with separate logins and separate
credentials**. Start at <https://sandbox-vendors.paddle.com> with
`PADDLE_SANDBOX=true`; nothing carries over when you switch.

| Value | Where |
|---|---|
| `PADDLE_SELLER_ID` | Developer tools → Authentication (a number) |
| `PADDLE_CLIENT_SIDE_TOKEN` | Developer tools → Authentication → Client-side tokens. Public — it ships in the page |
| `PADDLE_API_KEY` | Developer tools → Authentication → API keys. Secret, shown once |
| `PADDLE_WEBHOOK_SECRET` | Developer tools → Notifications → your destination |
| `PADDLE_PRICE_ID` | Catalog → Products → your product → its price, starts `pri_` |

The notification destination points at `https://yourdomain/paddle/webhook` and
must be subscribed to exactly these events, or Cashier never learns about a
payment:

`customer.updated`, `transaction.completed`, `transaction.updated`,
`subscription.created`, `subscription.updated`, `subscription.paused`,
`subscription.canceled`

Paddle has to be able to reach that URL, so testing webhooks locally needs a
tunnel (`ngrok http 8000` or similar) with the tunnel's public URL in the
destination and in `APP_URL`.

> **`PADDLE_WEBHOOK_SECRET` is not optional once billing is on.** Cashier only
> verifies webhook signatures when it is set; without it anyone who knows the
> URL can POST `subscription.created` and grant themselves a paid account. The
> app refuses to boot in production with billing enabled and no secret.

### Admin

`php artisan app:make-admin you@example.com` promotes an account. There is no
"make me an admin" button anywhere — the flag is not fillable and the only way
to get it is shell access.

Admins get **Account → Admin**: accounts, billing state, and complimentary
access. API keys are never displayed, only whether one is set, and every admin
action is written to an append-only audit log with who did it.

#### Free access for an account

```bash
php artisan app:grant-access you@example.com --reason="My own account"   # no end date
php artisan app:grant-access someone@example.com --days=30 --reason="Refund"
php artisan app:grant-access someone@example.com --revoke
```

The same thing is on each account's admin page. Leaving the days blank means the
access never expires — which is what your own account wants, since a grant with
a date on it eventually lapses and locks you out of your own product. Everyone
else is better off with an end date: an open-ended grant is one nobody revisits.

A comped account gets the full subscribed entitlements and is never shown a
"subscribe" prompt. The command works with no admin account in the database and
records itself in the audit log as coming from the server console, so it is also
the way to give yourself access on a fresh install.

### Your data

**Profile & settings → Your data** exports everything the app holds as one JSON
file: workouts, measurements, goals, intake logs and generated analyses. API keys
are deliberately excluded. Deleting your account removes all of it.

---

## Licence

MIT.

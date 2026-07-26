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

## Languages

English and Portuguese, switchable from the header (guests included) or from
Profile. A signed-in user's choice follows them between devices; otherwise the
app honours the browser's `Accept-Language`.

Adding a language is one config entry and one `lang/<code>/` directory. The test
suite fails if any language falls behind English on keys, files or placeholders,
so a partially translated language cannot ship unnoticed.

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
> authenticated request to whatever address is configured, so `App\Services\AI\UrlGuard`
> requires https and a publicly routable address, and refuses redirects. Private
> networks, loopback and the cloud metadata endpoint are all blocked, and the
> check is repeated on every call rather than only when the URL was saved.
> `AI_ALLOW_LOCAL_PROVIDERS=true` permits loopback for a single-user install; it
> must stay off anywhere other people have accounts.

### Your data

**Profile & settings → Your data** exports everything the app holds as one JSON
file: workouts, measurements, goals, intake logs and generated analyses. API keys
are deliberately excluded. Deleting your account removes all of it.

---

## Licence

MIT.

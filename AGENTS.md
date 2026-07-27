# Hevy Analytics — Contributor Guide

A Laravel app that syncs your [Hevy](https://hevy.com) workout data into a local
database and turns it into evidence-based training & body-composition analytics
(lean-bulk focused). This file is the map: read it before changing code.

## TL;DR for a new developer

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# point .env at a Postgres database you have created
php artisan migrate
php artisan app:demo   # a populated account, so nothing is blank while you work
composer dev           # server + queue worker + logs + vite, all at once
```

**Run `composer dev`, not `php artisan serve` on its own.** Syncing is a queued
job, so without a worker the app will say "Sync queued" and then nothing will
ever happen. If you do run the pieces separately you need at least:

```bash
php artisan serve
php artisan queue:work   # required, or syncs never run
npm run dev
```

The dashboard warns you when a sync has been sitting in the queue unclaimed, so
this failure is visible rather than silent.

Then, in the app: register → verify your email (in local dev the link is written
to `storage/logs/laravel.log`) → **Profile** (add your Hevy API key +
height/age/sex) → click **Sync Hevy**. Or from the CLI:
`php artisan hevy:sync {email}`.

Run the checks before committing:

```bash
php artisan test        # ~850 tests
./vendor/bin/pint       # code style (PSR-12 via Laravel Pint)
npm run build           # production assets
```

## Tech stack

| Concern | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Database | PostgreSQL 16+ (dev, CI and production alike — one set of behaviours) |
| Auth | Laravel Breeze (Blade), multi-user |
| Frontend | Blade + Alpine.js + [Alpine AJAX](https://alpine-ajax.js.org) |
| Styling | Tailwind CSS **only** (v4, configured in `resources/css/app.css`) |
| Charts | Chart.js, bundled via Vite (never a CDN — see `resources/js/components/chart.js`) |
| AI | DeepSeek (OpenAI-compatible), optional |

There is **no SPA / frontend framework**. Pages are server-rendered Blade;
in-page filtering swaps HTML partials via Alpine AJAX. Keep it that way.

## Architecture (where things live)

```
app/
├─ Console/Commands/     Artisan commands (hevy:sync, strength:build-opl-standards)
├─ Http/Controllers/     Thin HTTP layer — validate input, call a service, return a view
├─ Jobs/                 Queued work (SyncHevyJob)
├─ Models/               Eloquent models (data only + relationships)
├─ Science/              PURE domain formulas — no DB, no framework. Unit-tested.
│  ├─ Strength/          e1RM (Epley/Brzycki), Wilks/DOTS, strength standards
│  ├─ Volume/            muscle groups + RP volume landmarks (MEV/MAV/MRV)
│  ├─ BodyComp/          FFMI, Navy body-fat, LBM, ratios
│  ├─ Nutrition/         BMR, TDEE, macros
│  ├─ Stats/             linear regression
│  └─ Goals/             goal presets (lean bulk, cut, …)
├─ Services/             ORCHESTRATION — combines models + Science + external APIs
│  ├─ Analytics/         per-feature calculators (Volume, Strength, BodyComp, …)
│  ├─ Hevy/              Hevy API client, sync, write-back, progression
│  ├─ StrengthStandards/ layered strength-level resolver (FitnessVolt→OPL→builtin)
│  └─ AI/                DeepSeek client + metrics summary
└─ Support/              tiny framework-agnostic helpers (Chart data builder)

resources/
├─ views/
│  ├─ components/        reusable Blade UI (x-panel, x-stat, x-alert, x-chart, …)
│  ├─ <feature>/         one folder per page (dashboard, body, muscle, …)
│  └─ layouts/           app shell + navigation
├─ js/
│  ├─ app.js             registers Alpine + plugins (tiny)
│  └─ components/chart.js  the only custom JS (Chart.js wrapper)
└─ css/app.css          Tailwind + a small @layer components (.btn, .form-control…)
```

### The golden rule: layers only call downward
`Controller → Service → (Model | Science | Support)`. Science never touches the
DB. Controllers never contain formulas. If you're doing math in a controller or
a query in Science, it's in the wrong place.

## Data flow (read path)

1. `HevySync` pulls from the Hevy API into local tables (workouts, sets,
   routines, body measurements, exercise templates).
2. `Services/Analytics/*` read those tables and compute metrics, delegating the
   actual formulas to `Science/*`.
3. Controllers pass the computed arrays to Blade views.
4. `Services/Analytics/SetQuery` is the shared, filtered query of workout sets —
   every strength/volume calculation starts from it. `FilterCriteria` is the DTO
   describing the active filters (date range, routine, exercise, muscle, period).

## Frontend conventions

- **One Blade component per repeated UI concept.** Prefer `<x-...>` over copy-paste.
  Key components: `x-panel`, `x-stat`, `x-alert`, `x-info` (tooltip),
  `x-chart` (raw Chart.js), `x-line-chart` (single series), `x-muscle-volume-bars`,
  `x-balance-ratios`, `x-strength-bar`.
- **Charts:** build datasets in PHP with `App\Support\Chart` and pass simple
  `['label'=>..,'value'=>..]` series to `<x-line-chart>`. Don't hand-write
  Chart.js config in views. For two or more series on one axis use
  `<x-multi-line-chart>`, never several `Chart::line()` datasets against one
  series' labels — that plots the later series against the wrong dates.
- **Filtering:** a `<form x-target="results-id" method="get" action="…/data">`
  submits via Alpine AJAX; the controller returns a `_results.blade.php` partial
  whose root element has the matching `id`. No custom JS needed.
- **CSS:** compose Tailwind utilities in markup. For repeated primitives use the
  semantic classes in `app.css` (`.btn-primary`, `.form-control`, `.form-label`,
  `.table-head`, `.badge`). Add a new one only when a pattern repeats 3+ times.
- **Tailwind v4 has no `tailwind.config.js`.** Theme tokens live in `@theme`,
  plugins in `@plugin`, and scanned paths in `@source`, all inside
  `resources/css/app.css`. Component classes go in `@layer components`, not
  `@utility`: a custom `@utility` cannot be `@apply`'d inside another one, and
  doing so silently drops the rest of the declaration.

## Testing

- `tests/Unit/ScienceTest.php` — pure formula correctness (fast, no DB).
- `tests/Feature/AnalyticsCorrectnessTest.php` — the analytics layer against
  REAL seeded data (use the `SeedsTrainingData` trait). Every metric bug found so
  far hid behind tests that only ever ran on an empty database, so new analytics
  work belongs here.
- `tests/Feature/AbuseControlsTest.php` — the guards on things that cost money or
  touch the user's real Hevy account: AI quota, sync queueing, write idempotency.
- `tests/Feature/*` — pages render (200), strength resolver fallback order, auth.
  External HTTP is always mocked (`Http::fake`).
- When adding a feature: put the math in `Science/` with a unit test, the
  orchestration in a `Service/` (feature test), and keep the controller thin.

## External services (all optional / graceful)

- **Hevy API** — per-user API key (Profile). Stored encrypted.
- **DeepSeek** — set `DEEPSEEK_API_KEY` in `.env` to enable the AI page. Usage is
  capped per user and app-wide (`config/services.php` → `ai`), counted in
  `ai_usage_events` by requests ATTEMPTED, not analyses stored.
- **Strength levels** — layered: FitnessVolt API (free, CC BY 4.0) →
  OpenPowerlifting (`php artisan strength:build-opl-standards`) → offline model.
  Everything degrades gracefully if a source is down.

## Localisation

Every user-facing string goes through `__('app.…')` and lives in `lang/en/app.php`.
Adding a language means adding `config/locales.php` entry + a `lang/<code>/`
directory — nothing else in the codebase needs to know which languages exist.

Rules that matter:

- **Services return KEYS, not sentences.** `MuscleBalance` emits `push`/`pull`,
  `MuscleLandmarks::classify()` emits `below_maintenance`. The view turns them
  into words via `App\Support\Labels`. A service has no business deciding what
  language the reader speaks.
- **Never `sprintf` a user-facing string.** Use `:placeholders`, so a translator
  never has to reproduce a format specifier.
- **`LocalisationTest` is the guard.** It fails if any language is missing a key
  or a file, if a placeholder is dropped, or if a translation file looks like a
  copied English placeholder. Adding an English string without translating it
  breaks the build — deliberately.
- Dates go through Carbon, which the `SetLocale` middleware localises. Do not
  hand-format month or day names.

## Conventions cheat-sheet

- Weights/measurements are stored in **kg**; dates as `Y-m-d`.
- Timestamps are stored in **UTC**. Anything that buckets by day or week must
  convert to the athlete's zone first — use `$user->resolvedTimezone()` and
  `PeriodService`. Bucketing in UTC silently misfiles evening sessions.
- Every claim the UI makes should be backed by a test. Several metrics here were
  confidently wrong for months because nothing asserted them against real data.
- Money-free: never store secrets in code; use `.env` + `config/services.php`.
- Prefer readable names over comments; comment the *why*, not the *what*.
- Keep methods small and single-purpose (SRP). If a class mixes DB + math +
  HTTP, split it.

## Security (multi-user — read this)

- **Data isolation:** every query must be scoped to the current user. Create
  records through the relationship (`$user->goals()->create(...)`), never
  `Goal::create(['user_id' => ...])`.
- **IDOR:** any action receiving a model via route-model binding must call
  `$this->authorizeOwner($model)` (base `Controller`) — it 403s if the model
  isn't the current user's. There are regression tests in `SecurityTest`.
- **Mass assignment:** user-input models use explicit `$fillable` (never put
  `user_id`/`id` there). Validate with a FormRequest or `$request->validate()`
  before saving.
- **XSS:** escape with `{{ }}`. The only `{!! !!}` is the AI page, which renders
  Markdown with `html_input => 'strip'` because model output is untrusted.
- **Rate limits:** external-facing / mutating routes use `throttle:` (sync, AI,
  write-back, photo upload).
- **Headers:** `App\Http\Middleware\SecurityHeaders` adds nosniff / frame /
  referrer headers to every response.
- **Uploads:** progress photos are validated (image, ≤8 MB), stored on the
  private `local` disk, and streamed only to the owner via an auth'd route.
- **Production:** set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and a
  real `APP_KEY`. Per-user Hevy keys are encrypted at rest.

# Hevy Analytics — Contributor Guide

A Laravel app that syncs your [Hevy](https://hevy.com) workout data into a local
database and turns it into evidence-based training & body-composition analytics
(lean-bulk focused). This file is the map: read it before changing code.

## TL;DR for a new developer

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run dev            # vite dev server (hot reload)
php artisan serve      # http://127.0.0.1:8000
```

Then, in the app: register → **Profile** (add your Hevy API key + height/age/sex)
→ click **Sync Hevy**. Or from the CLI: `php artisan hevy:sync {email}`.

Run the checks before committing:

```bash
php artisan test        # 77 tests
./vendor/bin/pint       # code style (PSR-12 via Laravel Pint)
npm run build           # production assets
```

## Tech stack

| Concern | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Database | SQLite (zero-config; file at `database/database.sqlite`) |
| Auth | Laravel Breeze (Blade), multi-user |
| Frontend | Blade + Alpine.js + [Alpine AJAX](https://alpine-ajax.js.org) |
| Styling | Tailwind CSS **only** (v3) |
| Charts | Chart.js (via CDN, wrapped in one Alpine component) |
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
  Chart.js config in views.
- **Filtering:** a `<form x-target="results-id" method="get" action="…/data">`
  submits via Alpine AJAX; the controller returns a `_results.blade.php` partial
  whose root element has the matching `id`. No custom JS needed.
- **CSS:** compose Tailwind utilities in markup. For repeated primitives use the
  semantic classes in `app.css` (`.btn-primary`, `.form-control`, `.form-label`,
  `.table-head`, `.badge`). Add a new one only when a pattern repeats 3+ times.

## Testing

- `tests/Unit/ScienceTest.php` — pure formula correctness (fast, no DB).
- `tests/Feature/*` — pages render (200), sync upserts, write-back, strength
  resolver fallback order, etc. External HTTP is always mocked (`Http::fake`).
- When adding a feature: put the math in `Science/` with a unit test, the
  orchestration in a `Service/` (feature test), and keep the controller thin.

## External services (all optional / graceful)

- **Hevy API** — per-user API key (Profile). Stored encrypted.
- **DeepSeek** — set `DEEPSEEK_API_KEY` in `.env` to enable the AI page.
- **Strength levels** — layered: FitnessVolt API (free, CC BY 4.0) →
  OpenPowerlifting (`php artisan strength:build-opl-standards`) → offline model.
  Everything degrades gracefully if a source is down.

## Conventions cheat-sheet

- Weights/measurements are stored in **kg**; dates as `Y-m-d`.
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

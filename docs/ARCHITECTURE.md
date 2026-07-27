# Architecture

`AGENTS.md` at the repository root is the full contributor guide, kept next to
the code. This file is the shorter orientation: the shape, the one rule, and the
places where a change is most likely to go wrong.

## Shape

```
app/
├─ Science/     Pure formulas. No database, no framework. Unit-tested.
├─ Services/    Orchestration: combines models, Science and external APIs.
├─ Http/        Thin controllers — validate, call a service, return a view.
├─ Jobs/        Queued work (SyncHevyJob).
├─ Billing/     Subscription state and entitlements.
├─ Listeners/   Login sync, past-due notification.
├─ Support/     Small framework-agnostic helpers.
└─ Models/      Eloquent: data and relationships only.
```

## The one rule

Layers call **downward only**: `Controller → Service → (Model | Science)`.

`Science/` never touches the database; controllers never contain formulas. Doing
maths in a controller or writing a query in `Science/` means it belongs
somewhere else. This is what makes the formulas unit-testable without a
database, and it is why `ScienceTest` runs in milliseconds.

## The four places a change goes wrong

**1. Analytics that only ever ran on an empty database.** Every metric bug found
so far hid behind a test that never had data. New analytics work belongs in
`AnalyticsCorrectnessTest` against seeded fixtures, not in a test that asserts a
page returns 200.

**2. Entitlements applied in the wrong place.** There are exactly two
chokepoints where the history cap is enforced — `SetQuery` and
`BodyCompAnalytics::measurements()`. `latest()`, `latestValue()` and
`symmetry()` deliberately do **not** clamp: "your most recent measurement" must
not silently become "your most recent measurement within 30 days". Adding a
third chokepoint means two of them will eventually disagree.

**3. Billing state re-derived from Cashier.** `App\Billing\State` is the only
class that asks Cashier anything. Everything downstream works from the enum. A
`$user->subscribed()` call added anywhere else is a second source of truth, and
the admin list and the app will drift apart.

**4. A claim written next to code that later moved.** The UI states things —
"compared against male lifters", "dampened near natural ceilings". When the
code behind a sentence changes, the sentence has to change with it.
`HonestClaimsTest` pins the ones that already went wrong once.

## Data flow

1. `HevySync` pulls from the Hevy API into local tables.
2. `Services/Analytics/*` read those tables and compute, delegating formulas to
   `Science/*`.
3. Controllers pass computed arrays to Blade.
4. `SetQuery` is the shared filtered query every strength and volume calculation
   starts from; `FilterCriteria` is the DTO describing the active filters.

## Frontend

Server-rendered Blade with Alpine.js, and Alpine AJAX for in-page filtering.
**No SPA.** Charts are Chart.js, bundled through Vite — never a CDN, because a
CDN that does not load is a blank box nobody notices.

Tailwind v4 has no `tailwind.config.js`: tokens live in `@theme`, plugins in
`@plugin`, scanned paths in `@source`, all inside `resources/css/app.css`.
Write markup against the **semantic tokens** (`bg-surface`, `text-muted`,
`border-line`) and never against a raw palette colour — a raw colour cannot flip
with the theme, and `DesignTokensTest` fails the build over it.

## Localisation

Everything a user can see lives in `lang/<code>/`. Three tests keep it honest:
key parity between languages, no raw key rendered on any page, and no English
hardcoded into a template. The third exists because the first two were green
while the entire guide page sat untranslated.

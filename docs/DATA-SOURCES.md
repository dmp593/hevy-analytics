# Data sources — what exists, what is planned, what was rejected

The app analyses set-level training data. This documents every way that data
can arrive, and the honest state of the ecosystem it could arrive from.

## The uncomfortable fact the roadmap is built on

**Hevy is the only major lifting app with a real public API.** That sentence
was checked, not assumed:

| App | Public API | Set-level export | Practical route in |
|---|---|---|---|
| **Hevy** | ✅ documented, keyed — but **Pro only** | ✅ CSV, every account | API **and** CSV, both live |
| **Strong** | ❌ none | ✅ CSV export | CSV adapter (planned next) |
| **Jefit** | ❌ none public | ✅ CSV export | CSV adapter |
| **Fitbod** | ❌ none | ✅ CSV export | CSV adapter |
| **Alpha Progression** | ❌ | ✅ CSV export | CSV adapter |
| **Garmin Connect** | partner-gated | strength data is thin | not worth it |
| **Strava** | ✅ OAuth | ❌ "WeightTraining" is one blob, no sets | useless for this product |
| **Apple Health / Google Fit** | device-side | no per-set strength schema | not viable server-side |

Two conclusions fall out:

1. **CSV is not the fallback — it is the strategy.** Every competitor's users
   are reachable through one well-built CSV pipeline with per-app adapters.
   The API path is a Hevy-only luxury.
2. Even for Hevy, the API is **Pro-gated**, so an API-only product was locked
   to people already paying Hevy. The CSV import (live, `/import`) opens the
   product to every Hevy account.

## How import is built (and how to add a source)

```
app/Services/Import/
├─ HevyCsvImport.php   the Hevy adapter — also the reference implementation
└─ ImportException.php file-level failures, worded for the user
app/Support/
└─ ExerciseMuscles.php title → muscle groups, ordered keyword rules
```

An adapter's whole contract: read a file, group rows into
workouts→exercises→sets, and persist through the same `updateOrCreate` shape
`HevySync` uses, with a **deterministic `hevy_id`** (`csv:` + hash of start
time + title) so re-imports merge instead of duplicating. Muscle attribution
comes from `ExerciseMuscles` because exports carry titles only; when the
account already has an API-synced template with the same title, that template
wins — Hevy's own attribution beats keyword inference.

To add Strong: new class, its header aliases (`Workout Name`, `Date`,
`Exercise Name`, `Weight`, `Reps`…), its date formats, same persistence. The
tolerant pieces (kg/lbs, BOM, blank rows, row cap, skipped-row reporting) are
already the Hevy adapter's shape to copy.

**Provenance note:** imported workouts are ordinary workouts. The `csv:` id
prefix is the only marker, deliberately — every analytics query treats sources
identically, which is the entire point of importing.

## Nutrition APIs — evaluated, deferred

The question was whether to integrate a food database (Open Food Facts, USDA
FoodData Central, FatSecret, Nutritionix; MyFitnessPal's API is closed).

Deferred, for a product reason rather than an effort one: those APIs solve
*food lookup* — barcode → calories — which is the first step of building a
diet tracker. This app's nutrition page deliberately does the opposite job:
totals in, adaptive maintenance out. Bolting a food database on would put the
app in competition with MyFitnessPal on MyFitnessPal's terms, while what
differentiates it is the measured-maintenance loop that needs only daily
totals.

What WOULD fit, later and cheaply: a CSV import of MyFitnessPal/Cronometer
daily-totals exports into `intake_logs` — same adapter pattern as above,
feeds the adaptive TDEE directly, no third-party account required. That is the
nutrition integration worth doing first if users ask.

## Body measurements

Hevy's workout CSV does not include body measurements (the API does). For
CSV-only accounts the sources are the Nutrition page's manual entry — which
the adaptive-maintenance loop needs anyway — or adding an API key later.

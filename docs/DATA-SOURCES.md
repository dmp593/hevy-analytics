# Data sources — what exists, what is planned, what was rejected

The app analyses set-level training data. This documents every way that data
can arrive, and the honest state of the ecosystem it could arrive from.

## The uncomfortable fact the roadmap is built on

**Hevy is the only major lifting app with a real public API.** That sentence
was checked, not assumed:

| App | Public API | Set-level export | Practical route in |
|---|---|---|---|
| **Hevy** | ✅ documented, keyed — but **Pro only** | ✅ CSV, every account | API **and** CSV, both live |
| **Strong** | ❌ none | ✅ CSV export | ✅ live (signature-detected) |
| **FitNotes** | ❌ none | ✅ CSV export | ✅ live (signature-detected) |
| **Jefit** | ❌ none public | ✅ CSV export | ✅ live (packed sets unpacked) |
| **Fitbod** | ❌ none | ✅ CSV export | column-matching screen |
| **Alpha Progression** | ❌ | ✅ CSV export | column-matching screen |
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
├─ CsvImport.php         one engine, per-source knowledge (SOURCES const)
├─ UnknownCsvFormat.php  a real CSV matching nothing → column-matching screen
└─ ImportException.php   file-level failures, worded for the user
app/Support/
└─ ExerciseMuscles.php   title → muscle groups, ordered keyword rules
```

One pipeline, not one class per app. A named source is a **header signature**
plus (optionally) extra aliases and quirks: Strong is `workout_name +
exercise_name + set_order`; Jefit is `mydate + ename + logs`, whose packed
"50x10,55x8" cells are unpacked into sets; FitNotes is `date + exercise +
category`, whose `Time` column is a duration only *because* the signature
matched — on any other file a Time column means clock time. Files with no
unit information (Strong on iPhone exports none) use the unit chosen on the
upload form, which defaults to the athlete's own unit system.

A file matching no signature and missing the generic required columns raises
`UnknownCsvFormat`, and the controller shows a column-matching screen: each of
our fields, a dropdown of the file's columns, prefilled with the engine's best
guess. That screen is the adapter of last resort — "unknown app" costs the
person two minutes, not a rejection.

Persistence is shared: workouts→exercises→sets through the same
`updateOrCreate` shape `HevySync` uses, with a **deterministic `hevy_id`**
(`csv:` + hash of start time + title) so re-imports merge instead of
duplicating. Muscle attribution comes from `ExerciseMuscles` because exports
carry titles only; when the account already has an API-synced template with
the same title, that template wins — Hevy's own attribution beats keyword
inference.

To add a source: an entry in `CsvImport::SOURCES` (signature + aliases), a
quirk branch only if the file packs data in a shape the row loop cannot read,
and a feature test built from a real export. **The Strong/FitNotes/Jefit
shapes here were built from documented formats, not live files — the first
real export of each is worth a validation pass.**

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

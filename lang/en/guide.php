<?php

/*
 | The guide.
 |
 | Its own file rather than a section of app.php, because it is long-form prose
 | rather than interface strings: a translator working on it is doing a different
 | job from a translator working on button labels, and mixing the two makes both
 | harder to review.
 |
 | Inline <strong> is kept inside the strings and rendered with {!! !!}. The
 | emphasis is part of the sentence — it is what makes a wall of explanation
 | skimmable — and pulling it into the template would mean cutting every
 | paragraph into fragments that no longer read as sentences to whoever
 | translates them.
 */

return [

    'intro' => 'No jargon. This page explains every number the app shows, why it matters for <strong>building muscle while staying lean</strong>, and what "good" looks like. Everything is based on published strength and nutrition science (sources at the bottom).',

    'nav' => [
        'data' => 'Getting data in',
        'checkins' => 'Check-ins & compare',
        'volume' => 'Weekly sets & volume',
        'strength' => 'Strength & 1RM',
        'levels' => 'Strength levels',
        'body' => 'Body composition',
        'accuracy' => 'Measurement accuracy',
        'leanbulk' => 'Lean-bulk signals',
        'nutrition' => 'Calories & macros',
        'projections' => 'Projections',
        'balance' => 'Muscle balance',
    ],

    'data' => [
        'title' => 'Getting data in — sync, imports & units',
        'lead' => 'There are two doors into the app, and they end in exactly the same place:',
        'api' => '<strong>Hevy API key (Hevy Pro):</strong> paste it once in your Profile and every workout syncs automatically, body measurements included.',
        'csv' => '<strong>CSV import (any account):</strong> upload the export your training app produces on the :import page. <strong>Hevy, Strong, FitNotes and Jefit</strong> files are recognised automatically by their columns; any other CSV gets a <strong>column-matching screen</strong> — you point each field (date, exercise, weight, reps…) at the right column and import anyway.',
        'protections' => 'Details that protect your numbers:',
        'idempotent' => '<strong>Re-uploading is always safe.</strong> A workout\'s identity is its date and name, so the same file — or a newer export overlapping it — merges instead of duplicating.',
        'units_ask' => '<strong>Units are asked when the file is silent.</strong> Strong\'s iPhone export, for example, carries no unit column; the import form asks whether its weights are kg or lb, pre-set to your own preference.',
        'units_pref' => '<strong>Your unit preference</strong> (Profile → Units, or the toggle on the welcome card) changes how you type and read everything — height in ft/in, bodyweight and loads in lb, tape in inches. Internally everything is stored metric, so calculations never mix units, and anything written back to Hevy stays metric because Hevy\'s API is.',
        'muscles' => '<strong>Muscles are inferred from exercise names</strong> on CSV imports — the files carry none. Standard names match well; if you later add an API key, Hevy\'s own attribution takes over.',
    ],

    'checkins' => [
        'title' => 'Check-ins, photos & comparison',
        'lead' => 'A <strong>check-in</strong> is one date with up to four photos — <strong>front, back, left side, right side</strong> — plus a bodyweight and a note. Any single photo is enough to save; taking all four from the same spots, in the same light, is what makes comparisons honest.',
        'measurements' => '<strong>Manual measurements</strong> (Body page → Log measurements): weight, body fat and fourteen tape measurements, every field optional. The <strong>date is editable</strong> — a Saturday measurement logged on Monday belongs to Saturday — and re-saving a date fills in or corrects it without touching fields you left blank.',
        'compare' => '<strong>Compare</strong> (:compare): pick 2–4 check-in dates and they line up side by side — every "front" photo on one row, every "back" on the next — with a measurements table underneath showing each date\'s change against the oldest one.',
        'judgement' => '<strong>Only the weight change is judged, and only against your goal:</strong> gaining reads green on a bulk and red on a cut, maintenance judges both directions equally, and a change under about 1% of bodyweight counts as stable — that much is water and meal timing. Tape measurements are never coloured, and the arrow and sign always say what the colour says.',
    ],

    'volume' => [
        'title' => 'Weekly sets & volume landmarks (MV · MEV · MAV · MRV)',
        'lead' => 'The single biggest driver of muscle growth is <strong>how many hard sets you do per muscle each week</strong> (a "hard set" is a real working set taken close to failure; warm-ups do not count). Researchers describe four weekly-set landmarks per muscle:',
        'mv' => '<strong>MV — Maintenance Volume:</strong> the bare minimum to <em>keep</em> the muscle you already have. Below this, you slowly lose size.',
        'mev' => '<strong>MEV — Minimum Effective Volume:</strong> the fewest sets that actually <em>build</em> new muscle. This is the "start growing" line.',
        'mav' => '<strong>MAV — Maximum Adaptive Volume:</strong> the sweet spot where you get the <em>most</em> growth for the effort. Most of your training should land between MEV and MAV.',
        'mrv' => '<strong>MRV — Maximum Recoverable Volume:</strong> the most you can do and still recover. Beyond this it is "junk volume" — extra fatigue, no extra gains.',
        'zone' => 'So the ideal zone is <strong>MEV → MAV</strong>. The app labels each muscle:',

        'status' => [
            'below_maintenance' => '<strong>Below maintenance</strong> — too few sets; the muscle is likely stalling or shrinking. Add sets.',
            'maintenance' => '<strong>Maintenance</strong> — holding, not really growing. Fine on a cut; add sets on a bulk.',
            'optimal' => '<strong>Optimal</strong> — in the MEV–MAV growth zone. Keep going.',
            'growth' => '<strong>Growth (high)</strong> — near your recovery ceiling; good if you are recovering well.',
            'junk' => '<strong>Junk</strong> — above MRV; trim sets, you are only adding fatigue.',
        ],

        'example_title' => 'Reading an example: "Chest 7.9/wk · below maintenance (MEV 10 / MAV 16)"',
        'example_body' => 'You are averaging <strong>7.9 hard chest sets per week</strong>. Chest needs at least <strong>about 8 to maintain</strong> and <strong>about 10 (MEV) to actually grow</strong>, with the best returns up to <strong>about 16 (MAV)</strong>. At 7.9 you are <em>under</em> the growth line, so your chest probably is not developing as fast as it could. The fix: add three to five chest sets a week — one extra press plus one fly session — to reach 11–14, and check again in a few weeks.',
        'landmark_note' => 'Landmarks per muscle are ADAPTED from Renaissance Periodization (Israetel et al.) to this app’s counting: RP’s tables count direct work only, while this app credits secondary muscles half a set — so some rows deliberately differ from RP’s published numbers, and a few muscles RP does not table (forearms, lower back) carry our own conservative estimates. Starting guidelines, not physiology — individual recovery varies.',

        'tonnage_title' => 'Tonnage (volume-load)',
        'tonnage_body' => '<strong>Tonnage is weight × reps, summed across all your sets.</strong> It is the total work done and a quick proxy for training stimulus. Rising tonnage over weeks and months is progressive overload: you are doing more than before, which is what drives growth.',
    ],

    'strength' => [
        'title' => 'Strength & estimated 1RM',
        'e1rm_title' => 'Estimated 1RM (e1RM)',
        'e1rm_body' => 'Your <strong>one-rep max</strong> is the most you could lift once. Testing it is risky, so it is <em>estimated</em> from normal sets using two well-known formulas (<strong>Epley</strong> and <strong>Brzycki</strong>), averaged. For example, 100 kg × 10 reps is roughly a <strong>133 kg</strong> estimated 1RM.',
        'e1rm_why' => 'Why it matters: e1RM is the cleanest way to see whether you are getting <strong>stronger over time</strong>, even when your rep counts and weights vary. A rising e1RM line is real strength progress. Only sets of <strong>12 reps or fewer</strong> are used, because the formulas become inaccurate at high reps.',
        'rpe_title' => 'RPE & RIR',
        'rpe_body' => '<strong>RPE</strong> (Rate of Perceived Exertion, 1–10) is how hard a set felt. <strong>RIR</strong> (Reps In Reserve) is how many reps you had left: RIR = 10 − RPE. If you log RPE, it is used to make the e1RM estimate more accurate — a set left three reps short is really stronger than the raw numbers suggest.',
        'wilks_title' => 'Wilks / DOTS & relative strength',
        'wilks_body' => 'These score your lifts <strong>relative to your bodyweight</strong>, so progress stays fair as your weight changes. <strong>Relative strength</strong> is lift ÷ bodyweight — a 1.5× bodyweight bench, for instance. This matters during a bulk, where lifting more <em>and</em> gaining weight can flatter your progress; Wilks and DOTS cut through that.',
    ],

    'levels' => [
        'title' => 'Strength levels (beginner → elite)',
        'lead' => 'For each barbell lift you are placed on a <strong>0–100% bar</strong> that compares you to other lifters of the <strong>same sex, bodyweight and age</strong>. The fill percentage is your percentile — <em>"stronger than X% of lifters"</em>.',
        'boundaries' => 'The separator lines mark the boundaries between levels, mapped to well-known percentiles:',

        'tier' => [
            'beginner' => '<strong>Beginner</strong> — up to about the 20th percentile (can perform the lift, trained roughly a month).',
            'novice' => '<strong>Novice</strong> — around the 20th (trained regularly for about six months).',
            'intermediate' => '<strong>Intermediate</strong> — around the 50th percentile, the average trained lifter (about two years).',
            'advanced' => '<strong>Advanced</strong> — around the 80th (five or more years of progress).',
            'elite' => '<strong>Elite</strong> — 95th and above (competitive-level strength).',
        ],

        'how' => 'So "stronger than 86%" sits in the <strong>advanced</strong> band, closing in on elite. Your 1RM is estimated (Epley/Brzycki) from your best set, divided by bodyweight, and <strong>age-adjusted</strong> so you are compared to peers your age — strength peaks around 25 to 35 and declines afterwards.',
        'sources' => '<strong>Where the data comes from (layered):</strong> the free <strong>FitnessVolt API</strong> (CC BY 4.0) is tried first — it serves two separate populations: <strong>verified competition</strong> percentiles from <strong>OpenPowerlifting</strong> (2.5M+ judged lifts) and <strong>self-reported gym</strong> percentiles (Symmetric Strength), age-adjusted. If it is unreachable, the app falls back to a locally built <strong>OpenPowerlifting</strong> table, then to an offline ratio model.',
        'why_two_title' => 'Why two different percentages?',
        'why_two_body' => 'The same lift ranks differently depending on who you are compared to. Against everyday <strong>gym</strong> lifters you rank high; against <strong>competition</strong> lifters — a much stronger crowd — you rank lower. A 100 kg bench at 68 kg bodyweight is about the <strong>83rd percentile (gym)</strong> but about the <strong>46th (verified competition)</strong>. The <strong>gym</strong> number is the headline, because it matches what apps like Hevy show, and the verified number is displayed beside it. Neither is "wrong" — they are different reference populations.',
        'footnote' => 'The big three (squat, bench, deadlift) have verified competition data; accessory lifts use ratio estimates. Only weight × reps barbell lifts are covered; anything else falls back to the offline model. Powered by FitnessVolt (CC BY 4.0); data from OpenPowerlifting (CC0) and Symmetric Strength.',
    ],

    'body' => [
        'title' => 'Body composition',
        'trend_weight' => '<strong>Trend weight:</strong> the weight tiles show a smoothed average that leans on your recent weigh-ins (half-life about 10 days), because a single reading swings 1–2 kg on water and meal timing alone. The charts still plot every raw reading.',
        'fat' => '<strong>Body fat %:</strong> the share of your weight that is fat. Lower is leaner. During a lean bulk you want this to creep up only slowly.',
        'lean' => '<strong>Lean mass:</strong> everything that is not fat — muscle, bone, water, organs. Growing lean mass while fat stays flat is the whole goal.',
        'navy' => '<strong>Navy body fat %:</strong> an independent estimate from tape measurements (neck, waist, height), shown as a cross-check against your scale or caliper number.',
        'rfm' => '<strong>RFM (Relative Fat Mass):</strong> a third body-fat estimate from just height and waist (Woolcott &amp; Bergman, 2018), validated against DXA scans. Three imperfect estimators agreeing beats trusting any one of them.',
        'ffmi' => '<strong>FFMI (Fat-Free Mass Index):</strong> your muscularity, adjusted for height — like BMI, but for muscle. <strong>Normalised FFMI</strong> standardises it to 1.80 m so it is comparable. Roughly: 19 is average, 22 is fit, and 25 is around the natural ceiling for most men.',
        'waist_height' => '<strong>Waist-to-height ratio:</strong> waist ÷ height. Keeping it <strong>under 0.5</strong> is a simple health marker. If it climbs during a bulk, you are adding fat around the middle.',
        'waist_hip' => '<strong>Waist-to-hip ratio:</strong> waist ÷ hips. The WHO flags <strong>0.90 and above</strong> for men and <strong>0.85 and above</strong> for women as elevated cardiometabolic risk. The cut-offs are sex-specific, so the colour only appears once your sex is on file.',
        'symmetry' => '<strong>Left/right symmetry:</strong> the percentage difference between your left and right limb measurements. Over about 5% suggests an imbalance worth some single-arm or single-leg work.',
    ],

    'accuracy' => [
        'title' => 'Measurement accuracy — why one number is not trusted',
        'lead' => 'Smart scales estimate body fat with <strong>BIA — Bioelectrical Impedance Analysis</strong>: a tiny current passed through your feet. It is convenient but <strong>noisy, and not very accurate for absolute values</strong>:',
        'bia_error' => 'Off by roughly <strong>3 to 8 percentage points</strong> of body fat versus a lab (DEXA) scan.',
        'bia_feet' => 'Foot-to-foot scales mostly read your <strong>lower body</strong> and estimate the rest.',
        'bia_swing' => 'Readings swing with <strong>hydration, carbs, salt, food, time of day, temperature and recent training</strong> — often more than your real weekly change.',

        'protects_lead' => 'That is why a single "you gained 77% fat" reading can be mostly measurement noise. This app protects you from that:',
        'protect_trends' => '<strong>Trends, not two points:</strong> partitioning is computed from a line fitted through <em>many</em> readings.',
        'protect_confidence' => '<strong>Confidence label:</strong> if there is not enough consistent data, the estimate is marked <em>low confidence</em> and the warning softens.',
        'protect_triangulate' => '<strong>Triangulation:</strong> weight, waist, chest, arm and strength trends are shown together — muscle gain looks like chest and arms plus strength rising while the waist stays flat.',
        'protect_source' => '<strong>Choose your source</strong> (Profile → body-fat source): <strong>scale (BIA)</strong>, <strong>Navy tape</strong> (neck/waist/height — steadier), or <strong>manual</strong> (type your own estimate).',

        'bottom_line' => '<strong>Bottom line:</strong> the mirror and progress photos are legitimately the most reliable everyday gauge. Use the :photos page for that, and treat body-fat percentage as a rough trend rather than gospel.',
        'consistent_title' => 'Measure consistently',
        'consistent_body' => 'Same time of day, <strong>fasted, in the morning, after the bathroom</strong>, similar hydration. Consistency matters far more than the absolute number.',
    ],

    'leanbulk' => [
        'title' => 'Lean-bulk signals',
        'rate' => '<strong>Weight rate (%BW/week):</strong> how fast your bodyweight is changing, as a percentage of your bodyweight, per week. For a lean bulk the sweet spot is <strong>+0.25% to +0.5% a week</strong> — roughly 0.2 to 0.35 kg a week at 70 kg. Faster means more fat; slower or negative means you are not feeding growth.',
        'p_ratio' => '<strong>P-ratio (partitioning):</strong> of the weight you gained, the fraction that was <em>lean</em> mass rather than fat. A p-ratio of 0.7 means 70% of the gain was muscle, which is excellent. A low p-ratio while bulking is a warning to slow the surplus down.',
        'waist' => '<strong>Waist versus muscle trend:</strong> if your waist is growing faster than your chest and arms, that is a proxy for fat gain outpacing muscle gain, and the app flags it.',
        'note' => 'These need a few bodyweight and measurement entries over time to become reliable. Log your weight regularly in Hevy, or on the nutrition page.',
    ],

    'nutrition' => [
        'title' => 'Calories & macros',
        'bmr' => '<strong>BMR (Basal Metabolic Rate):</strong> the calories your body burns at complete rest, just to stay alive. Mifflin-St Jeor is used, or Katch-McArdle when your body fat is known — more accurate for lean people.',
        'tdee' => '<strong>TDEE / maintenance:</strong> the total calories you burn in a day (BMR × your activity level, plus training). Eat this to stay the same weight.',
        'pal' => '<strong>Activity level (PAL):</strong> a multiplier for how active you are, from 1.2 (sedentary) to 1.9 (very active). Set it in your profile.',
        'target' => '<strong>Target calories:</strong> maintenance adjusted for your goal — for example +7.5% for a lean bulk, −20% for a cut.',
        'macros' => '<strong>Protein / fat / carbs:</strong> protein (about 1.6 to 2.2 g/kg) builds and protects muscle; fat (at least 0.5 g/kg) supports hormones; carbs fuel your training and fill the remaining calories.',
        'adaptive' => '<strong>Adaptive maintenance:</strong> once you log some food and weight, your <em>real</em> maintenance is back-calculated from how your weight actually moved, and your targets are nudged — because a formula is only a starting estimate.',
    ],

    'projections' => [
        'title' => 'Projections',
        'lead' => 'A straight <strong>trend line</strong> is fitted through your recent data and extended out one month, quarter, semester and year. These are <strong>"if you keep going like this" estimates, not promises.</strong>',
        'r2' => '<strong>R² (quality):</strong> how well the trend line fits your data, from 0 to 1. Near 1 is a clean, reliable trend; near 0 means noisy data, and the projection should be treated with caution.',
        'dampened' => '<strong>Damped:</strong> longer horizons are scaled down, because progress that continued in a straight line for a year would be unusual. The reduction depends only on how far ahead the estimate reaches — it is not a model of your personal ceiling.',
    ],

    'balance' => [
        'title' => 'Muscle balance',
        'lead' => 'Compares training volume between opposing and related areas, so you develop evenly and reduce injury risk:',
        'push_pull' => '<strong>Push versus pull</strong> (chest, shoulders and triceps against back and biceps)',
        'quads_posterior' => '<strong>Quads versus posterior chain</strong> (front thigh against hamstrings, glutes and lower back)',
        'upper_lower' => '<strong>Upper versus lower body</strong>',
        'ratio' => 'A ratio near <strong>1.0</strong> is healthy — 0.8 to 1.25 is treated as balanced. Well off 1.0 means one side is getting far more work, which is a common cause of stalls, posture problems and injuries.',
    ],

    'sources' => [
        'title' => 'Sources',
        'schoenfeld' => 'Schoenfeld et al. — dose-response of weekly sets and hypertrophy.',
        'rp' => 'Renaissance Periodization (Israetel et al.) — MV/MEV/MAV/MRV volume landmarks.',
        'epley' => 'Epley (1985) and Brzycki (1998) — 1RM estimation formulas.',
        'mifflin' => 'Mifflin-St Jeor (1990) and Katch-McArdle — BMR and energy expenditure.',
        'helms' => 'Helms, Aragon, Morton et al. — protein intake and lean-gain rate guidelines.',
        'kouri' => 'Kouri et al. — FFMI and the natural muscular ceiling.',
        'disclaimer' => 'Educational only — not medical advice.',
    ],

];

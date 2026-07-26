<?php

/*
 | Interface strings.
 |
 | Keys are grouped by where they appear, and read as sentences so a translator
 | can tell what they are looking at without opening the app. Anything a user
 | can see belongs here — including tooltips and empty states, which are exactly
 | the strings that get forgotten and then sit in English forever.
 */

return [

    'brand' => 'Hevy Analytics',

    'nav' => [
        'dashboard' => 'Dashboard',
        'performance' => 'Performance',
        'levels' => 'Levels',
        'muscle' => 'Muscle',
        'body' => 'Body',
        'photos' => 'Photos',
        'nutrition' => 'Nutrition',
        'projections' => 'Projections',
        'routines' => 'Routines',
        'goals' => 'Goals',
        'ai' => 'AI',
        'guide' => 'Guide',
        'sync' => 'Sync Hevy',
        'profile' => 'Profile & Settings',
        'write_operations' => 'Write Operations',
        'log_out' => 'Log Out',
        'toggle' => 'Toggle navigation',
        'primary' => 'Primary',
        'language' => 'Language',
    ],

    'sync' => [
        'queued' => 'Sync queued — refresh in a moment to see your new data.',
        'running' => 'Syncing your Hevy data…',
        'stalled' => 'Your sync is queued but nothing has picked it up. If you are running this app yourself, start a queue worker: php artisan queue:work',
        'failed' => 'Your last sync failed: :error',
        'needs_key' => 'Add your Hevy API key in Profile first.',
        'last_synced' => 'Last sync: :when',
        'never' => 'never',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'welcome_title' => 'Welcome to Hevy Analytics',
        'welcome_body' => 'To get started, add your Hevy API key and body profile, then sync your data.',
        'set_up_profile' => 'Set up profile',
        'workouts' => ':count workouts',
        'goal' => 'Goal',
        'no_goal' => 'No goal set',
        'weight' => 'Weight',
        'body_fat' => 'Body fat',
        'lean_mass' => 'Lean mass',
        'volume_4wk' => 'Volume (4wk)',
        'hard_sets_4wk' => 'Hard sets (4wk)',
        'training_volume' => 'Training volume',
        'training_volume_sub' => 'Weekly tonnage, last 6 months',
        'body_composition' => 'Body composition',
        'body_composition_sub' => 'Weight vs lean mass, last 12 months',
        'weekly_sets' => 'Weekly sets per muscle',
        'weekly_sets_sub' => 'vs hypertrophy landmarks',
        'muscle_balance' => 'Muscle balance',
        'whats_next' => "What's next",
        'at_a_glance' => 'At a glance',
        'trends' => 'Trends',
        'detail' => 'Detail',
        'more_alerts' => ':count more',
        'nutrition_targets' => 'Nutrition targets',
        'nutrition_targets_sub' => 'Daily, based on your goal',
        'no_training_yet' => 'No training logged yet',
        'no_training_yet_body' => 'Sync your Hevy account and your weekly sets per muscle will appear here.',
        'muscle_balance_sub' => 'Set ratios, 3 months',
    ],

    'nutrition' => [
        'recompute' => 'Recompute targets',
        'maintenance' => 'Maintenance (TDEE)',
        'target_calories' => 'Target calories',
        'protein' => 'Protein',
        'fat' => 'Fat',
        'carbs' => 'Carbs',
    ],

    'chart' => [
        'no_data' => 'No data for this range.',
        'no_body_data' => 'Log a bodyweight measurement to see this chart.',
        'no_tape_data' => 'Log a tape measurement to see this chart.',
        'no_sets' => 'No sets logged in this range yet.',
        'description' => ':type chart showing :series from :from to :to',
    ],

    'balance' => [
        'not_enough' => 'not enough data yet',
        'balanced' => 'balanced',
        'skewed' => 'skewed',
        'unit' => 'sets/wk',
    ],

    'profile' => [
        'timezone' => 'Timezone',
        'timezone_help' => 'Decides which day and week each session counts toward. Get this wrong and an evening workout can land in the previous week, skewing your sets-per-week figures.',
        'language' => 'Language',
        'language_help' => 'The language this app is shown in. Your training data is never translated.',
        'follow_browser' => 'Follow my browser',
    ],

    'ai' => [
        'generate' => 'Generate analysis',
        'regenerate' => 'Regenerate',
        'unavailable' => "AI analysis isn't available on your account yet.",
        'quota' => ':remaining of :limit analyses left this month.',
        'quota_spent' => 'You have used all :limit AI analyses for this month. Your allowance resets on :date.',
        'temporarily_unavailable' => 'AI analysis is temporarily unavailable while we top up capacity. Please try again later.',
        'unchanged' => 'Showing your latest analysis — your data has not changed since it was generated.',
    ],

    'routines' => [
        'edit_progression' => 'Edit / stage progression →',
    ],

    'write' => [
        'confirm' => 'Confirm & push',
        'retry' => 'Retry',
        'stalled' => 'stalled',
        'details' => 'details',
    ],

    'photos' => [
        'limit_reached' => 'You have reached the limit of :limit progress photos. Delete some older ones to add more.',
    ],

    'muscles' => [
        'chest' => 'Chest',
        'shoulders' => 'Shoulders',
        'triceps' => 'Triceps',
        'biceps' => 'Biceps',
        'forearms' => 'Forearms',
        'lats' => 'Lats',
        'upper_back' => 'Upper back',
        'lower_back' => 'Lower back',
        'traps' => 'Traps',
        'abdominals' => 'Abs',
        'quadriceps' => 'Quads',
        'hamstrings' => 'Hamstrings',
        'glutes' => 'Glutes',
        'calves' => 'Calves',
        'abductors' => 'Abductors',
        'adductors' => 'Adductors',
        'neck' => 'Neck',
        'cardio' => 'Cardio',
        'full_body' => 'Full body',
        'other' => 'Other',
    ],

    'volume_status' => [
        'below_maintenance' => 'Below maintenance',
        'maintenance' => 'Maintenance',
        'optimal' => 'Optimal',
        'growth' => 'Growth',
        'junk' => 'Junk volume',
        'per_week' => ':count/wk',
    ],

    'balance_groups' => [
        'push' => 'Push',
        'pull' => 'Pull',
        'quads' => 'Quads',
        'posterior' => 'Posterior chain',
        'upper' => 'Upper body',
        'lower' => 'Lower body',
    ],

    'series' => [
        'weight' => 'Weight (kg)',
        'lean_mass' => 'Lean mass (kg)',
        'tonnage' => 'Tonnage (kg)',
        'body_fat' => 'Body fat %',
        'ffmi' => 'FFMI',
        'chest' => 'Chest',
        'waist' => 'Waist',
        'bicep' => 'Bicep (R)',
        'e1rm' => 'e1RM (kg)',
    ],

    'alerts' => [
        'gaining_too_fast' => 'Gaining too fast',
        'gaining_too_fast_body' => 'Weight is rising :observed%BW/week vs target :target%. Excess likely fat — trim ~150-250 kcal.',
        'bulk_stalling' => 'Bulk stalling',
        'bulk_stalling_body' => 'Only :observed%BW/week gained (target :target%). Add ~150-250 kcal to keep building.',
        'on_track' => 'On track',
        'on_track_bulk_body' => 'Gaining :observed%BW/week — right in the lean-bulk band.',
        'cut_too_slow' => 'Cut too slow / gaining',
        'cut_too_slow_body' => 'Losing only :observed%BW/week (target :target%). Reduce ~200 kcal.',
        'cut_too_fast' => 'Cutting too aggressively',
        'cut_too_fast_body' => 'Dropping :observed%BW/week (target :target%) — muscle-loss risk. Add calories.',
        'on_track_cut_body' => 'Losing :observed%BW/week — sustainable cut pace.',
        'poor_partitioning' => 'Poor gain partitioning',
        'poor_partitioning_body' => 'Estimated ~:percent% of recent weight gain was lean mass, based on a trend over :readings readings.',
        'bia_caveat' => 'Note: body-fat comes from a BIA scale (noisy) — confirm with the mirror, waist tape and strength.',
        'poor_partitioning_low' => 'Partitioning estimate (low confidence)',
        'poor_partitioning_low_body' => "Rough estimate suggests ~:percent% of gain was lean, but there isn't enough consistent data to trust it yet.",
        'fat_climbing' => 'Body-fat climbing',
        'fat_climbing_body' => 'Body fat trending up ~:points points recently. Watch the surplus size.',
        'waist_high' => 'Waist-to-height above 0.5',
        'waist_high_body' => 'Waist-to-height ratio exceeds the 0.5 health guideline — monitor waist during the bulk.',
        'asymmetry' => ':part asymmetry',
        'asymmetry_body' => ':part differs :percent% left vs right — consider unilateral work.',
        'no_data' => 'Not enough data yet',
        'no_data_body' => 'Log a few more body measurements over time to unlock trend-based alerts.',
    ],

    'goals' => [
        'lean_bulk' => 'Lean bulk',
        'hypertrophy' => 'Hypertrophy / maingain',
        'aggressive_bulk' => 'Aggressive bulk',
        'recomposition' => 'Recomposition',
        'cut' => 'Cut / fat loss',
        'strength' => 'Strength',
    ],

    'units' => [
        'bw_per_week' => ':value%BW/wk',
        'per_week' => '/wk',
    ],

    'theme' => [
        'system' => 'Match my system',
        'light' => 'Light',
        'dark' => 'Dark',
        'switch_dark' => 'Switch to dark mode',
        'switch_light' => 'Switch to light mode',
    ],

    'sections' => [
        'today' => 'Today',
        'today_question' => 'What should I do this week?',
        'training' => 'Training',
        'training_question' => 'Am I getting stronger?',
        'body' => 'Body',
        'body_question' => 'Is my body changing the way I want?',
        'outlook' => 'Outlook',
        'outlook_question' => 'Where does this end up?',
    ],

    /*
     | Page titles, plus the question each page exists to answer. The subtitle is
     | not decoration: "Muscle analysis" tells you nothing you could act on,
     | whereas "Which muscles are under-trained, and by how much" tells you what
     | the page is for before you have read a single number.
     */
    'pages' => [
        'body' => 'Body composition',
        'body_sub' => 'Weight, body fat and lean mass — the trend, not the daily noise',
        'muscle' => 'Muscle analysis',
        'muscle_sub' => 'Which muscles are under-trained, and by how much',
        'performance' => 'Performance over time',
        'performance_sub' => 'Whether the weight on the bar is actually going up',
        'levels' => 'Strength levels',
        'levels_sub' => 'How your lifts compare to other lifters your age, weight and sex',
        'routines' => 'Routines',
        'routines_sub' => 'Which of your routines are progressing, and which have stalled',
        'routine_edit' => 'Edit: :name',
        'nutrition' => 'Nutrition',
        'nutrition_sub' => 'Calorie and macro targets derived from your own data',
        'photos' => 'Progress photos',
        'photos_sub' => 'The measurement a scale cannot make',
        'projections' => 'Projections',
        'projections_sub' => 'Where your current rate of change leads — estimates, not promises',
        'goals' => 'Goals',
        'goals_sub' => 'Set the target that every other page measures you against',
        'ai' => 'AI deep analysis',
        'ai_sub' => 'A written read of your recent training and body data',
        'guide' => 'Guide',
        'guide_sub' => 'What every metric on this site means, and why it matters',
        'write' => 'Write operations',
        'write_sub' => 'Changes staged for Hevy — review the payload before anything is sent',
        'profile' => 'Profile & settings',
    ],

    'tips' => [
        'weight' => "Your bodyweight and how fast it's changing per week. Lean-bulk sweet spot: +0.25% to +0.5% of bodyweight per week.",
        'body_fat' => 'Percent of your weight that is fat. Navy = an independent tape-measure estimate used as a cross-check.',
        'lean_mass' => "Everything that isn't fat (mostly muscle). FFMI = muscularity adjusted for height; ~25 is near the natural ceiling for men.",
        'hard_sets' => 'Total working sets (warm-ups excluded) in the last 4 weeks — the main driver of muscle growth.',
        'landmarks' => "Hard working sets per muscle each week. MEV = minimum to grow, MAV = best-return zone. Aim between them. 'Below maintenance' means too few sets to grow.",
    ],

    'common' => [
        'save' => 'Save',
        'saved' => 'Saved.',
        'apply' => 'Apply',
        'all' => 'All',
        'from' => 'From',
        'to' => 'To',
        'period' => 'Period',
        'learn_more' => 'Learn more →',
        'skip_to_content' => 'Skip to content',
        'more_info' => 'More info',
    ],

];

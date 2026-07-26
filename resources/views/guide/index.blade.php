<x-ui.page :title="__('app.pages.guide')" :subtitle="__('app.pages.guide_sub')" width="4xl" class="space-y-6">
    <x-panel>
        <p class="text-sm text-body">
            No jargon. This page explains every number the app shows, why it matters for
            <strong>building muscle while staying lean</strong>, and what "good" looks like.
            Everything is based on published strength &amp; nutrition science (sources at the bottom).
        </p>
        <nav class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach(['volume'=>'Weekly sets & volume','strength'=>'Strength & 1RM','levels'=>'Strength levels','body'=>'Body composition','accuracy'=>'Measurement accuracy','leanbulk'=>'Lean-bulk signals','nutrition'=>'Calories & macros','projections'=>'Projections','balance'=>'Muscle balance'] as $id=>$label)
                <a href="#{{ $id }}" class="rounded-full bg-surface-sunk px-3 py-1 hover:bg-surface-sunk">{{ $label }}</a>
            @endforeach
        </nav>
    </x-panel>

    {{-- VOLUME --}}
    <x-panel id="volume" title="Weekly sets & volume landmarks (MV · MEV · MAV · MRV)">
        <div class="prose prose-sm max-w-none text-body">
            <p>The single biggest driver of muscle growth is <strong>how many hard sets you do per muscle each week</strong>
               (a "hard set" = a real working set taken close to failure; warm-ups don't count). Researchers describe four
               weekly-set landmarks per muscle:</p>
            <ul>
                <li><strong>MV — Maintenance Volume:</strong> the bare minimum to <em>keep</em> the muscle you already have. Below this, you slowly lose size.</li>
                <li><strong>MEV — Minimum Effective Volume:</strong> the fewest sets that actually <em>build</em> new muscle. This is the "start growing" line.</li>
                <li><strong>MAV — Maximum Adaptive Volume:</strong> the sweet spot where you get the <em>most</em> growth for the effort. Most of your training should land between MEV and MAV.</li>
                <li><strong>MRV — Maximum Recoverable Volume:</strong> the most you can do and still recover. Beyond this it's "junk volume" — extra fatigue, no extra gains.</li>
            </ul>
            <p>So the ideal zone is <strong>MEV → MAV</strong>. The app labels each muscle:</p>
            <div class="not-prose grid sm:grid-cols-2 gap-2 my-3">
                <div class="rounded-lg border p-3"><span class="inline-block h-2 w-2 rounded-full bg-bad"></span> <strong>Below maintenance</strong> — too few sets; the muscle is likely stalling or shrinking. Add sets.</div>
                <div class="rounded-lg border p-3"><span class="inline-block h-2 w-2 rounded-full bg-warn"></span> <strong>Maintenance</strong> — holding, not really growing. Fine on a cut; add sets on a bulk.</div>
                <div class="rounded-lg border p-3"><span class="inline-block h-2 w-2 rounded-full bg-good"></span> <strong>Optimal</strong> — in the MEV–MAV growth zone. Keep going.</div>
                <div class="rounded-lg border p-3"><span class="inline-block h-2 w-2 rounded-full bg-grow"></span> <strong>Growth (high)</strong> — near your recovery ceiling; great if you're recovering well.</div>
                <div class="rounded-lg border p-3"><span class="inline-block h-2 w-2 rounded-full bg-accent"></span> <strong>Junk</strong> — above MRV; trim sets, you're just adding fatigue.</div>
            </div>

            <div class="not-prose rounded-lg bg-brand-soft border border-brand p-4 my-3">
                <p class="text-sm font-semibold text-brand-ink">Reading your example: "Chest 7.9/wk · below maintenance (MEV 10 / MAV 16)"</p>
                <p class="text-sm text-brand-ink mt-1">
                    You're averaging <strong>7.9 hard chest sets per week</strong>. Chest needs at least <strong>~8 to maintain</strong>
                    and <strong>~10 (MEV) to actually grow</strong>, with the best returns up to <strong>~16 (MAV)</strong>.
                    At 7.9 you're <em>under</em> the growth line — <strong>yes, your chest probably isn't developing as fast as it could.</strong>
                    Fix: add ~3–5 chest sets/week (e.g., one extra press + one fly session) to reach 11–14 and re-check in a few weeks.
                    Your lats and upper back (3–5/wk) are even further below — prioritise back volume too.
                </p>
            </div>
            <p class="text-xs text-muted">Landmarks per muscle follow Renaissance Periodization (Dr. Mike Israetel et&nbsp;al.). They're starting guidelines — individual recovery varies. Secondary muscles count as a half-set by default.</p>

            <h3>Tonnage (a.k.a. volume-load)</h3>
            <p><strong>Tonnage = weight × reps, summed across all your sets.</strong> It's total "work" done and a quick proxy for
               training stimulus. Rising tonnage over weeks/months = progressive overload = you're doing more than before, which drives growth.</p>
        </div>
    </x-panel>

    {{-- STRENGTH --}}
    <x-panel id="strength" title="Strength & estimated 1RM">
        <div class="prose prose-sm max-w-none text-body">
            <h3>Estimated 1RM (e1RM)</h3>
            <p>Your <strong>1-rep max</strong> is the most you could lift once. Testing it is risky, so we <em>estimate</em> it from
               normal sets using two well-known formulas (<strong>Epley</strong> and <strong>Brzycki</strong>) and average them.
               Example: 100&nbsp;kg × 10 reps ≈ a <strong>133&nbsp;kg</strong> estimated 1RM.</p>
            <p>Why it matters: e1RM is the cleanest way to see if you're getting <strong>stronger over time</strong>, even when your
               rep counts and weights vary. A rising e1RM line = real strength progress. We only trust sets of <strong>≤12 reps</strong>
               (formulas get inaccurate at very high reps).</p>
            <h3>RPE &amp; RIR</h3>
            <p><strong>RPE</strong> (Rate of Perceived Exertion, 1–10) is how hard a set felt. <strong>RIR</strong> (Reps In Reserve)
               is how many reps you had left: RIR = 10 − RPE. If you log RPE, we use it to make the e1RM estimate more accurate
               (a set left 3 reps short is really stronger than the raw numbers suggest).</p>
            <h3>Wilks / DOTS &amp; relative strength</h3>
            <p>These score your lifts <strong>relative to your bodyweight</strong> so progress is fair as your weight changes.
               <strong>Relative strength</strong> = lift ÷ bodyweight (e.g., a 1.5× bodyweight bench). Handy during a bulk because
               lifting more <em>and</em> gaining weight can hide/flatter progress — Wilks/DOTS cut through that.</p>
        </div>
    </x-panel>

    {{-- LEVELS --}}
    <x-panel id="levels" title="Strength levels (Beginner → Elite)">
        <div class="prose prose-sm max-w-none text-body">
            <p>For each barbell lift we place you on a <strong>0–100% bar</strong> that compares you to other lifters of the
               <strong>same sex, bodyweight and age</strong>. The fill % is literally your percentile — <em>"stronger than X% of lifters."</em></p>
            <p>The separator lines mark the boundaries between levels, mapped to well-known percentiles:</p>
            <ul>
                <li><strong>Beginner</strong> — up to ~20th percentile (can perform the lift, trained ~1 month).</li>
                <li><strong>Novice</strong> — ~20th (trained regularly ~6 months).</li>
                <li><strong>Intermediate</strong> — ~50th percentile = the average trained lifter (~2 years).</li>
                <li><strong>Advanced</strong> — ~80th (5+ years of progress).</li>
                <li><strong>Elite</strong> — ~95th+ (competitive-level strength).</li>
            </ul>
            <p>So "stronger than 86%" sits in the <strong>Advanced</strong> band, closing in on Elite. We estimate your 1RM (Epley/Brzycki)
               from your best set, divide by bodyweight, and <strong>age-adjust</strong> so you're compared to peers your age (strength peaks ~25–35 and declines later).</p>
            <p class="text-xs text-muted"><strong>Where the data comes from (layered):</strong> we try the free <strong>FitnessVolt API</strong> (CC BY 4.0) first — it serves two separate populations: <strong>verified competition</strong> percentiles from <strong>OpenPowerlifting</strong> (2.5M+ judged lifts) and <strong>self-reported gym</strong> percentiles (Symmetric Strength), age-adjusted. If it's unreachable we fall back to a locally-built <strong>OpenPowerlifting</strong> table, then to an offline ratio model.</p>
            <div class="not-prose rounded-lg bg-brand-soft border border-brand p-3 my-2 text-sm text-brand-ink">
                <strong>Why two different percentages?</strong> The same lift ranks differently depending on who you're compared to.
                Against everyday <strong>gym</strong> lifters you rank high; against <strong>competition</strong> lifters (a much stronger crowd) you rank lower.
                Example: a 100&nbsp;kg bench at 68&nbsp;kg is ~<strong>83rd percentile (gym)</strong> but ~<strong>46th (verified competition)</strong>.
                We show the <strong>gym</strong> number as the headline (it matches apps like Hevy) and display the verified number beside it.
                Neither is "wrong" — they're different reference populations.
            </div>
            <p class="text-xs text-muted">Big-3 (squat/bench/deadlift) have verified competition data; accessory lifts use ratio estimates. Only weight×reps barbell lifts are covered; others fall back to the offline model. Powered by FitnessVolt (CC BY 4.0); data from OpenPowerlifting (CC0) &amp; Symmetric Strength.</p>
        </div>
    </x-panel>

    {{-- BODY --}}
    <x-panel id="body" title="Body composition">            <div class="prose prose-sm max-w-none text-body">
            <ul>
                <li><strong>Body fat %:</strong> share of your weight that is fat. Lower = leaner. During a lean bulk you want this to creep up only slowly.</li>
                <li><strong>Lean mass:</strong> everything that isn't fat (muscle, bone, water, organs). Growing lean mass while fat stays flat is the whole goal.</li>
                <li><strong>Navy body fat %:</strong> an independent estimate from tape measurements (neck, waist, height). We show it as a cross-check against your scale/caliper number.</li>
                <li><strong>FFMI (Fat-Free Mass Index):</strong> your muscularity, adjusted for height — like BMI but for muscle. <strong>Normalized FFMI</strong> standardises it to 1.80&nbsp;m so it's comparable. Rough guide: ~19 average, ~22 fit, ~25 is around the natural ceiling for most men.</li>
                <li><strong>Waist-to-height ratio:</strong> waist ÷ height. Keeping it <strong>under 0.5</strong> is a simple health marker. If it climbs during a bulk, you're adding fat around the middle.</li>
                <li><strong>Left/right symmetry:</strong> % difference between your left and right limb measurements. Over ~5% suggests an imbalance worth some single-arm/leg work.</li>
            </ul>
        </div>
    </x-panel>

    {{-- ACCURACY --}}
    <x-panel id="accuracy" title="Measurement accuracy — why we don't trust one number">
        <div class="prose prose-sm max-w-none text-body">
            <p>Smart scales (Xiaomi, etc.) estimate body fat with <strong>BIA — Bioelectrical Impedance Analysis</strong>: a tiny
               current through your feet. It's convenient but <strong>noisy and not very accurate for absolute values</strong>:</p>
            <ul>
                <li>Off by roughly <strong>±3–8% body fat</strong> versus a lab (DEXA) scan.</li>
                <li>Foot-to-foot scales mostly read your <strong>lower body</strong> and estimate the rest.</li>
                <li>Readings swing with <strong>hydration, carbs, salt, food, time of day, temperature and recent training</strong> — often more than your real weekly change.</li>
            </ul>
            <p>That's why a single "you gained 77% fat" reading can be mostly measurement noise. This app protects you from that:</p>
            <ul>
                <li><strong>Trends, not two points:</strong> partitioning is computed from a line fitted through <em>many</em> readings.</li>
                <li><strong>Confidence label:</strong> if there isn't enough consistent data, the estimate is marked <em>low confidence</em> and the warning softens.</li>
                <li><strong>Triangulation:</strong> we show weight, waist, chest, arm and strength trends together — muscle gain looks like chest/arms + strength rising while waist stays flat.</li>
                <li><strong>Choose your source</strong> (Profile → Body-fat source): <strong>Scale (BIA)</strong>, <strong>Navy tape</strong> (neck/waist/height — steadier), or <strong>Manual</strong> (type your own estimate).</li>
            </ul>
            <p class="not-prose rounded-lg bg-brand-soft border border-brand p-3 text-sm text-brand-ink">
                <strong>Bottom line:</strong> the mirror and progress photos are legitimately the most reliable everyday gauge. Use the
                <a href="{{ route('photos') }}" class="underline">Photos</a> page for that, and treat body-fat % as a rough trend, not gospel.
            </p>
            <h3>Measure consistently</h3>
            <p>Same time of day, <strong>fasted, in the morning, after the bathroom</strong>, similar hydration. Consistency matters far more than the absolute number.</p>
        </div>
    </x-panel>

    {{-- LEAN BULK --}}
    <x-panel id="leanbulk" title="Lean-bulk signals">            <div class="prose prose-sm max-w-none text-body">
            <ul>
                <li><strong>Weight rate (%BW/week):</strong> how fast your bodyweight is changing, as a percent of your bodyweight, per week. For a lean bulk the sweet spot is <strong>+0.25% to +0.5%/week</strong> (e.g., ~0.2–0.35&nbsp;kg/wk at 70&nbsp;kg). Faster = more fat; slower/negative = you're not feeding growth.</li>
                <li><strong>P-ratio (partitioning):</strong> of the weight you gained, what fraction was <em>lean</em> mass vs fat. A p-ratio of 0.7 means 70% of the gain was muscle — excellent. A low p-ratio while bulking is a warning to slow the surplus down.</li>
                <li><strong>Waist vs muscle trend:</strong> if your waist is growing faster than your chest/arms, that's a proxy for fat gain outpacing muscle gain — the app flags it.</li>
            </ul>
            <p class="text-xs text-muted">These need a few body-weight/measurement entries over time to become reliable. Log weight regularly in Hevy (or on the Nutrition page).</p>
        </div>
    </x-panel>

    {{-- NUTRITION --}}
    <x-panel id="nutrition" title="Calories & macros">
        <div class="prose prose-sm max-w-none text-body">
            <ul>
                <li><strong>BMR (Basal Metabolic Rate):</strong> calories your body burns at complete rest just to stay alive. We use the Mifflin-St&nbsp;Jeor formula, or Katch-McArdle when your body-fat is known (more accurate for lean people).</li>
                <li><strong>TDEE / Maintenance:</strong> total calories you burn in a day (BMR × your activity level + training). Eat this to stay the same weight.</li>
                <li><strong>Activity level (PAL):</strong> a multiplier for how active you are (1.2 sedentary → 1.9 very active). Set it in your Profile.</li>
                <li><strong>Target calories:</strong> maintenance adjusted for your goal (e.g., +7.5% for a lean bulk, −20% for a cut).</li>
                <li><strong>Protein / Fat / Carbs (macros):</strong> Protein (~1.6–2.2&nbsp;g/kg) builds &amp; protects muscle; Fat (≥0.5&nbsp;g/kg) supports hormones; Carbs fuel your training and fill the remaining calories.</li>
                <li><strong>Adaptive maintenance:</strong> once you log some food + weight, we back-calculate your <em>real</em> maintenance from how your weight actually moved, and nudge your targets — because formulas are only a starting estimate.</li>
            </ul>
        </div>
    </x-panel>

    {{-- PROJECTIONS --}}
    <x-panel id="projections" title="Projections">
        <div class="prose prose-sm max-w-none text-body">
            <p>We fit a straight <strong>trend line</strong> through your recent data and extend it out 1 month / quarter / semester / year.
               These are <strong>"if you keep going like this" estimates, not promises.</strong></p>
            <ul>
                <li><strong>R² (quality):</strong> how well the trend line fits your data, 0–1. Near 1 = a clean, reliable trend (strong); near 0 = noisy data, treat the projection with caution (weak).</li>
                <li><strong>Dampened:</strong> for things with a natural ceiling (muscle/FFMI/strength), we curve the projection to slow down over time, because gains realistically decelerate — a straight line would over-promise.</li>
            </ul>
        </div>
    </x-panel>

    {{-- BALANCE --}}
    <x-panel id="balance" title="Muscle balance">
        <div class="prose prose-sm max-w-none text-body">
            <p>Compares training volume between opposing/related areas so you develop evenly and reduce injury risk:</p>
            <ul>
                <li><strong>Push vs Pull</strong> (chest/shoulders/triceps vs back/biceps)</li>
                <li><strong>Quads vs Posterior chain</strong> (front thigh vs hamstrings/glutes/lower back)</li>
                <li><strong>Upper vs Lower body</strong></li>
            </ul>
            <p>A ratio near <strong>1.0</strong> (we treat 0.8–1.25 as balanced) is healthy. Well off 1.0 means one side is getting far more work — a common cause of stalls, posture issues and injuries.</p>
        </div>
    </x-panel>

    <x-panel title="Sources">
        <ul class="text-xs text-muted list-disc list-inside space-y-1">
            <li>Schoenfeld et al. — dose-response of weekly sets and hypertrophy.</li>
            <li>Renaissance Periodization (Israetel et al.) — MV/MEV/MAV/MRV volume landmarks.</li>
            <li>Epley (1985) &amp; Brzycki (1998) — 1RM estimation formulas.</li>
            <li>Mifflin-St Jeor (1990), Katch-McArdle/Cunningham — BMR/energy expenditure.</li>
            <li>Helms, Aragon, Morton et al. — protein intake &amp; lean-gain rate guidelines.</li>
            <li>Kouri et al. — FFMI and the natural muscular ceiling.</li>
        </ul>
        <p class="mt-3 text-xs text-faint">Educational only — not medical advice.</p>
    </x-panel>
</x-ui.page>

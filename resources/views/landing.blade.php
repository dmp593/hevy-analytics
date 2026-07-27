{{--
    The landing page.

    Everything on it is a claim the app has to be able to back up. The sample
    outputs are the app's own sentences, rendered from the same language file the
    product uses, so a wording change on a verdict changes the pitch too and the
    page cannot drift into describing a product that no longer exists. The
    numbers in them are illustrative and say so.

    The limits section is not a legal disclaimer bolted on at the end. It is the
    reason the rest of the page is believable: a page that only lists upsides is
    the one people feel misled by after they pay.
--}}
<x-landing-layout>
    @php
        $free = config('billing.entitlements.free');
        $paid = config('billing.entitlements.subscribed');
        $trialDays = (int) config('billing.trial_days');
        $price = config('billing.display_price');
    @endphp

    {{-- The "demo unavailable" bounce lands here; without this it is silent. --}}
    @if (session('error'))
        <div class="mx-auto max-w-5xl px-5 pt-6">
            <div class="rounded-md border border-bad/30 bg-bad-soft px-4 py-3 text-sm text-bad">{{ session('error') }}</div>
        </div>
    @endif

    {{-- ------------------------------------------------------------- hero --}}
    <section class="mx-auto grid max-w-5xl gap-12 px-5 pb-14 pt-16 sm:pb-20 sm:pt-24 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start lg:gap-10">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-brand">{{ __('app.landing.eyebrow') }}</p>

            <h1 class="mt-3 text-balance text-4xl font-bold leading-[1.1] tracking-tight text-ink sm:text-5xl">
                {{ __('app.landing.headline') }}
            </h1>

            <p class="mt-5 max-w-2xl text-pretty text-lg leading-relaxed text-body">
                {{ __('app.landing.subhead') }}
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <x-ui.button :href="route('register')" size="lg">{{ __('app.landing.cta', ['days' => $trialDays]) }}</x-ui.button>
                {{-- The cheaper promise first-ish: no account, no key, nothing
                     to configure — one click into a populated account. --}}
                <form method="POST" action="{{ route('demo.enter') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="lg">{{ __('app.landing.demo_cta') }}</x-ui.button>
                </form>
                <p class="w-full text-sm text-muted sm:w-auto">{{ __('app.landing.cta_note', ['price' => $price]) }}</p>
            </div>

            {{-- Said here, in the first screen, rather than discovered after
                 signing up. Without a Hevy key the app has nothing to read, and
                 finding that out on the empty dashboard is a waste of an
                 evening. --}}
            <p class="mt-6 max-w-2xl rounded-lg border border-line bg-surface-sunk px-4 py-3 text-sm text-body">
                {!! __('app.landing.requirement', [
                    'hevy' => '<a href="https://hevy.com" target="_blank" rel="noopener noreferrer" class="font-medium text-brand-ink underline">Hevy</a>',
                ]) !!}
            </p>
        </div>

        {{-- The pitch in one object: the same week of training, read twice. The
             left half is what a log can tell you and the right half is what this
             adds — which is the entire product, so it goes above the fold rather
             than three scrolls down. --}}
        <div class="rounded-2xl border border-line bg-surface p-1.5 shadow-xs">
            <div class="rounded-xl bg-surface-sunk px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('app.landing.compare_log') }}</p>
                <ul class="mt-2 space-y-1 font-mono text-xs text-body">
                    @foreach (['Bench press — 4×8 @ 72.5 kg', 'Incline DB press — 3×10 @ 26 kg', 'Cable fly — 3×12 @ 15 kg'] as $line)
                        <li class="truncate">{{ $line }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-faint">{{ __('app.landing.compare_log_note') }}</p>
            </div>

            <div class="px-4 pb-4 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('app.landing.compare_this') }}</p>
                <p class="mt-2 text-sm font-semibold leading-snug text-ink">{{ __('app.landing.compare_verdict') }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-body">{{ __('app.landing.compare_verdict_body') }}</p>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------------ the gap --}}
    <section class="border-y border-subtle bg-surface">
        <div class="mx-auto max-w-5xl px-5 py-14 sm:py-16">
            <h2 class="text-2xl font-bold tracking-tight text-ink">{{ __('app.landing.gap_title') }}</h2>
            <p class="mt-3 max-w-3xl text-pretty text-body">{{ __('app.landing.gap_body') }}</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach (['sets', 'bulk', 'calories'] as $question)
                    <div class="rounded-xl border border-line bg-canvas p-5">
                        <p class="text-base font-semibold text-ink">{{ __("app.landing.question_{$question}") }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ __("app.landing.question_{$question}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- the output --}}
    <section class="mx-auto max-w-5xl px-5 py-14 sm:py-20">
        <h2 class="text-2xl font-bold tracking-tight text-ink">{{ __('app.landing.output_title') }}</h2>
        <p class="mt-3 max-w-3xl text-pretty text-body">{{ __('app.landing.output_body') }}</p>

        <div class="mt-8 grid gap-5 lg:grid-cols-2">

            {{-- Volume. The headline and body are the app's own strings, with the
                 same placeholders filled by the same kind of values it computes. --}}
            <article class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('app.pages.muscle') }}</p>
                <p class="mt-2 text-base font-semibold text-ink">
                    {{ trans_choice('app.muscle.verdict.under_headline', 3, ['count' => 3, 'total' => 11]) }}
                </p>
                <p class="mt-1.5 text-sm leading-relaxed text-body">
                    {{ __('app.muscle.verdict.under_body', [
                        'fixes' => __('app.muscle.verdict.fix', ['muscle' => 'Lats', 'sets' => 5])
                            .', '.__('app.muscle.verdict.fix', ['muscle' => 'Chest', 'sets' => 3]),
                    ]) }}
                </p>

                <div class="mt-4 space-y-2.5">
                    @foreach ([
                        ['muscle' => 'Lats', 'current' => 4.0, 'mev' => 10, 'mav' => 20, 'tone' => 'bad'],
                        ['muscle' => 'Chest', 'current' => 7.9, 'mev' => 10, 'mav' => 16, 'tone' => 'warn'],
                        ['muscle' => 'Quads', 'current' => 14.0, 'mev' => 8, 'mav' => 18, 'tone' => 'good'],
                    ] as $row)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-xs">
                                <span class="font-medium text-ink">{{ $row['muscle'] }}</span>
                                <span class="tabular-nums text-muted">
                                    {{ __('app.muscle.verdict.per_week_now', [
                                        'current' => $row['current'].' '.__('app.landing.sets_week'),
                                        'target' => $row['mev'].'–'.$row['mav'],
                                    ]) }}
                                </span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                                <div @class([
                                        'h-full rounded-full',
                                        'bg-bad' => $row['tone'] === 'bad',
                                        'bg-warn' => $row['tone'] === 'warn',
                                        'bg-good' => $row['tone'] === 'good',
                                    ])
                                     style="width: {{ min(100, round($row['current'] / $row['mav'] * 100)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            {{-- Body composition. --}}
            <article class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('app.pages.body') }}</p>
                <p class="mt-2 text-base font-semibold text-ink">{{ __('app.body.verdict.gaining_both') }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-body">
                    {{ __('app.body.verdict.gaining_both_body', [
                        'signals' => 'Chest, right bicep and your top lift',
                        'waist' => '+1.4 cm / 8 wk',
                    ]) }}
                </p>
                <p class="mt-3 inline-flex rounded-full bg-surface-sunk px-2.5 py-1 text-xs text-muted">
                    {{ __('app.body.verdict.confidence_high', ['count' => 4]) }}
                </p>
            </article>

            {{-- Nutrition — the measured-vs-formula distinction, which is the one
                 number here that no calculator can give you. --}}
            <article class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('app.pages.nutrition') }}</p>
                <p class="mt-2 text-base font-semibold text-ink">{{ __('app.nutrition.verdict.higher_headline') }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-body">
                    {{ __('app.nutrition.verdict.higher_body', [
                        'days' => 23,
                        'measured' => '2,840',
                        'gap' => '210 kcal',
                        'formula' => '2,630 kcal',
                    ]) }}
                </p>
            </article>

            {{-- Strength percentile. --}}
            <article class="rounded-xl border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('app.pages.levels') }}</p>
                <p class="mt-2 text-base font-semibold text-ink">{{ __('app.landing.levels_headline') }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-body">{{ __('app.landing.levels_body') }}</p>

                <div class="mt-4 space-y-3">
                    @foreach ([
                        ['lift' => 'Bench press', 'gym' => 83, 'meet' => 46],
                        ['lift' => 'Squat', 'gym' => 61, 'meet' => 28],
                    ] as $lift)
                        <div>
                            <div class="flex items-baseline justify-between text-xs">
                                <span class="font-medium text-ink">{{ $lift['lift'] }}</span>
                                <span class="tabular-nums text-muted">
                                    {{ __('app.landing.percentile_pair', ['gym' => $lift['gym'], 'meet' => $lift['meet']]) }}
                                </span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                                <div class="h-full rounded-full bg-tier-4" style="width: {{ $lift['gym'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        </div>

        <p class="mt-5 text-xs text-muted">{{ __('app.landing.output_disclaimer') }}</p>
    </section>

    {{-- ------------------------------------------------------------ how --}}
    <section class="border-y border-subtle bg-surface">
        <div class="mx-auto max-w-5xl px-5 py-14 sm:py-16">
            <h2 class="text-2xl font-bold tracking-tight text-ink">{{ __('app.landing.how_title') }}</h2>

            <ol class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ([1, 2, 3] as $step)
                    <li>
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-semibold text-on-fill">{{ $step }}</span>
                        <p class="mt-3 font-semibold text-ink">{{ __("app.landing.step_{$step}") }}</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ __("app.landing.step_{$step}_body") }}</p>
                    </li>
                @endforeach
            </ol>

            <p class="mt-8 max-w-3xl rounded-lg border border-line bg-canvas px-4 py-3 text-sm text-body">
                {{ __('app.landing.write_back') }}
            </p>
        </div>
    </section>

    {{-- --------------------------------------------------------- the limits --}}
    <section class="mx-auto max-w-5xl px-5 py-14 sm:py-20">
        <h2 class="text-2xl font-bold tracking-tight text-ink">{{ __('app.landing.limits_title') }}</h2>
        <p class="mt-3 max-w-3xl text-pretty text-body">{{ __('app.landing.limits_intro') }}</p>

        <ul class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach (['logging', 'scale', 'projections', 'landmarks', 'ai', 'medical'] as $limit)
                <li class="flex gap-3 rounded-xl border border-line bg-surface p-5">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM8.7 7.3a1 1 0 0 0-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 1 0 1.4 1.4L10 11.4l1.3 1.3a1 1 0 0 0 1.4-1.4L11.4 10l1.3-1.3a1 1 0 0 0-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ __("app.landing.limit_{$limit}") }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ __("app.landing.limit_{$limit}_body") }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ----------------------------------------------------------- pricing --}}
    <section class="border-t border-subtle bg-surface">
        <div class="mx-auto max-w-5xl px-5 py-14 sm:py-20">
            <h2 class="text-2xl font-bold tracking-tight text-ink">{{ __('app.landing.pricing_title') }}</h2>
            <p class="mt-3 max-w-3xl text-pretty text-body">
                {{ __('app.landing.pricing_body', ['days' => $free['history_days']]) }}
            </p>

            <div class="mt-8 grid gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-line bg-canvas p-6">
                    <p class="text-sm font-semibold text-ink">{{ __('app.landing.plan_free') }}</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-ink">{{ __('app.landing.plan_free_price') }}</p>
                    <ul class="mt-5 space-y-2.5 text-sm text-body">
                        <li>{{ __('app.landing.free_history', ['days' => $free['history_days']]) }}</li>
                        <li>{{ __('app.landing.free_pages') }}</li>
                        <li>{{ __('app.landing.free_photos', ['count' => $free['progress_photos']]) }}</li>
                        <li class="text-muted">{{ __('app.landing.free_no_ai') }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border-2 border-brand bg-canvas p-6">
                    <p class="text-sm font-semibold text-brand-ink">{{ __('app.landing.plan_paid') }}</p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-ink">
                        {{ $price }}<span class="text-base font-medium text-muted">{{ __('app.landing.per_month') }}</span>
                    </p>
                    <ul class="mt-5 space-y-2.5 text-sm text-body">
                        <li>{{ __('app.landing.paid_history') }}</li>
                        <li>{{ __('app.landing.paid_adaptive') }}</li>
                        <li>{{ __('app.landing.paid_projections') }}</li>
                        <li>{{ __('app.landing.paid_ai', ['count' => $paid['ai_analyses_per_month']]) }}</li>
                        <li>{{ __('app.landing.paid_photos', ['count' => $paid['progress_photos']]) }}</li>
                    </ul>

                    <div class="mt-6">
                        <x-ui.button :href="route('register')" size="lg">{{ __('app.landing.cta', ['days' => $trialDays]) }}</x-ui.button>
                    </div>
                    <p class="mt-3 text-xs text-muted">{{ __('app.landing.trial_terms', ['days' => $trialDays]) }}</p>
                </div>
            </div>

            <p class="mt-6 max-w-3xl text-sm text-muted">{{ __('app.landing.data_note') }}</p>
        </div>
    </section>

    {{-- ----------------------------------------------------------- sources --}}
    <section class="mx-auto max-w-5xl px-5 py-14">
        <h2 class="text-lg font-semibold text-ink">{{ __('app.landing.sources_title') }}</h2>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-muted">{{ __('app.landing.sources_body') }}</p>
    </section>
</x-landing-layout>

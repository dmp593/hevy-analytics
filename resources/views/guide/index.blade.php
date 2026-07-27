{{--
    The guide.

    Every sentence lives in lang/<code>/guide.php, including the inline <strong>
    emphasis, which is why this file renders with {!! !!}. The strings are the
    app's own copy rather than user input, and cutting each paragraph into
    fragments so the markup could stay here would leave a translator working on
    half-sentences.
--}}
<x-ui.page :title="__('app.pages.guide')" :subtitle="__('app.pages.guide_sub')" width="4xl" class="space-y-6">
    <x-panel>
        <p class="text-sm text-body">{!! __('guide.intro') !!}</p>
        <nav class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach (['volume', 'strength', 'levels', 'body', 'accuracy', 'leanbulk', 'nutrition', 'projections', 'balance'] as $id)
                <a href="#{{ $id }}" class="rounded-full bg-surface-sunk px-3 py-1 hover:bg-surface-sunk">{{ __('guide.nav.'.$id) }}</a>
            @endforeach
        </nav>
    </x-panel>

    {{-- VOLUME --}}
    <x-panel id="volume" :title="__('guide.volume.title')">
        <div class="prose prose-sm max-w-none text-body">
            <p>{!! __('guide.volume.lead') !!}</p>
            <ul>
                @foreach (['mv', 'mev', 'mav', 'mrv'] as $landmark)
                    <li>{!! __('guide.volume.'.$landmark) !!}</li>
                @endforeach
            </ul>
            <p>{!! __('guide.volume.zone') !!}</p>

            <div class="not-prose grid sm:grid-cols-2 gap-2 my-3">
                @foreach ([
                    'below_maintenance' => 'bg-bad',
                    'maintenance' => 'bg-warn',
                    'optimal' => 'bg-good',
                    'growth' => 'bg-grow',
                    'junk' => 'bg-accent',
                ] as $status => $dot)
                    <div class="rounded-lg border p-3">
                        <span class="inline-block h-2 w-2 rounded-full {{ $dot }}"></span>
                        {!! __('guide.volume.status.'.$status) !!}
                    </div>
                @endforeach
            </div>

            <div class="not-prose rounded-lg bg-brand-soft border border-brand p-4 my-3">
                <p class="text-sm font-semibold text-brand-ink">{{ __('guide.volume.example_title') }}</p>
                <p class="text-sm text-brand-ink mt-1">{!! __('guide.volume.example_body') !!}</p>
            </div>
            <p class="text-xs text-muted">{{ __('guide.volume.landmark_note') }}</p>

            <h3>{{ __('guide.volume.tonnage_title') }}</h3>
            <p>{!! __('guide.volume.tonnage_body') !!}</p>
        </div>
    </x-panel>

    {{-- STRENGTH --}}
    <x-panel id="strength" :title="__('guide.strength.title')">
        <div class="prose prose-sm max-w-none text-body">
            <h3>{{ __('guide.strength.e1rm_title') }}</h3>
            <p>{!! __('guide.strength.e1rm_body') !!}</p>
            <p>{!! __('guide.strength.e1rm_why') !!}</p>
            <h3>{{ __('guide.strength.rpe_title') }}</h3>
            <p>{!! __('guide.strength.rpe_body') !!}</p>
            <h3>{{ __('guide.strength.wilks_title') }}</h3>
            <p>{!! __('guide.strength.wilks_body') !!}</p>
        </div>
    </x-panel>

    {{-- LEVELS --}}
    <x-panel id="levels" :title="__('guide.levels.title')">
        <div class="prose prose-sm max-w-none text-body">
            <p>{!! __('guide.levels.lead') !!}</p>
            <p>{{ __('guide.levels.boundaries') }}</p>
            <ul>
                @foreach (['beginner', 'novice', 'intermediate', 'advanced', 'elite'] as $tier)
                    <li>{!! __('guide.levels.tier.'.$tier) !!}</li>
                @endforeach
            </ul>
            <p>{!! __('guide.levels.how') !!}</p>
            <p class="text-xs text-muted">{!! __('guide.levels.sources') !!}</p>

            <div class="not-prose rounded-lg bg-brand-soft border border-brand p-3 my-2 text-sm text-brand-ink">
                <strong>{{ __('guide.levels.why_two_title') }}</strong>
                {!! __('guide.levels.why_two_body') !!}
            </div>
            <p class="text-xs text-muted">{{ __('guide.levels.footnote') }}</p>
        </div>
    </x-panel>

    {{-- BODY --}}
    <x-panel id="body" :title="__('guide.body.title')">
        <div class="prose prose-sm max-w-none text-body">
            <ul>
                @foreach (['fat', 'lean', 'navy', 'ffmi', 'waist_height', 'symmetry'] as $item)
                    <li>{!! __('guide.body.'.$item) !!}</li>
                @endforeach
            </ul>
        </div>
    </x-panel>

    {{-- ACCURACY --}}
    <x-panel id="accuracy" :title="__('guide.accuracy.title')">
        <div class="prose prose-sm max-w-none text-body">
            <p>{!! __('guide.accuracy.lead') !!}</p>
            <ul>
                @foreach (['bia_error', 'bia_feet', 'bia_swing'] as $item)
                    <li>{!! __('guide.accuracy.'.$item) !!}</li>
                @endforeach
            </ul>
            <p>{{ __('guide.accuracy.protects_lead') }}</p>
            <ul>
                @foreach (['protect_trends', 'protect_confidence', 'protect_triangulate', 'protect_source'] as $item)
                    <li>{!! __('guide.accuracy.'.$item) !!}</li>
                @endforeach
            </ul>
            <p class="not-prose rounded-lg bg-brand-soft border border-brand p-3 text-sm text-brand-ink">
                {!! __('guide.accuracy.bottom_line', [
                    'photos' => '<a href="'.route('photos').'" class="underline">'.__('app.pages.photos').'</a>',
                ]) !!}
            </p>
            <h3>{{ __('guide.accuracy.consistent_title') }}</h3>
            <p>{!! __('guide.accuracy.consistent_body') !!}</p>
        </div>
    </x-panel>

    {{-- LEAN BULK --}}
    <x-panel id="leanbulk" :title="__('guide.leanbulk.title')">
        <div class="prose prose-sm max-w-none text-body">
            <ul>
                @foreach (['rate', 'p_ratio', 'waist'] as $item)
                    <li>{!! __('guide.leanbulk.'.$item) !!}</li>
                @endforeach
            </ul>
            <p class="text-xs text-muted">{{ __('guide.leanbulk.note') }}</p>
        </div>
    </x-panel>

    {{-- NUTRITION --}}
    <x-panel id="nutrition" :title="__('guide.nutrition.title')">
        <div class="prose prose-sm max-w-none text-body">
            <ul>
                @foreach (['bmr', 'tdee', 'pal', 'target', 'macros', 'adaptive'] as $item)
                    <li>{!! __('guide.nutrition.'.$item) !!}</li>
                @endforeach
            </ul>
        </div>
    </x-panel>

    {{-- PROJECTIONS --}}
    <x-panel id="projections" :title="__('guide.projections.title')">
        <div class="prose prose-sm max-w-none text-body">
            <p>{!! __('guide.projections.lead') !!}</p>
            <ul>
                <li>{!! __('guide.projections.r2') !!}</li>
                <li>{!! __('guide.projections.dampened') !!}</li>
            </ul>
        </div>
    </x-panel>

    {{-- BALANCE --}}
    <x-panel id="balance" :title="__('guide.balance.title')">
        <div class="prose prose-sm max-w-none text-body">
            <p>{{ __('guide.balance.lead') }}</p>
            <ul>
                @foreach (['push_pull', 'quads_posterior', 'upper_lower'] as $group)
                    <li>{!! __('guide.balance.'.$group) !!}</li>
                @endforeach
            </ul>
            <p>{!! __('guide.balance.ratio') !!}</p>
        </div>
    </x-panel>

    <x-panel :title="__('guide.sources.title')">
        <ul class="text-xs text-muted list-disc list-inside space-y-1">
            @foreach (['schoenfeld', 'rp', 'epley', 'mifflin', 'helms', 'kouri'] as $source)
                <li>{{ __('guide.sources.'.$source) }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-faint">{{ __('guide.sources.disclaimer') }}</p>
    </x-panel>
</x-ui.page>

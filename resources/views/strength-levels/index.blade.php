<x-ui.page :title="__('app.pages.levels')" :subtitle="__('app.pages.levels_sub')" width="4xl">
    <x-slot:actions>
        <x-info :title="__('app.pages.levels')" :text="__('app.tips.strength_level')" />
    </x-slot:actions>

    <x-flash />

    {{-- Above the branches on purpose. When sex is unset the analytics layer
         must pick something and picks male, and the page then stated that
         comparison as fact. An athlete needs to see this before any percentile,
         including on the empty state where they are about to log their first
         lift. --}}
    @if ($assumedSex)
        <x-ui.insight tone="warn" :title="__('app.levels.assumed_sex')" class="mb-6">
            <p>{!! __('app.levels.assumed_sex_body', [
                'profile' => '<a href="'.route('profile.edit').'" class="underline font-medium">'.__('app.nav.profile').'</a>',
            ]) !!}</p>
        </x-ui.insight>
    @endif

    @if(! $bodyweight)
        <x-ui.card>
            <p class="text-sm text-body">Log a body weight in Hevy and set your <a href="{{ route('profile.edit') }}" class="text-brand-ink underline">age &amp; sex</a> — strength levels compare you to lifters of the same age, bodyweight and sex.</p>
        </x-ui.card>
    @elseif(empty($levels))
        <x-ui.card>
            <p class="text-sm text-body">No standard-mapped lifts found yet. Strength standards cover barbell movements like Bench, Squat, Deadlift, Overhead Press, Barbell Row and Barbell Curl. Log some and re-sync.</p>
        </x-ui.card>
    @else
        {{-- Say what the comparison assumed, not just what it concluded.

             When sex is unset the analytics layer has to pick something to
             compute with and picks male; the page then printed "compared against
             male lifters" as a statement of fact. For a woman who had not filled
             in her profile, every percentile on the page was wrong and nothing
             said so. The age factor is likewise only applied on the offline
             fallback rows, so it is stated per row rather than page-wide. --}}
        <x-ui.card class="mb-6">
            <p class="text-sm text-body">
                {{ __('app.levels.compared_against', [
                    'sex' => __('app.levels.sex_'.($sex ?? 'male')),
                    'bodyweight' => $bodyweight,
                ]) }}
                @if ($age)
                    {{ __('app.levels.aged', ['age' => $age]) }}
                @else
                    <a href="{{ route('profile.edit') }}" class="underline">{{ __('app.levels.add_age') }}</a>
                @endif
            </p>
        </x-ui.card>

        <div class="space-y-4">
            @foreach ($levels as $l)
                <x-ui.card>
                    <x-strength-bar :eval="$l" :exercise="$l['exercise']" />
                </x-ui.card>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-faint">
            Levels use a layered data source: <strong>FitnessVolt</strong> (free, CC BY 4.0 — serving verified
            <a href="https://www.openpowerlifting.org/" target="_blank" rel="noopener" class="underline">OpenPowerlifting</a>
            competition percentiles + self-reported gym percentiles), falling back to a locally-built OpenPowerlifting
            table, then an offline ratio model. The headline % is the <strong>gym</strong> population (comparable to
            general lifting apps); the verified/competition % is shown alongside. See the
            <a href="{{ route('guide') }}" class="underline">Guide</a> for details. Powered by FitnessVolt (CC BY 4.0).
        </p>
    @endif
</x-ui.page>

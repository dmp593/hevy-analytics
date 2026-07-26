<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-ink">Strength levels</h2>
            <x-info title="Strength level" text="Strength level measures your strength in this exercise compared to people at the same age, weight and sex group as you. 0% = beginner, 100% = elite. Based on Strength Level community standards (millions of lifts)." />
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        @if(! $bodyweight)
            <x-panel>
                <p class="text-sm text-body">Log a body weight in Hevy and set your <a href="{{ route('profile.edit') }}" class="text-brand-ink underline">age &amp; sex</a> — strength levels compare you to lifters of the same age, bodyweight and sex.</p>
            </x-panel>
        @elseif(empty($levels))
            <x-panel>
                <p class="text-sm text-body">No standard-mapped lifts found yet. Strength standards cover barbell movements like Bench, Squat, Deadlift, Overhead Press, Barbell Row and Barbell Curl. Log some and re-sync.</p>
            </x-panel>
        @else
            <x-panel class="mb-6">
                <p class="text-sm text-body">
                    Compared against {{ $sex }} lifters
                    @if($age) aged {{ $age }} @endif
                    at {{ $bodyweight }}kg bodyweight.
                    @if($ageFactor && $ageFactor != 1)
                        <span class="text-faint">Standards age-adjusted ×{{ $ageFactor }}.</span>
                    @endif
                </p>
            </x-panel>

            <div class="space-y-4">
                @foreach($levels as $l)
                    <x-panel>
                        <x-strength-bar :eval="$l" :exercise="$l['exercise']" />
                    </x-panel>
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
    </div>
</x-app-layout>

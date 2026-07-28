<x-ui.page :title="__('app.compare.title')" :subtitle="__('app.compare.subtitle')" width="6xl" class="space-y-6">
    <x-flash />

    {{-- Date picker: any check-in date (photo or measurement) is fair game. --}}
    <x-ui.card :title="__('app.compare.pick_title')">
        <p class="mb-3 text-xs text-muted">{{ __('app.compare.pick_hint') }}</p>

        @if ($candidates->isEmpty())
            <x-ui.empty :title="__('app.compare.none_title')">
                {{ __('app.compare.none_body') }}
            </x-ui.empty>
        @else
            <form method="GET" action="{{ route('compare') }}">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($candidates as $c)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm transition hover:bg-surface-sunk has-checked:border-brand has-checked:bg-brand-soft">
                            <input type="checkbox" name="dates[]" value="{{ $c['date'] }}" class="rounded-sm border-line"
                                   @checked($selected->contains($c['date']))>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $c['date'] }}</span>
                                <span class="block text-[11px] text-muted">
                                    @if ($c['poses'] > 0){{ trans_choice('app.compare.photos_badge', $c['poses'], ['count' => $c['poses']]) }}@endif
                                    @if ($c['poses'] > 0 && $c['measured']) · @endif
                                    @if ($c['measured']){{ __('app.compare.measured_badge') }}@endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <x-ui.button type="submit">{{ __('app.compare.show') }}</x-ui.button>
                    @if ($selected->count() === 1)
                        <p class="text-xs text-warn">{{ __('app.compare.need_two') }}</p>
                    @endif
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('dates')" />
            </form>
        @endif
    </x-ui.card>

    @if ($comparison)
        {{-- Photos, aligned by pose: one row per pose, one column per date.
             3-4 columns slide horizontally on a phone; the page never does. --}}
        @if ($comparison['poses']->isNotEmpty())
            <x-ui.card :title="__('app.compare.photos_title')">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="w-20 py-2 pr-3 text-start text-xs font-medium text-muted"></th>
                                @foreach ($comparison['columns'] as $col)
                                    <th class="py-2 pr-3 text-start text-xs font-semibold text-ink">
                                        {{ $col['date'] }}
                                        @if ($loop->first)<span class="ml-1 font-normal text-faint">{{ __('app.compare.baseline') }}</span>@endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparison['poses'] as $pose)
                                <tr>
                                    <th class="py-2 pr-3 text-start align-top text-xs font-medium text-muted">{{ __('app.photos.angle_'.$pose) }}</th>
                                    @foreach ($comparison['columns'] as $col)
                                        <td class="py-2 pr-3 align-top">
                                            @if ($photo = $col['photos']->get($pose))
                                                <img src="{{ route('photos.file', $photo) }}" loading="lazy" alt="{{ $col['date'] }} · {{ __('app.photos.angle_'.$pose) }}"
                                                     class="h-56 w-40 rounded-lg border border-line object-cover">
                                            @else
                                                <div class="flex h-56 w-40 items-center justify-center rounded-lg border border-dashed border-line text-[11px] text-faint">
                                                    {{ __('app.compare.no_photo') }}
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        {{-- Values: change is measured against the oldest selected date. --}}
        @if (count($comparison['rows']))
            <x-ui.card :title="__('app.compare.values_title')">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="table-head">
                                <th class="py-2 pl-1 pr-4 text-start"></th>
                                @foreach ($comparison['columns'] as $col)
                                    <th class="whitespace-nowrap py-2 pr-4 text-start">
                                        {{ $col['date'] }}
                                        @if ($loop->first)<span class="ml-1 text-[11px] font-normal text-faint">{{ __('app.compare.baseline') }}</span>@endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comparison['rows'] as $row)
                                <tr class="table-row">
                                    <th class="whitespace-nowrap py-2 pl-1 pr-4 text-start text-xs font-medium text-muted">{{ $row['label'] }} <span class="text-faint">({{ $row['unit'] }})</span></th>
                                    @foreach ($row['cells'] as $cell)
                                        <td class="whitespace-nowrap py-2 pr-4 tabular-nums">
                                            @if ($cell['display'] === null)
                                                <span class="text-faint">—</span>
                                            @else
                                                <span class="font-semibold text-ink">{{ $cell['display'] }}</span>
                                                @if ($cell['delta'] !== null)
                                                    <span @class([
                                                        'ml-1 text-xs font-medium',
                                                        'text-good' => $cell['tone'] === 'good',
                                                        'text-warn' => $cell['tone'] === 'warn',
                                                        'text-bad' => $cell['tone'] === 'bad',
                                                        'text-muted' => $cell['tone'] === 'neutral',
                                                    ])>{{ $cell['arrow'] }} {{ $cell['delta'] }}</span>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-3 text-xs text-muted">
                    @if ($comparison['goal_direction'])
                        {{ __('app.compare.vs_goal', ['goal' => \App\Science\Goals\GoalType::label($comparison['goal_type'])]) }}
                    @else
                        {{ __('app.compare.vs_no_goal') }}
                    @endif
                </p>
            </x-ui.card>
        @endif
    @endif
</x-ui.page>

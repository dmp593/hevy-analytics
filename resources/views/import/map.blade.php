<x-ui.page :title="__('app.import.map.title')" :subtitle="__('app.import.map.subtitle')" width="3xl" class="space-y-6">
    <x-flash />

    <x-ui.card :title="__('app.import.map.preview')">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="table-head">
                        @foreach ($headers as $h)
                            <th class="whitespace-nowrap py-2 pr-4 pl-1 text-start">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview as $row)
                        <tr class="table-row">
                            @foreach ($headers as $i => $h)
                                <td class="whitespace-nowrap py-1.5 pr-4 pl-1 text-muted">{{ $row[$i] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('app.import.map.title')">
        <form method="POST" action="{{ route('import.map') }}" class="space-y-4">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (\App\Services\Import\CsvImport::MAPPABLE as $field)
                    <label class="text-xs text-body block">
                        {{ __('app.import.map.field_'.$field) }}
                        @if (in_array($field, ['start_time', 'exercise_title'], true))
                            <span class="text-bad">*</span>
                        @endif
                        <select name="map[{{ $field }}]" class="mt-1 block w-full rounded-md border-line text-sm">
                            <option value="">{{ __('app.import.map.none') }}</option>
                            @foreach ($headers as $i => $h)
                                <option value="{{ $i }}" @selected(($guess[$field] ?? null) === $i)>{{ $h }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>

            <div>
                <label class="text-xs text-body block">{{ __('app.import.unit_label') }}
                    <select name="unit" class="mt-1 block w-40 rounded-md border-line text-sm">
                        <option value="kg" @selected($unit === 'kg')>kg</option>
                        <option value="lbs" @selected($unit === 'lbs')>lb</option>
                    </select>
                </label>
            </div>

            <p class="text-xs text-muted">{{ __('app.import.map.required') }}</p>

            <x-input-error class="mt-2" :messages="$errors->all()" />

            <x-ui.button type="submit">{{ __('app.import.map.submit') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-ui.page>

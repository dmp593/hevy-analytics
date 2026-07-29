<x-ui.card :title="__('app.calendar.title')" :subtitle="__('app.calendar.subtitle')">
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Http\Controllers\CalendarStyleController::STYLES as $style)
            <form method="POST" action="{{ route('settings.calendar', $style) }}">
                @csrf
                <button class="min-h-11 rounded-md border px-3 text-xs font-semibold transition {{ auth()->user()->calendar_style === $style ? 'border-brand bg-brand-soft text-brand-ink' : 'border-line text-body hover:bg-surface-sunk' }}"
                        @if (auth()->user()->calendar_style === $style) aria-pressed="true" @endif>
                    {{ __('app.calendar.'.$style) }}
                </button>
            </form>
        @endforeach
    </div>
</x-ui.card>

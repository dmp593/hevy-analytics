<x-ui.card :title="__('app.emails.title')" :subtitle="__('app.emails.subtitle')">
    <form method="POST" action="{{ route('profile.emails') }}">
        @csrf
        <label class="flex items-start gap-3 text-sm text-body">
            {{-- The hidden field makes an unticked box an explicit false. --}}
            <input type="hidden" name="weekly_email" value="0">
            <input type="checkbox" name="weekly_email" value="1" @checked($user->weekly_email)
                   class="mt-0.5 rounded border-line">
            <span>
                <span class="font-medium text-ink">{{ __('app.emails.weekly') }}</span><br>
                <span class="text-xs text-muted">{{ __('app.emails.weekly_sub') }}</span>
            </span>
        </label>

        <x-ui.button type="submit" class="mt-4">{{ __('app.common.save') }}</x-ui.button>
    </form>
</x-ui.card>

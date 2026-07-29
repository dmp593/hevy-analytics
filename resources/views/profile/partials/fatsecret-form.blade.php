@if (\App\Services\FatSecret\FatSecretClient::configured())
    <x-ui.card :title="__('app.fatsecret.title')" :subtitle="__('app.fatsecret.subtitle')">
        @if ($user->fatsecret_linked_at)
            <p class="text-sm text-body">
                {{ __('app.fatsecret.linked_since', ['date' => $user->fatsecret_linked_at->toDateString()]) }}
                @if ($user->fatsecret_synced_at)
                    · {{ __('app.fatsecret.last_sync', ['when' => $user->fatsecret_synced_at->diffForHumans()]) }}
                @endif
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('fatsecret.sync') }}">
                    @csrf
                    <x-ui.button type="submit">{{ __('app.fatsecret.sync_now') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('fatsecret.disconnect') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">{{ __('app.fatsecret.disconnect') }}</x-ui.button>
                </form>
            </div>
        @else
            <p class="text-sm text-body">{{ __('app.fatsecret.pitch') }}</p>

            <form method="POST" action="{{ route('fatsecret.connect') }}" class="mt-4">
                @csrf
                <x-ui.button type="submit">{{ __('app.fatsecret.connect') }}</x-ui.button>
            </form>
            <p class="mt-2 text-xs text-muted">{{ __('app.fatsecret.privacy') }}</p>
        @endif
    </x-ui.card>
@endif

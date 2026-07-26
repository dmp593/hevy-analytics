@php
    $providers = \App\Services\AI\ProviderRegistry::all();
    $active = $user->aiCredentials()->where('is_active', true)->first();
    $current = old('provider', $active?->provider ?? \App\Services\AI\ProviderRegistry::OPENAI);
@endphp

<x-ui.card :title="__('app.settings.ai.title')" :subtitle="__('app.settings.ai.subtitle')">
    {{-- Alpine holds the provider metadata so the form can reshape itself
         without a round trip: only the "other, OpenAI-compatible" option needs
         an endpoint field, and showing it for OpenAI invites someone to type
         something that will be ignored. --}}
    <div x-data="{
            providers: @js(collect($providers)->map(fn ($p) => [
                'label' => $p['label'],
                'base_url' => $p['base_url'],
                'editable' => $p['base_url_editable'],
                'models' => $p['models'],
                'hint' => $p['key_hint'],
                'docs' => $p['docs'],
            ])),
            provider: @js($current),
            get meta() { return this.providers[this.provider] ?? {} },
         }">

        @if ($active)
            <div class="mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-line bg-surface-sunk px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-ink">
                        {{ __('app.settings.ai.using', ['provider' => $active->providerLabel()]) }}
                        <span class="font-mono text-xs text-muted">{{ $active->maskedKey() }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-muted">
                        {{ $active->model }}
                        @if ($active->last_verified_at)
                            · <span class="text-good">{{ __('app.settings.ai.verified', ['when' => $active->last_verified_at->diffForHumans()]) }}</span>
                        @elseif ($active->last_error)
                            · <span class="text-bad">{{ __($active->last_error) }}</span>
                        @else
                            · <span class="text-muted">{{ __('app.settings.ai.not_yet_used') }}</span>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.ai.destroy', $active->provider) }}">
                    @csrf
                    @method('delete')
                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('app.settings.ai.remove') }}</x-ui.button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.ai.update') }}" class="space-y-4">
            @csrf
            @method('put')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="ai_provider" :value="__('app.settings.ai.provider')" />
                    <select id="ai_provider" name="provider" x-model="provider" class="form-control">
                        @foreach ($providers as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('provider')" />
                </div>

                <div>
                    <x-input-label for="ai_model" :value="__('app.settings.ai.model')" />
                    <input id="ai_model" name="model" type="text" class="form-control" list="ai-models"
                           value="{{ old('model', $active?->model) }}"
                           x-bind:placeholder="meta.models?.[0] ?? ''" required>
                    {{-- A datalist, not a select: providers ship new models
                         constantly and an athlete should not have to wait for a
                         release here to use one. --}}
                    <datalist id="ai-models">
                        <template x-for="m in (meta.models ?? [])" :key="m">
                            <option x-bind:value="m"></option>
                        </template>
                    </datalist>
                    <x-input-error class="mt-2" :messages="$errors->get('model')" />
                </div>
            </div>

            <div>
                <x-input-label for="ai_key" :value="__('app.settings.ai.key')" />
                <input id="ai_key" name="api_key" type="password" class="form-control" autocomplete="off"
                       x-bind:placeholder="'{{ $active ? __('app.settings.ai.key_kept') : '' }}' || meta.hint || ''">
                <p class="mt-1 text-xs text-muted">
                    {{ __('app.settings.ai.key_help') }}
                    <template x-if="meta.docs">
                        <a x-bind:href="meta.docs" target="_blank" rel="noopener" class="underline" x-text="'{{ __('app.settings.ai.where') }}'"></a>
                    </template>
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('api_key')" />
            </div>

            <div x-show="meta.editable" x-cloak>
                <x-input-label for="ai_base_url" :value="__('app.settings.ai.endpoint')" />
                <input id="ai_base_url" name="base_url" type="url" class="form-control"
                       value="{{ old('base_url', $active?->base_url) }}" placeholder="https://…/v1">
                <p class="mt-1 text-xs text-muted">{{ __('app.settings.ai.endpoint_help') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('base_url')" />
            </div>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('app.common.save') }}</x-ui.button>
                <p class="text-xs text-muted">{{ __('app.settings.ai.no_limit') }}</p>
            </div>
        </form>
    </div>
</x-ui.card>

<x-ui.card :title="__('app.profile.title')" :subtitle="__('app.profile.subtitle')">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('app.auth.name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('app.auth.email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink">
                        {{ __('app.auth.unverified') }}

                        <button form="send-verification" class="underline text-sm text-body hover:text-ink rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                            {{ __('app.auth.unverified_resend') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-good">
                            {{ __('app.auth.verification_sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-subtle pt-6">
            <h3 class="text-sm font-semibold text-ink mb-1">{{ __('app.profile.hevy_section') }}</h3>
            <p class="text-xs text-muted mb-4">{{ __('app.profile.hevy_section_help') }}</p>

            <div>
                <x-input-label for="hevy_api_key" :value="__('app.profile.hevy_key')" />
                <x-text-input id="hevy_api_key" name="hevy_api_key" type="password" class="mt-1 block w-full"
                              :placeholder="$user->hasHevyKey() ? __('app.profile.hevy_key_set') : __('app.profile.hevy_key_placeholder')" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('hevy_api_key')" />
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                <div>
                    <x-input-label for="sex" :value="__('app.profile.sex')" />
                    <select id="sex" name="sex" class="mt-1 block w-full rounded-md border-line text-sm">
                        <option value="">—</option>
                        @foreach (['male', 'female'] as $sex)
                            <option value="{{ $sex }}" @selected(old('sex', $user->sex) === $sex)>{{ __('app.profile.sex_'.$sex) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="age" :value="__('app.profile.age')" />
                    <x-text-input id="age" name="age" type="number" class="mt-1 block w-full" :value="old('age', $user->age)" />
                </div>
                <div>
                    <x-input-label for="height_cm" :value="__('app.profile.height')" />
                    <x-text-input id="height_cm" name="height_cm" type="number" step="0.1" class="mt-1 block w-full" :value="old('height_cm', $user->height_cm)" />
                </div>
                <div>
                    <x-input-label for="activity_level" :value="__('app.profile.activity')" />
                    <select id="activity_level" name="activity_level" class="mt-1 block w-full rounded-md border-line text-sm">
                        @foreach(['1.2' => 'sedentary', '1.375' => 'light', '1.55' => 'moderate', '1.725' => 'very', '1.9' => 'extreme'] as $val => $key)
                            <option value="{{ $val }}" @selected((string) old('activity_level', $user->activity_level) === $val)>{{ __('app.profile.activity_'.$key) }} ({{ $val }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="body_fat_source" :value="__('app.profile.body_fat_source')" />
                <select id="body_fat_source" name="body_fat_source" class="mt-1 block w-full md:w-1/2 rounded-md border-line text-sm">
                    @foreach (['scale', 'navy', 'manual'] as $source)
                        <option value="{{ $source }}" @selected(old('body_fat_source', $user->body_fat_source) === $source)>{{ __('app.profile.body_fat_'.$source) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">{{ __('app.profile.body_fat_help') }}</p>
            </div>

            <div class="mt-4">
                <x-input-label for="locale" :value="__('app.profile.language')" />
                <select id="locale" name="locale" class="mt-1 block w-full md:w-1/2 rounded-md border-line text-sm">
                    <option value="">{{ __('app.profile.follow_browser') }}</option>
                    @foreach(\App\Support\Locales::supported() as $code => $meta)
                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $meta['native'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">{{ __('app.profile.language_help') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('locale')" />
            </div>

            <div class="mt-4">
                <x-input-label for="timezone" :value="__('app.profile.timezone')" />
                <select id="timezone" name="timezone" class="mt-1 block w-full md:w-1/2 rounded-md border-line text-sm">
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" @selected(old('timezone', $user->resolvedTimezone()) === $tz)>{{ str_replace('_', ' ', $tz) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">{{ __('app.profile.timezone_help') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('app.common.save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-body"
                >{{ __('app.common.saved') }}</p>
            @endif
        </div>
    </form>
</x-ui.card>

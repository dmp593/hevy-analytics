<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-gray-100 pt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Hevy &amp; athlete profile</h3>
            <p class="text-xs text-gray-500 mb-4">Your Hevy API key is stored encrypted. Height, age &amp; sex power FFMI, BMR and macro targets.</p>

            <div>
                <x-input-label for="hevy_api_key" :value="__('Hevy API key')" />
                <x-text-input id="hevy_api_key" name="hevy_api_key" type="password" class="mt-1 block w-full"
                              :placeholder="$user->hasHevyKey() ? '•••••••• (leave blank to keep current)' : 'Paste your Hevy API key'" autocomplete="off" />
                <x-input-error class="mt-2" :messages="$errors->get('hevy_api_key')" />
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                <div>
                    <x-input-label for="sex" :value="__('Sex')" />
                    <select id="sex" name="sex" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">—</option>
                        <option value="male" @selected(old('sex', $user->sex) === 'male')>Male</option>
                        <option value="female" @selected(old('sex', $user->sex) === 'female')>Female</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="age" :value="__('Age')" />
                    <x-text-input id="age" name="age" type="number" class="mt-1 block w-full" :value="old('age', $user->age)" />
                </div>
                <div>
                    <x-input-label for="height_cm" :value="__('Height (cm)')" />
                    <x-text-input id="height_cm" name="height_cm" type="number" step="0.1" class="mt-1 block w-full" :value="old('height_cm', $user->height_cm)" />
                </div>
                <div>
                    <x-input-label for="activity_level" :value="__('Activity (PAL)')" />
                    <select id="activity_level" name="activity_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @foreach(['1.2'=>'Sedentary','1.375'=>'Light','1.55'=>'Moderate','1.725'=>'Very active','1.9'=>'Extreme'] as $val => $lbl)
                            <option value="{{ $val }}" @selected((string) old('activity_level', $user->activity_level) === $val)>{{ $lbl }} ({{ $val }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="body_fat_source" :value="__('Body-fat source')" />
                <select id="body_fat_source" name="body_fat_source" class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 text-sm">
                    <option value="scale" @selected(old('body_fat_source', $user->body_fat_source) === 'scale')>Scale (BIA) — from your smart scale</option>
                    <option value="navy" @selected(old('body_fat_source', $user->body_fat_source) === 'navy')>Navy tape method — from neck/waist/height</option>
                    <option value="manual" @selected(old('body_fat_source', $user->body_fat_source) === 'manual')>Manual — the body-fat % you log yourself</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Drives lean-mass, FFMI and partitioning. BIA scales are noisy day-to-day; the Navy tape method or a manual estimate are often steadier for tracking trends.</p>
            </div>

            <div class="mt-4">
                <x-input-label for="timezone" :value="__('Timezone')" />
                <select id="timezone" name="timezone" class="mt-1 block w-full md:w-1/2 rounded-md border-gray-300 text-sm">
                    @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" @selected(old('timezone', $user->resolvedTimezone()) === $tz)>{{ str_replace('_', ' ', $tz) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Decides which day and week each session counts toward. Get this wrong and an evening workout can land in the previous week, skewing your sets-per-week figures.</p>
                <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>

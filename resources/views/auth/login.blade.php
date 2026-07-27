<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('app.auth.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('app.auth.password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex min-h-11 items-center">
                <input id="remember_me" type="checkbox" class="h-5 w-5 rounded-sm border-line text-brand-ink shadow-xs focus:ring-brand" name="remember">
                <span class="ms-2 text-sm text-body">{{ __('app.auth.remember_me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="inline-flex min-h-11 items-center underline text-sm text-body hover:text-ink rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand" href="{{ route('password.request') }}">
                    {{ __('app.auth.forgot_password') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('app.auth.log_in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

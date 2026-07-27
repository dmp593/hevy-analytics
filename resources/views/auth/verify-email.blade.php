<x-guest-layout>
    <div class="mb-4 text-sm text-body">
        {{ __('app.auth.verify_intro') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-good">
            {{ __('app.auth.verify_sent') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('app.auth.verify_resend') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="inline-flex min-h-11 items-center underline text-sm text-body hover:text-ink rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                {{ __('app.auth.log_out') }}
            </button>
        </form>
    </div>
</x-guest-layout>

<x-ui.card :title="__('app.profile.delete_button')" :subtitle="__('app.profile.delete_help')">
    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('app.profile.delete_button') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-ink">
                {{ __('app.profile.delete_confirm_title') }}
            </h2>

            <p class="mt-1 text-sm text-body">
                {{ __('app.profile.delete_confirm_help') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('app.auth.password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('app.auth.password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('app.profile.cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('app.profile.delete_button') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</x-ui.card>

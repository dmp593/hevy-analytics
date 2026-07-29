<x-ui.page :title="__('app.pages.profile')" width="4xl" class="space-y-6">
    {{-- Without this, the demo middleware's "read-only" bounce lands here with
         its explanation in a flash nothing renders — a silent refusal. --}}
    <x-flash />

    @include('profile.partials.update-profile-information-form')

    @include('profile.partials.ai-provider-form')

    @include('profile.partials.fatsecret-form')

    @include('profile.partials.emails-form')

    @include('profile.partials.data-privacy')

    @include('profile.partials.update-password-form')

    @include('profile.partials.delete-user-form')
</x-ui.page>

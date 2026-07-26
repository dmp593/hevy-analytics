<x-ui.page :title="__('app.pages.profile')" width="4xl" class="space-y-6">
    @include('profile.partials.update-profile-information-form')

    @include('profile.partials.update-password-form')

    @include('profile.partials.delete-user-form')
</x-ui.page>

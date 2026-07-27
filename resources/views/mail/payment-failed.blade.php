<x-mail::message>
# {{ __('app.mail.payment_failed.heading', ['name' => $user->name]) }}

{{ __('app.mail.payment_failed.body') }}

{{ __('app.mail.payment_failed.no_cutoff') }}

<x-mail::button :url="route('billing')">
{{ __('app.mail.payment_failed.cta') }}
</x-mail::button>

{{ __('app.mail.signoff') }}<br>
{{ config('app.name') }}
</x-mail::message>

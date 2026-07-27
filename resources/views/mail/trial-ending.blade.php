<x-mail::message :preheader="__('app.mail.trial_ending.preheader')">
# {{ __('app.mail.trial_ending.heading', ['name' => $user->name]) }}

{{ __('app.mail.trial_ending.body', ['date' => $endsAt->isoFormat('D MMMM')]) }}

{{ __('app.mail.trial_ending.what_changes', ['days' => $freeDays]) }}

<x-mail::button :url="route('billing')">
{{ __('app.mail.trial_ending.cta', ['price' => $price]) }}
</x-mail::button>

{{ __('app.mail.trial_ending.no_pressure') }}

{{ __('app.mail.signoff') }}<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message :preheader="__('app.mail.weekly.preheader')">
# {{ __('app.mail.weekly.heading', ['name' => $user->name]) }}

@if ($trendWeight !== null)
{{ __('app.mail.weekly.weight_line', [
    'weight' => $units->weight($trendWeight).' '.$units->weightUnit(),
    'rate' => isset($rate['kg_per_week'])
        ? sprintf('%+.2f %s', $units->weight($rate['kg_per_week'], 2), $units->weightUnit().'/'.__('app.mail.weekly.week_abbr'))
        : __('app.mail.weekly.no_rate'),
]) }}
@endif

@if ($consistency)
{{ __('app.mail.weekly.consistency_line', [
    'sessions' => $consistency['sessions_this_week'],
    'avg' => $consistency['avg_per_week'],
    'streak' => $consistency['streak_weeks'],
]) }}
@endif

@foreach ($alerts as $alert)
**{{ $alert['title'] }}** — {{ $alert['message'] }}
@endforeach

<x-mail::button :url="route('dashboard')">
{{ __('app.mail.weekly.cta') }}
</x-mail::button>

{{ __('app.mail.weekly.opt_out', ['url' => route('profile.edit')]) }}

{{ __('app.mail.signoff') }}<br>
{{ config('app.name') }}
</x-mail::message>

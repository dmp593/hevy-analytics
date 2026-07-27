<x-mail::layout :preheader="$preheader ?? null">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer.

     Says WHY this arrived and where it came from. "All rights reserved" tells
     the reader nothing; a line explaining that this is an account email, not
     marketing, is what stops it being marked as spam. --}}
<x-slot:footer>
<x-mail::footer>
{{ __('app.mail.footer_reason') }}<br>
<a href="{{ config('app.url') }}" style="color: #6b7280; text-decoration: underline;">{{ config('app.name') }}</a>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>

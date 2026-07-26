@props(['title' => null, 'subtitle' => null])

{{-- Deprecated: use <x-ui.card> directly in new markup.

     This delegates rather than duplicating, so the seventy-odd existing call
     sites pick up the card's semantics — a real <section>/<header>, and a
     heading at <h2> under the page's <h1> instead of a stray <h3> with nothing
     above it — without a mechanical rewrite of twelve views. --}}
<x-ui.card :title="$title" :subtitle="$subtitle" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    {{ $slot }}
</x-ui.card>

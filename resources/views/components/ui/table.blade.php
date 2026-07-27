@props([
    'headers' => [],
    'align' => [],
])

{{-- A table that scrolls inside its own box.

     The three hand-built tables in this app repeated 28 cell class strings
     between them and had no responsive behaviour, so a wide table pushed the
     whole document sideways. `align` takes 'right' per column index for numbers,
     which should never be left-aligned against text. --}}
<div {{ $attributes->merge(['class' => '-mx-5 overflow-x-auto px-5']) }}>
    <table class="w-full min-w-full text-sm">
        @if (count($headers))
            <thead>
                <tr>
                    @foreach ($headers as $i => $header)
                        <th scope="col"
                            class="whitespace-nowrap border-b border-line px-3 py-2 text-xs font-medium uppercase tracking-wide text-muted {{ ($align[$i] ?? 'left') === 'right' ? 'text-right' : 'text-left' }}">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>

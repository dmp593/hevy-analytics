@props(['align' => 'left', 'numeric' => false, 'strong' => false])

<td {{ $attributes->merge([
    'class' => 'px-3 py-2.5 '
        .($align === 'right' ? 'text-right ' : '')
        .($numeric ? 'tabular-nums ' : '')
        .($strong ? 'font-medium text-ink' : 'text-body'),
]) }}>
    {{ $slot }}
</td>

@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-brand focus:ring-brand rounded-md shadow-xs']) }}>

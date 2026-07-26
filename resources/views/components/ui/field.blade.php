@props([
    'label',
    'name',
    'help' => null,
    'required' => false,
])

{{-- Label, control, help text and error in one place, wired together by id so
     the label is actually associated with the input and the error is announced. --}}
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="block text-xs font-medium text-muted">
        {{ $label }}
        @if ($required)
            <span class="text-bad" aria-hidden="true">*</span>
        @endif
    </label>

    <div class="mt-1">
        {{ $slot }}
    </div>

    @if ($help)
        <p id="{{ $name }}-help" class="mt-1 text-xs text-muted">{{ $help }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs font-medium text-bad" role="alert">{{ $message }}</p>
    @enderror
</div>

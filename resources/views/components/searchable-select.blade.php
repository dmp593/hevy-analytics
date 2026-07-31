@props(['name', 'options', 'selected' => null, 'allLabel' => null, 'form' => null, 'submit' => false, 'label' => null])

{{-- A combobox for selects too long to scan: type to filter, click to pick.
     Plain Alpine, no library; the hidden input keeps normal form semantics
     (including belonging to a form elsewhere via the form attribute). --}}
@php
    $items = collect($options)
        ->map(fn ($o) => ['value' => (string) $o['value'], 'label' => (string) $o['label']])
        ->values();
    $current = optional($items->firstWhere('value', (string) $selected))['label'] ?? '';
@endphp

<div x-data="{
        open: false,
        query: '',
        value: @js((string) ($selected ?? '')),
        label: @js($current),
        items: @js($items->all()),
        get filtered() {
            const q = this.query.trim().toLowerCase();
            return q === '' ? this.items : this.items.filter(i => i.label.toLowerCase().includes(q));
        },
        choose(item) {
            this.value = item ? item.value : '';
            this.label = item ? item.label : '';
            this.query = '';
            this.open = false;
            @if ($submit) this.$nextTick(() => this.$refs.value.form?.requestSubmit()); @endif
        },
    }"
    @click.outside="open = false" @keydown.escape="open = false"
    {{ $attributes->merge(['class' => 'relative']) }}>

    <input type="hidden" name="{{ $name }}" :value="value" x-ref="value" @if ($form) form="{{ $form }}" @endif>

    <input type="text" x-model="query" @focus="open = true" @click="open = true"
           :placeholder="label || @js($allLabel ?? '')"
           role="combobox" :aria-expanded="open" aria-label="{{ $label ?? $name }}"
           autocomplete="off" class="form-control mt-0 text-xs">

    <div x-show="open" x-cloak
         class="absolute z-30 mt-1 max-h-64 w-full min-w-48 overflow-auto rounded-lg border border-line bg-surface py-1 shadow-xl">
        @if ($allLabel)
            <button type="button" @click="choose(null)"
                    class="block w-full px-3 py-2 text-left text-sm text-body hover:bg-surface-sunk"
                    :class="value === '' && 'font-semibold text-brand-ink'">{{ $allLabel }}</button>
        @endif
        <template x-for="item in filtered" :key="item.value">
            <button type="button" @click="choose(item)"
                    class="block w-full px-3 py-2 text-left text-sm text-body hover:bg-surface-sunk"
                    :class="item.value === value && 'font-semibold text-brand-ink'"
                    x-text="item.label"></button>
        </template>
        <p x-show="filtered.length === 0" class="px-3 py-2 text-xs text-muted">{{ __('app.common.no_matches') }}</p>
    </div>
</div>

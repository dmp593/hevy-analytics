@props(['text' => '', 'title' => null, 'anchor' => null])

<span x-data="{
        open: false,
        timer: null,
        show() { clearTimeout(this.timer); this.open = true; this.$nextTick(() => this.place()); },
        hide() { this.timer = setTimeout(() => this.open = false, 200); },
        {{-- Measured on OPEN, not on init: a display:none panel reports a
             zero rect, so any decision made before it is visible anchors
             the wrong side and right-column tooltips spill off-screen. --}}
        place() {
            const el = this.$refs.panel;
            if (! el) return;
            el.classList.remove('left-0', 'right-0');
            el.classList.add('left-1/2', '-translate-x-1/2');
            const box = el.getBoundingClientRect();
            if (box.right > window.innerWidth - 8) {
                el.classList.remove('left-1/2', '-translate-x-1/2');
                el.classList.add('right-0');
            } else if (box.left < 8) {
                el.classList.remove('left-1/2', '-translate-x-1/2');
                el.classList.add('left-0');
            }
        },
    }"
    class="relative inline-flex items-center align-middle">
    <button type="button"
            @mouseenter="show()" @mouseleave="hide()"
            @focus="show()" @blur="hide()" @click.prevent="open ? open = false : show()"
            @keydown.escape.window="open = false"
            :aria-expanded="open"
            {{-- The circle stays visually small; the HIT AREA does not. Padding
                 with negative margins grows the touch target to 44px without
                 moving a pixel of layout — the old 16×16 button was unhittable
                 with a thumb, and it sits next to every metric in the app. --}}
            {{-- -ml-2 = the old 4px visual gap (padding 12px − 8px), because
                 margin-left outranks the -m-3 shorthand. Plain ml-1 here made
                 the dot drift 12px away from its label. --}}
            class="-m-3 -ml-2 inline-flex items-center justify-center p-3 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand"
            aria-label="{{ __('app.common.more_info') }}">
        <span class="pointer-events-none inline-flex h-5 w-5 items-center justify-center rounded-full bg-surface-sunk text-[11px] font-bold leading-none text-body">i</span>
    </button>
    {{-- A centred, fixed-width panel hanging off a card at the edge of the grid
         pushed the document wider than the viewport, so every page scrolled
         sideways. Clamping the width and letting it flip sides keeps it inside. --}}
    <span x-show="open" x-cloak x-transition.opacity
          role="tooltip" x-ref="panel"
          @mouseenter="show()" @mouseleave="hide()"
          class="absolute z-30 bottom-full left-1/2 -translate-x-1/2 pb-2 w-[min(16rem,calc(100vw-2rem))] max-w-[16rem] text-left font-normal normal-case">
        <span class="block rounded-lg bg-ink text-canvas text-xs leading-relaxed p-3 shadow-xl">
            @if($title)<span class="block font-semibold mb-1">{{ $title }}</span>@endif
            {{ $text }}
            {{-- Deep-link to the metric's own guide section: landing on the
                 top of a long guide page is a search, not an answer. --}}
            <a href="{{ route('guide') }}{{ $anchor ? '#'.$anchor : '' }}" class="block mt-2 text-brand-ink underline">{{ __('app.common.learn_more') }}</a>
        </span>
    </span>
</span>

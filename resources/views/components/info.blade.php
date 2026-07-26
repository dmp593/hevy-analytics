@props(['text' => '', 'title' => null])

<span x-data="{
        open: false,
        timer: null,
        show() { clearTimeout(this.timer); this.open = true; },
        hide() { this.timer = setTimeout(() => this.open = false, 200); },
    }"
    class="relative inline-flex items-center align-middle">
    <button type="button"
            @mouseenter="show()" @mouseleave="hide()"
            @focus="show()" @blur="hide()" @click.prevent="open = !open"
            @keydown.escape.window="open = false"
            :aria-expanded="open"
            class="ml-1 h-4 w-4 inline-flex items-center justify-center rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold leading-none hover:bg-gray-300 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
            aria-label="{{ __('app.common.more_info') }}">i</button>
    {{-- A centred, fixed-width panel hanging off a card at the edge of the grid
         pushed the document wider than the viewport, so every page scrolled
         sideways. Clamping the width and letting it flip sides keeps it inside. --}}
    <span x-show="open" x-cloak x-transition.opacity
          role="tooltip"
          @mouseenter="show()" @mouseleave="hide()"
          class="absolute z-30 bottom-full left-1/2 -translate-x-1/2 pb-2 w-[min(16rem,calc(100vw-2rem))] max-w-[16rem] text-left font-normal normal-case"
          x-init="$nextTick(() => {
              const box = $el.getBoundingClientRect();
              if (box.right > window.innerWidth - 8) {
                  $el.classList.remove('left-1/2', '-translate-x-1/2');
                  $el.classList.add('right-0');
              } else if (box.left < 8) {
                  $el.classList.remove('left-1/2', '-translate-x-1/2');
                  $el.classList.add('left-0');
              }
          })">
        <span class="block rounded-lg bg-gray-900 text-white text-xs leading-relaxed p-3 shadow-xl">
            @if($title)<span class="block font-semibold mb-1">{{ $title }}</span>@endif
            {{ $text }}
            <a href="{{ route('guide') }}" class="block mt-2 text-indigo-300 underline">{{ __('app.common.learn_more') }}</a>
        </span>
    </span>
</span>

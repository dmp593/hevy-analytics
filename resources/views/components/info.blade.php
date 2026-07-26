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
            class="ml-1 h-4 w-4 inline-flex items-center justify-center rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold leading-none hover:bg-gray-300 focus:outline-none"
            aria-label="More info">i</button>
    <span x-show="open" x-cloak x-transition.opacity
          @mouseenter="show()" @mouseleave="hide()"
          class="absolute z-30 bottom-full left-1/2 -translate-x-1/2 pb-2 w-64 text-left font-normal normal-case">
        <span class="block rounded-lg bg-gray-900 text-white text-xs leading-relaxed p-3 shadow-xl">
            @if($title)<span class="block font-semibold mb-1">{{ $title }}</span>@endif
            {{ $text }}
            <a href="{{ route('guide') }}" class="block mt-2 text-indigo-300 underline">Learn more →</a>
        </span>
    </span>
</span>

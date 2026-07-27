<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center px-4 py-2 bg-bad border border-transparent rounded-md font-semibold text-xs text-on-fill uppercase tracking-widest hover:bg-bad active:bg-bad focus:outline-hidden focus:ring-2 focus:ring-bad focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>

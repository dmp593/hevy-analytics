<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-ink border border-transparent rounded-md font-semibold text-xs text-canvas uppercase tracking-widest hover:opacity-90 focus:opacity-90 active:bg-ink focus:outline-hidden focus:ring-2 focus:ring-brand focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>

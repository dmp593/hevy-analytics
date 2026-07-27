{{-- The simple paginator, on the same tokens and lang keys as the full one. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('app.pagination.nav') }}" class="flex items-center justify-between gap-2">
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-11 cursor-default items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-faint">
                {{ __('app.pagination.previous') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-body transition hover:bg-surface-sunk">
                {{ __('app.pagination.previous') }}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-body transition hover:bg-surface-sunk">
                {{ __('app.pagination.next') }}
            </a>
        @else
            <span class="inline-flex min-h-11 cursor-default items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-faint">
                {{ __('app.pagination.next') }}
            </span>
        @endif
    </nav>
@endif

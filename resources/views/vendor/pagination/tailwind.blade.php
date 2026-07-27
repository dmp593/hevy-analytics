{{--
    Laravel's default paginator, rewritten for this app: semantic colour tokens
    instead of the stock grey palette (which stayed light in dark mode), lang
    keys instead of hardcoded English, and 40px+ touch targets.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('app.pagination.nav') }}" class="flex items-center justify-between">
        <div class="flex flex-1 justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex min-h-11 cursor-default items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-faint">
                    {{ __('app.pagination.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-body hover:bg-surface-sunk">
                    {{ __('app.pagination.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="ms-3 inline-flex min-h-11 items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-body hover:bg-surface-sunk">
                    {{ __('app.pagination.next') }}
                </a>
            @else
                <span class="ms-3 inline-flex min-h-11 cursor-default items-center rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-faint">
                    {{ __('app.pagination.next') }}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-muted">
                {{ __('app.pagination.showing', [
                    'first' => number_format($paginator->firstItem() ?? 0),
                    'last' => number_format($paginator->lastItem() ?? 0),
                    'total' => number_format($paginator->total()),
                ]) }}
            </p>

            <span class="relative z-0 inline-flex rounded-md shadow-xs rtl:flex-row-reverse">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('app.pagination.previous') }}" class="inline-flex min-h-10 cursor-default items-center rounded-s-md border border-line bg-surface px-2 py-2 text-faint">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" /></svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('app.pagination.previous') }}" class="inline-flex min-h-10 items-center rounded-s-md border border-line bg-surface px-2 py-2 text-muted hover:bg-surface-sunk focus:z-10">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd" /></svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="-ms-px inline-flex min-h-10 cursor-default items-center border border-line bg-surface px-4 py-2 text-sm font-medium text-faint">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="-ms-px inline-flex min-h-10 cursor-default items-center border border-brand bg-brand-soft px-4 py-2 text-sm font-semibold text-brand-ink">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('app.pagination.goto', ['page' => $page]) }}" class="-ms-px inline-flex min-h-10 items-center border border-line bg-surface px-4 py-2 text-sm font-medium text-body hover:bg-surface-sunk focus:z-10">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('app.pagination.next') }}" class="-ms-px inline-flex min-h-10 items-center rounded-e-md border border-line bg-surface px-2 py-2 text-muted hover:bg-surface-sunk focus:z-10">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('app.pagination.next') }}" class="-ms-px inline-flex min-h-10 cursor-default items-center rounded-e-md border border-line bg-surface px-2 py-2 text-faint">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" /></svg>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif

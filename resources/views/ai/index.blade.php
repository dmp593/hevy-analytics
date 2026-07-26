<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-ink">AI deep analysis</h2>
            <form method="POST" action="{{ route('ai.generate') }}">
                @csrf
                <input type="hidden" name="force" value="1">
                <button class="text-xs rounded-md bg-brand text-on-fill px-3 py-1.5 hover:bg-brand-hover">
                    {{ $analysis ? 'Regenerate' : 'Generate analysis' }}
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />

        @unless($configured)
            <div class="mb-4 rounded-md bg-warn-soft border border-warn/30 px-4 py-3 text-sm text-warn">
                {{ __('app.ai.unavailable') }}
            </div>
        @else
            <p class="mb-4 text-xs text-muted">
                {{ __('app.ai.quota', ['remaining' => $quotaRemaining, 'limit' => $quotaLimit]) }}
            </p>
        @endunless

        <x-panel>
            @if($analysis)
                <div class="prose prose-sm max-w-none prose-headings:font-semibold prose-h1:text-lg prose-h2:text-base">
                    {{-- AI output is untrusted (model can emit HTML); strip raw HTML to prevent XSS. --}}
                    {!! \Illuminate\Support\Str::markdown($analysis->response, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]) !!}
                </div>
                <p class="mt-6 text-xs text-faint">Generated {{ $analysis->created_at->diffForHumans() }} · {{ $analysis->model }}</p>
            @else
                <p class="text-sm text-body">
                    Generate an evidence-based review of your training volume, strength trends, body-composition trajectory
                    and lean-bulk nutrition. The model receives your computed metrics (weekly sets vs landmarks, e1RM PRs,
                    p-ratio, TDEE/macros) and returns concrete next-4-week adjustments.
                </p>
            @endif
        </x-panel>
    </div>
</x-app-layout>

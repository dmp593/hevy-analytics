<x-ui.page :title="__('app.pages.ai')" :subtitle="__('app.pages.ai_sub')" width="4xl">
    <x-slot:actions>
        {{-- No force flag.

             This button used to submit force=1, which bypasses the content-hash
             cache and bills a fresh provider call on every press — against a
             30-a-month allowance, even when nothing about the athlete's data had
             changed. It also made the "your data has not changed" path
             unreachable, so that copy had never once been shown. --}}
        <form method="POST" action="{{ route('ai.generate') }}">
            @csrf
            <x-ui.button type="submit" size="sm">
                {{ $analysis ? __('app.ai.refresh') : __('app.ai.generate') }}
            </x-ui.button>
        </form>
    </x-slot:actions>

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
</x-ui.page>

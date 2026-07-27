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

    @if (! $configured && $analysis)
        {{-- An analysis on screen and "not available" above it contradict each
             other. That is what the demo showed: a written review, headed by a
             banner saying written reviews were unavailable. Say the true thing
             instead — this one exists, generating another needs a key. --}}
        <x-ui.insight tone="info" :title="__('app.ai.showing_existing')" class="mb-6">
            <p>{!! __('app.settings.ai.add_key', [
                'settings' => '<a href="'.route('profile.edit').'" class="underline font-medium">'.__('app.nav.profile').'</a>',
            ]) !!}</p>
        </x-ui.insight>
    @elseif (! $configured)
        {{-- No provider at all: the operator shipped without an included key and
             this athlete has not added one. Point at the fix rather than just
             reporting the absence. --}}
        <x-ui.insight tone="warn" :title="__('app.ai.unavailable')" class="mb-6">
            <p>{!! __('app.settings.ai.add_key', [
                'settings' => '<a href="'.route('profile.edit').'" class="underline font-medium">'.__('app.nav.profile').'</a>',
            ]) !!}</p>
        </x-ui.insight>
    @elseif ($usesOwnKey)
        {{-- Showing an allowance to someone spending their own key would invent
             a limit that does not apply to them. --}}
        <p class="mb-4 text-xs text-muted">{{ __('app.settings.ai.no_limit_own_key') }}</p>
    @else
        <p class="mb-4 text-xs text-muted">
            {{ __('app.ai.quota', ['remaining' => $quotaRemaining, 'limit' => $quotaLimit]) }}
            <a href="{{ route('profile.edit') }}" class="underline">{{ __('app.ai.want_unlimited') }}</a>
        </p>
    @endunless

    <x-ui.card>
        @if ($analysis)
            <div class="prose prose-sm max-w-none prose-headings:font-semibold prose-h1:text-lg prose-h2:text-base">
                {{-- AI output is untrusted (model can emit HTML); strip raw HTML to prevent XSS. --}}
                {!! \Illuminate\Support\Str::markdown($analysis->response, [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]) !!}
            </div>
            <p class="mt-6 text-xs text-faint">
                {{ __('app.ai.generated_meta', [
                    'when' => $analysis->created_at->diffForHumans(),
                    'model' => $analysis->model,
                ]) }}
            </p>
        @else
            <p class="text-sm text-body">{{ __('app.settings.ai.intro') }}</p>
        @endif
    </x-ui.card>
</x-ui.page>

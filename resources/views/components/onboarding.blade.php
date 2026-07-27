@props(['onboarding'])

{{-- The checklist a new account sees until everything is wired up. Each row is
     a real link, because "go to your profile" as prose makes the user hunt for
     the thing the app could simply have pointed at. --}}
<x-ui.card :title="__('app.onboarding.title')"
           :subtitle="__('app.onboarding.progress', ['done' => $onboarding->doneCount(), 'total' => count($onboarding->steps)])">
    <ol class="space-y-1">
        @foreach ($onboarding->steps as $i => $step)
            <li>
                <a href="{{ $step['route'] }}"
                   @class([
                       'flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 -mx-2 transition',
                       'pointer-events-none' => $step['done'],
                       'hover:bg-surface-sunk' => ! $step['done'],
                   ])>
                    @if ($step['done'])
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-good-soft">
                            <svg class="h-3.5 w-3.5 text-good" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.8 6.8-6.8a1 1 0 0 1 1.4 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    @else
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-strong text-xs font-semibold text-muted">{{ $i + 1 }}</span>
                    @endif

                    <span class="min-w-0">
                        <span @class(['block text-sm font-medium', 'text-muted line-through' => $step['done'], 'text-ink' => ! $step['done']])>
                            {{ __('app.onboarding.'.$step['key']) }}
                        </span>
                        @unless ($step['done'])
                            <span class="block text-xs text-muted">{{ __('app.onboarding.'.$step['key'].'_help') }}</span>
                        @endunless
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
</x-ui.card>

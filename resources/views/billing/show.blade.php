<x-ui.page :title="__('app.billing.title')" :subtitle="__('app.billing.subtitle')" width="3xl" class="space-y-6">
    <x-flash />

    {{-- Where you stand, first. Someone opening this page has a question —
         "am I paying?", "when does it renew?", "did my card fail?" — and the
         answer should not be below a marketing pitch. --}}
    <x-ui.card :title="__('app.billing.current')">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span @class([
                        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                        'bg-good-soft text-good' => $state === \App\Billing\State::Subscribed,
                        'bg-info-soft text-info' => $state === \App\Billing\State::Trialing,
                        'bg-warn-soft text-warn' => in_array($state, [\App\Billing\State::GracePeriod, \App\Billing\State::PastDue], true),
                        'bg-surface-sunk text-body' => $state === \App\Billing\State::Free,
                    ])>{{ $state->label() }}</span>
                </div>

                <p class="mt-2 text-sm text-body">
                    @switch($state)
                        @case(\App\Billing\State::Trialing)
                            {{ __('app.billing.trial_body', [
                                'days' => (int) max(0, now()->diffInDays($trialEndsAt, false)) + 1,
                                'date' => $trialEndsAt->isoFormat('D MMMM'),
                            ]) }}
                            @break

                        @case(\App\Billing\State::Subscribed)
                            {{ $nextPayment
                                ? __('app.billing.renews', ['date' => $nextPayment->date()->isoFormat('D MMMM YYYY')])
                                : __('app.billing.active') }}
                            @break

                        @case(\App\Billing\State::GracePeriod)
                            {{ __('app.billing.grace_body', [
                                'date' => $subscription->ends_at?->isoFormat('D MMMM YYYY') ?? '—',
                            ]) }}
                            @break

                        @case(\App\Billing\State::PastDue)
                            {{ __('app.billing.past_due_body') }}
                            @break

                        @default
                            {{ __('app.billing.free_body', ['days' => $entitlements->historyDays()]) }}
                    @endswitch
                </p>
            </div>

            @if ($state === \App\Billing\State::GracePeriod)
                <form method="POST" action="{{ route('billing.resume') }}">
                    @csrf
                    <x-ui.button type="submit">{{ __('app.billing.resume') }}</x-ui.button>
                </form>
            @elseif ($state === \App\Billing\State::Subscribed)
                <form method="POST" action="{{ route('billing.cancel') }}"
                      onsubmit="return confirm('{{ __('app.billing.cancel_confirm') }}')">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('app.billing.cancel') }}</x-ui.button>
                </form>
            @endif
        </div>
    </x-ui.card>

    @if ($state->shouldPromptToSubscribe())
        <x-ui.card :title="__('app.billing.what_you_get')" :subtitle="__('app.billing.price_line', ['price' => $price])">
            {{-- Stated as the difference it makes, not as a feature grid. Every
                 page works on both tiers; what changes is how far back the
                 analysis can see, and that is worth saying in those words. --}}
            <ul class="space-y-3 text-sm text-body">
                @foreach ([
                    'history' => __('app.billing.benefit_history', ['days' => config('billing.entitlements.free.history_days')]),
                    'trends' => __('app.billing.benefit_trends'),
                    'nutrition' => __('app.billing.benefit_nutrition'),
                    'ai' => __('app.billing.benefit_ai', ['count' => config('billing.entitlements.subscribed.ai_analyses_per_month')]),
                ] as $benefit)
                    <li class="flex gap-2.5">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-good" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0l-3.5-3.5a1 1 0 1 1 1.4-1.4l2.8 2.8 6.8-6.8a1 1 0 0 1 1.4 0z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $benefit }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- What it does NOT do. A subscription page that only lists upsides
                 is the one people feel misled by afterwards. --}}
            <div class="mt-5 rounded-lg border border-line bg-surface-sunk px-4 py-3">
                <p class="text-xs font-semibold text-muted">{{ __('app.billing.limits_title') }}</p>
                <p class="mt-1 text-xs text-muted">{{ __('app.billing.limits_body') }}</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-4">
                @if ($checkout)
                    {{-- Cashier's own component: it emits the data- attributes
                         paddle.js binds to, including the server-created
                         transaction id. Rolling our own would mean tracking
                         Paddle's overlay contract by hand. --}}
                    <x-paddle-button :checkout="$checkout"
                                     class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-on-fill transition hover:bg-brand-hover">
                        {{ __('app.billing.subscribe', ['price' => $price]) }}
                    </x-paddle-button>

                    <p class="text-xs text-muted">{{ __('app.billing.cancel_anytime') }}</p>
                @else
                    <p class="text-sm text-muted">{{ __('app.billing.unavailable') }}</p>
                @endif
            </div>
        </x-ui.card>
    @endif

    @if ($lastPayment || $nextPayment)
        <x-ui.card :title="__('app.billing.payments')">
            <dl class="space-y-2 text-sm">
                @if ($lastPayment)
                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('app.billing.last_payment') }}</dt>
                        <dd class="text-ink">{{ $lastPayment->amount() }} · {{ $lastPayment->date()->isoFormat('D MMM YYYY') }}</dd>
                    </div>
                @endif
                @if ($nextPayment)
                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('app.billing.next_payment') }}</dt>
                        <dd class="text-ink">{{ $nextPayment->amount() }} · {{ $nextPayment->date()->isoFormat('D MMM YYYY') }}</dd>
                    </div>
                @endif
            </dl>

            <p class="mt-4 text-xs text-muted">{{ __('app.billing.receipts') }}</p>
        </x-ui.card>
    @endif

    <p class="text-xs text-muted">
        {!! __('app.billing.export_note', [
            'export' => '<a href="'.route('settings.export').'" class="underline">'.__('app.settings.data.download').'</a>',
        ]) !!}
    </p>
</x-ui.page>

<x-ui.page :title="$user->name" :subtitle="$user->email" width="4xl" class="space-y-6">
    <x-slot:actions>
        <x-ui.button :href="route('admin.users')" variant="ghost" size="sm">{{ __('app.admin.back') }}</x-ui.button>
    </x-slot:actions>

    <x-flash />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('app.admin.state')" :value="$state->label()" />
        <x-ui.stat :label="__('app.admin.workouts')" :value="number_format($user->workouts_count)" />
        <x-ui.stat :label="__('app.admin.measurements')" :value="number_format($user->body_measurements_count)" />
        <x-ui.stat :label="__('app.admin.ai_calls')" :value="number_format($user->ai_usage_events_count)" />
    </div>

    <x-ui.card :title="__('app.admin.account')">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            @foreach ([
                __('app.admin.joined') => $user->created_at->isoFormat('D MMMM YYYY'),
                __('app.admin.verified') => $user->email_verified_at?->isoFormat('D MMM YYYY') ?? __('app.admin.not_verified'),
                __('app.admin.last_sync') => $user->hevy_last_synced_at?->diffForHumans() ?? __('app.sync.never'),
                __('app.admin.hevy_key') => $user->hasHevyKey() ? __('app.admin.present') : __('app.admin.absent'),
                __('app.admin.trial_ends') => $user->trial_ends_at?->isoFormat('D MMM YYYY') ?? '—',
                __('app.admin.history_cap') => $entitlements->historyDays() === null
                    ? __('app.admin.unlimited')
                    : __('app.admin.days', ['count' => $entitlements->historyDays()]),
            ] as $label => $value)
                <div class="flex justify-between gap-4 border-b border-subtle pb-2 last:border-0">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right text-ink">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        {{-- No API keys, ever. An admin needs to know whether a key is set, not
             what it is: reading someone's credential is not support, and a page
             that can display it is a page that can leak it. --}}
        <p class="mt-4 text-xs text-faint">{{ __('app.admin.no_secrets') }}</p>
    </x-ui.card>

    @if ($subscription)
        <x-ui.card :title="__('app.admin.subscription')">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ __('app.admin.paddle_id') }}</dt>
                    <dd class="font-mono text-xs text-ink">{{ $subscription->paddle_id }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted">{{ __('app.admin.status') }}</dt>
                    <dd class="text-ink">{{ $subscription->status }}</dd>
                </div>
                @if ($subscription->ends_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted">{{ __('app.admin.ends') }}</dt>
                        <dd class="text-ink">{{ $subscription->ends_at->isoFormat('D MMM YYYY') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($subscription->valid() && ! $subscription->onGracePeriod())
                {{-- Typing the email is the confirmation. A dialog is dismissed
                     by reflex; a field that must match is not. --}}
                <form method="POST" action="{{ route('admin.users.cancel', $user) }}" class="mt-5 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="form-label">
                        {{ __('app.admin.confirm_email') }}
                        <input type="text" name="confirm" class="form-control w-64" autocomplete="off" required>
                    </label>
                    <x-ui.button type="submit" variant="danger" size="sm">{{ __('app.admin.cancel_sub') }}</x-ui.button>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('app.admin.cancel_note') }}</p>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card :title="__('app.admin.comp')" :subtitle="__('app.admin.comp_sub')">
        @if ($user->comped_until)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-line bg-surface-sunk px-4 py-3">
                <div>
                    <div class="text-sm text-ink">
                        {{ __('app.admin.comped_until', ['date' => $user->comped_until->isoFormat('D MMMM YYYY')]) }}
                    </div>
                    @if ($user->comped_reason)
                        <div class="mt-0.5 text-xs text-muted">{{ $user->comped_reason }}</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.users.revoke', $user) }}">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('app.admin.revoke') }}</x-ui.button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.grant', $user) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <label class="form-label">
                {{ __('app.admin.days_label') }}
                <input type="number" name="days" value="30" min="1" max="3650" class="form-control w-28" required>
            </label>
            <label class="form-label flex-1">
                {{ __('app.admin.reason') }}
                <input type="text" name="reason" class="form-control" maxlength="255" required
                       placeholder="{{ __('app.admin.reason_placeholder') }}">
            </label>
            <x-ui.button type="submit" size="sm">{{ __('app.admin.grant') }}</x-ui.button>
        </form>
        <x-input-error class="mt-2" :messages="$errors->get('days')" />
        <x-input-error class="mt-2" :messages="$errors->get('reason')" />
    </x-ui.card>

    <x-ui.card :title="__('app.admin.audit')" :subtitle="__('app.admin.audit_sub')" flush>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="py-2 pl-5 pr-4">{{ __('app.admin.when') }}</th>
                        <th class="py-2 pr-4">{{ __('app.admin.who') }}</th>
                        <th class="py-2 pr-5">{{ __('app.admin.what') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($actions as $action)
                        <tr class="table-row">
                            <td class="py-2 pl-5 pr-4 text-muted">{{ $action->created_at->isoFormat('D MMM YYYY HH:mm') }}</td>
                            <td class="py-2 pr-4">{{ $action->admin?->name ?? '—' }}</td>
                            <td class="py-2 pr-5">
                                <div class="text-ink">{{ $action->describe() }}</div>
                                @if ($action->detail)
                                    <div class="text-xs text-muted">{{ $action->detail }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-4 text-muted">{{ __('app.admin.no_actions') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>

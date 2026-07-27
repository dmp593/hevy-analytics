<x-ui.page :title="__('app.admin.users')" :subtitle="__('app.admin.users_sub')">
    <x-flash />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-6">
        @foreach ($states as $case)
            <a href="{{ route('admin.users', array_filter(['q' => $search, 'state' => $case->value])) }}"
               @class([
                   'rounded-xl border bg-surface px-4 py-3 shadow-xs transition hover:border-strong',
                   'border-brand' => $state === $case->value,
                   'border-line' => $state !== $case->value,
               ])>
                <div class="truncate text-xs font-medium uppercase tracking-wide text-muted">{{ $case->label() }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums text-ink">{{ $counts[$case->value] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <x-ui.card :title="__('app.admin.all_users')" flush>
        <x-slot:actions>
            <form method="GET" action="{{ route('admin.users') }}" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('app.admin.search') }}"
                       class="form-control mt-0 w-56 text-xs">
                @if ($state)
                    <input type="hidden" name="state" value="{{ $state }}">
                @endif
                <x-ui.button type="submit" variant="secondary" size="sm">{{ __('app.admin.find') }}</x-ui.button>
                @if ($search || $state)
                    <x-ui.button :href="route('admin.users')" variant="ghost" size="sm">{{ __('app.admin.clear') }}</x-ui.button>
                @endif
            </form>
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="table-head">
                        <th class="py-2 pl-5 pr-4">{{ __('app.admin.athlete') }}</th>
                        <th class="py-2 pr-4">{{ __('app.admin.state') }}</th>
                        <th class="py-2 pr-4">{{ __('app.admin.workouts') }}</th>
                        <th class="py-2 pr-4">{{ __('app.admin.last_sync') }}</th>
                        <th class="py-2 pr-5">{{ __('app.admin.joined') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $row)
                        @php $rowState = $row->billingState(); @endphp
                        <tr class="table-row">
                            <td class="py-2 pl-5 pr-4">
                                <a href="{{ route('admin.users.show', $row) }}" class="font-medium text-brand-ink hover:underline">
                                    {{ $row->name }}
                                </a>
                                <div class="text-xs text-muted">{{ $row->email }}</div>
                            </td>
                            <td class="py-2 pr-4">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-good-soft text-good' => $rowState === \App\Billing\State::Subscribed,
                                    'bg-info-soft text-info' => in_array($rowState, [\App\Billing\State::Trialing, \App\Billing\State::Complimentary], true),
                                    'bg-warn-soft text-warn' => in_array($rowState, [\App\Billing\State::GracePeriod, \App\Billing\State::PastDue], true),
                                    'bg-surface-sunk text-body' => $rowState === \App\Billing\State::Free,
                                ])>{{ $rowState->label() }}</span>
                            </td>
                            <td class="py-2 pr-4 tabular-nums">{{ number_format($row->workouts_count) }}</td>
                            <td class="py-2 pr-4 text-muted">
                                {{ $row->hevy_last_synced_at?->diffForHumans() ?? __('app.sync.never') }}
                            </td>
                            <td class="py-2 pr-5 text-muted">{{ $row->created_at->isoFormat('D MMM YYYY') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-4 text-muted">{{ __('app.admin.no_users') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $users->links() }}</div>
        @endif
    </x-ui.card>
</x-ui.page>

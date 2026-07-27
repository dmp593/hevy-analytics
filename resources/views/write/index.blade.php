<x-ui.page :title="__('app.pages.write')" :subtitle="__('app.pages.write_sub')" width="5xl">
    <x-flash />
    <p class="text-sm text-muted mb-6">{{ __('app.write.intro') }}</p>

    <x-panel>
        @forelse($operations as $op)
            <div class="py-4 border-b border-subtle last:border-0" x-data="{ open: false }">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-mono text-xs rounded-sm bg-surface-sunk px-2 py-0.5">{{ $op->method }}</span>
                        <span class="font-medium text-sm ml-2">{{ $op->operation }}</span>
                        <span class="text-xs text-faint ml-2">{{ $op->endpoint }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        @php
                            // Ask the service the same question it asks itself, rather than
                            // duplicating its status list here — that drift is what left a
                            // stalled operation with no way to retry it.
                            $stale = \App\Services\Hevy\HevyWriter::isStale($op);
                            $canPush = \App\Services\Hevy\HevyWriter::isExecutable($op);
                            $tone = match(true) {
                                $op->status === 'success' => 'bg-good-soft text-good',
                                $op->status === 'failed' => 'bg-bad-soft text-bad',
                                $op->status === 'pending' => 'bg-warn-soft text-warn',
                                $stale => 'bg-bad-soft text-bad',
                                default => 'bg-surface-sunk text-body',
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $tone }}">{{ $stale ? __('app.write.stalled') : $op->status }}</span>
                        <button @click="open = !open" class="text-xs text-muted">{{ __('app.write.details') }}</button>
                        @if($canPush)
                            <form method="POST" action="{{ route('write.confirm', $op) }}">
                                @csrf
                                <button class="text-xs rounded-md bg-brand text-on-fill px-3 py-1 hover:bg-brand-hover">
                                    {{ $stale ? __('app.write.retry') : __('app.write.confirm') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @if(!empty($op->revert_info['changes']))
                    <ul class="mt-2 text-xs text-body list-disc list-inside">
                        @foreach(array_slice($op->revert_info['changes'], 0, 12) as $c)<li>{{ $c }}</li>@endforeach
                    </ul>
                @endif
                <div x-show="open" x-cloak class="mt-2">
                    <pre class="text-[11px] bg-ink text-canvas rounded-lg p-3 overflow-x-auto">{{ json_encode($op->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                    @if($op->response)
                        <pre class="text-[11px] bg-surface-sunk rounded-lg p-3 overflow-x-auto mt-1">{{ json_encode($op->response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                </div>
                <div class="text-[11px] text-faint mt-1">{{ $op->created_at->diffForHumans() }}</div>
            </div>
        @empty
            <p class="text-sm text-muted">{{ __('app.write.none') }}</p>
        @endforelse
    </x-panel>
</x-ui.page>

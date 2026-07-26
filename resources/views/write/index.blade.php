<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Write operations</h2></x-slot>

    <div class="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-flash />
        <p class="text-sm text-gray-500 mb-6">Every change to Hevy is staged here first. Review the payload, then confirm to push. Successful writes trigger an automatic re-sync.</p>

        <x-panel>
            @forelse($operations as $op)
                <div class="py-4 border-b border-gray-100 last:border-0" x-data="{ open: false }">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-mono text-xs rounded-sm bg-gray-100 px-2 py-0.5">{{ $op->method }}</span>
                            <span class="font-medium text-sm ml-2">{{ $op->operation }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ $op->endpoint }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @php
                                // Ask the service the same question it asks itself, rather than
                                // duplicating its status list here — that drift is what left a
                                // stalled operation with no way to retry it.
                                $stale = \App\Services\Hevy\HevyWriter::isStale($op);
                                $canPush = \App\Services\Hevy\HevyWriter::isExecutable($op);
                                $tone = match(true) {
                                    $op->status === 'success' => 'bg-green-100 text-green-700',
                                    $op->status === 'failed' => 'bg-red-100 text-red-700',
                                    $op->status === 'pending' => 'bg-amber-100 text-amber-700',
                                    $stale => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $tone }}">{{ $stale ? __('app.write.stalled') : $op->status }}</span>
                            <button @click="open = !open" class="text-xs text-gray-500">{{ __('app.write.details') }}</button>
                            @if($canPush)
                                <form method="POST" action="{{ route('write.confirm', $op) }}">
                                    @csrf
                                    <button class="text-xs rounded-md bg-indigo-600 text-white px-3 py-1 hover:bg-indigo-700">
                                        {{ $stale ? __('app.write.retry') : __('app.write.confirm') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @if(!empty($op->revert_info['changes']))
                        <ul class="mt-2 text-xs text-gray-600 list-disc list-inside">
                            @foreach(array_slice($op->revert_info['changes'], 0, 12) as $c)<li>{{ $c }}</li>@endforeach
                        </ul>
                    @endif
                    <div x-show="open" x-cloak class="mt-2">
                        <pre class="text-[11px] bg-gray-900 text-gray-100 rounded-lg p-3 overflow-x-auto">{{ json_encode($op->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                        @if($op->response)
                            <pre class="text-[11px] bg-gray-100 rounded-lg p-3 overflow-x-auto mt-1">{{ json_encode($op->response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </div>
                    <div class="text-[10px] text-gray-400 mt-1">{{ $op->created_at->diffForHumans() }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No write operations yet. Stage a routine progression from any routine's edit page.</p>
            @endforelse
        </x-panel>
    </div>
</x-app-layout>

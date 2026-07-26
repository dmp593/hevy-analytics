@props(['status' => null])

@php
    $state = $status['state'] ?? 'idle';
    $message = $status['message'] ?? null;
@endphp

@if ($message && in_array($state, ['queued', 'stalled', 'running', 'failed'], true))
    @php
        $tone = match ($state) {
            'failed', 'stalled' => 'border-bad/30 bg-bad-soft text-bad',
            default => 'border-info/30 bg-info-soft text-info',
        };
    @endphp
    <div class="mb-4 flex items-start gap-3 rounded-md border px-4 py-3 text-sm {{ $tone }}" role="status">
        @if (in_array($state, ['queued', 'running'], true))
            <svg class="mt-0.5 h-4 w-4 shrink-0 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z" />
            </svg>
        @else
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
            </svg>
        @endif
        <span>{{ $message }}</span>
    </div>
@endif

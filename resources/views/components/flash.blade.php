@if (session('status'))
    <div class="mb-4 rounded-md bg-good-soft border border-good/30 px-4 py-3 text-sm text-good">
        {{ session('status') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-md bg-bad-soft border border-bad/30 px-4 py-3 text-sm text-bad">
        {{ session('error') }}
    </div>
@endif

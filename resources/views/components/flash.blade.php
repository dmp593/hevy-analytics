@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
        {{ session('status') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif

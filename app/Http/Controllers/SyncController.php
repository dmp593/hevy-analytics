<?php

namespace App\Http\Controllers;

use App\Jobs\SyncHevyJob;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Queue a Hevy sync.
     *
     * This used to run the whole sync inside the request: several paginated
     * round-trips to a third-party API with the browser left hanging and no
     * progress indication, and a request timeout partway through leaving the
     * sync half-applied. The queued job already existed and was never dispatched.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasHevyKey()) {
            return back()->with('error', 'Add your Hevy API key in Profile first.');
        }

        if ($user->syncLogs()->where('status', 'running')->where('started_at', '>=', now()->subMinutes(15))->exists()) {
            return back()->with('status', 'A sync is already running. This page will show the new data once it finishes.');
        }

        SyncHevyJob::dispatch($user->id, $request->boolean('force'));

        return back()->with('status', 'Sync started. Your data will appear here shortly — refresh in a moment.');
    }
}

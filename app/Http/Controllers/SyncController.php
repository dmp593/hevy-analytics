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

        // Deduplication lives on the job (ShouldBeUnique), not here: the sync_log
        // row is written inside the job, so at this point there is nothing yet to
        // detect and rapid clicks all looked like the first one.
        SyncHevyJob::dispatch($user->id, $request->boolean('force'));

        return back()->with('status', 'Sync started. Your data will appear here shortly — refresh in a moment.');
    }
}

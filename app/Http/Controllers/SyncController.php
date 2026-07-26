<?php

namespace App\Http\Controllers;

use App\Jobs\SyncHevyJob;
use App\Services\Hevy\SyncStatus;
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

        $status = new SyncStatus($user);
        if ($status->isPending()) {
            return back()->with('status', $status->current()['message']);
        }

        // Record the queued state up front so the UI has something to show while
        // the job waits, and so a queue that nobody is consuming is visible
        // instead of silently swallowing the request.
        $log = $user->syncLogs()->create([
            'type' => 'incremental',
            'status' => 'queued',
        ]);

        // Deduplication also lives on the job itself (ShouldBeUnique), which is
        // what actually holds under concurrent requests.
        SyncHevyJob::dispatch($user->id, $request->boolean('force'), $log->id);

        return back()->with('status', 'Sync queued — refresh in a moment to see your new data.');
    }
}

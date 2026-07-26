<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Models\WriteOperation;
use App\Services\Hevy\HevyWriter;
use App\Services\Hevy\RoutineProgression;
use Illuminate\Http\Request;

class WriteBackController extends Controller
{
    public function index(Request $request)
    {
        return view('write.index', [
            'operations' => $request->user()->writeOperations()->latest()->limit(50)->get(),
        ]);
    }

    public function confirm(Request $request, WriteOperation $operation)
    {
        $this->authorizeOwner($operation);

        $result = (new HevyWriter($request->user()))->execute($operation)->fresh();

        return $result->status === 'success'
            ? back()->with('status', 'Change pushed to Hevy and re-synced.')
            : back()->with('error', 'Push failed: '.json_encode($result->response));
    }

    /**
     * Build an evidence-based progression for a routine and stage it as a
     * pending write operation for the user to review and confirm.
     */
    public function stageProgression(Request $request, Routine $routine)
    {
        $this->authorizeOwner($routine);

        $user = $request->user();
        $progression = (new RoutineProgression($user))->build($routine);

        (new HevyWriter($user))
            ->stage('routine.update', 'PUT', "/v1/routines/{$routine->hevy_id}", $progression['payload'])
            ->update(['revert_info' => ['changes' => $progression['changes']]]);

        return redirect()->route('write.index')
            ->with('status', 'Progression staged for "'.$routine->title.'". Review and confirm to push to Hevy.');
    }
}

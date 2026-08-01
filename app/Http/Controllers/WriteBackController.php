<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Models\User;
use App\Models\WriteOperation;
use App\Services\Hevy\HevyWriter;
use App\Services\Hevy\RoutineAdjustment;
use App\Services\Hevy\RoutineProgression;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ? back()->with('status', __('app.write.pushed'))
            : back()->with('error', __('app.write.push_failed'));
    }

    /**
     * Build an evidence-based progression for a routine and stage it as a
     * pending write operation for the user to review and confirm.
     */
    public function stageProgression(Request $request, Routine $routine)
    {
        $this->authorizeOwner($routine);

        $user = $request->user();

        return $this->stage($user, $routine, (new RoutineProgression($user))->build($routine));
    }

    /**
     * Stage an advisor adjustment (add an exercise, or swap one for a new
     * stimulus) as a pending write operation. The payload is rebuilt server
     * side from the routine and the named template — the form only says WHAT,
     * never carries the routine content itself.
     */
    public function stageAdjustment(Request $request, Routine $routine)
    {
        $this->authorizeOwner($routine);

        $user = $request->user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['add', 'swap'])],
            'template' => ['required', 'string'],
            'replace' => ['required_if:action,swap', 'string'],
        ]);

        $template = $user->exerciseTemplates()->where('hevy_id', $data['template'])->firstOrFail();

        $built = $data['action'] === 'add'
            ? (new RoutineAdjustment)->addExercise($routine, $template)
            : (new RoutineAdjustment)->swapExercise(
                $routine,
                $routine->exercises()->where('exercise_template_hevy_id', $data['replace'])->firstOrFail(),
                $template,
            );

        return $this->stage($user, $routine, $built);
    }

    /**
     * Stage a built routine payload, replacing any change still waiting for
     * this same routine.
     *
     * Every staged payload is a FULL snapshot of the routine, taken when it
     * was built. Two unconfirmed snapshots of one routine therefore describe
     * two futures that both start from today: confirming the first and then
     * the second would push a payload that never knew about the first, and
     * silently undo it in the athlete's real Hevy account. One pending change
     * per routine makes that impossible — the newest intent wins, and the
     * next suggestion is computed from the routine as it actually is.
     */
    private function stage(User $user, Routine $routine, array $built)
    {
        $endpoint = "/v1/routines/{$routine->hevy_id}";

        $replaced = $user->writeOperations()
            ->where('endpoint', $endpoint)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        (new HevyWriter($user))
            ->stage('routine.update', 'PUT', $endpoint, $built['payload'])
            ->update(['revert_info' => ['changes' => $built['changes']]]);

        return redirect()->route('write.index')->with('status', __(
            $replaced ? 'app.write.staged_replaced' : 'app.write.staged',
            ['routine' => $routine->title],
        ));
    }
}

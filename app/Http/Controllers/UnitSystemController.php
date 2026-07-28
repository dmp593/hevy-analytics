<?php

namespace App\Http\Controllers;

use App\Support\Units;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One-tap unit switching, so the welcome card can offer it without sending a
 * brand-new user into the full profile form. The profile form sets the same
 * column; this is just the short road to it.
 *
 * The demo account never reaches this route (EnsureDemoIsReadOnly): hundreds
 * of visitors share that row, and one visitor's units must not flip the
 * numbers for everyone after them.
 */
class UnitSystemController extends Controller
{
    public function __invoke(Request $request, string $system): RedirectResponse
    {
        abort_unless(in_array($system, Units::SYSTEMS, true), 404);

        $request->user()->forceFill(['unit_system' => $system])->save();

        return back();
    }
}

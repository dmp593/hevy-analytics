<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * One-tap switch between the contribution-grid heatmap and the classic
 * month calendar — same data, two vocabularies. Mirrors UnitSystemController.
 */
class CalendarStyleController extends Controller
{
    public const STYLES = ['heatmap', 'classic'];

    public function __invoke(Request $request, string $style)
    {
        abort_unless(in_array($style, self::STYLES, true), 404);

        $request->user()->forceFill(['calendar_style' => $style])->save();

        return back()->with('status', __('app.calendar.saved'));
    }
}

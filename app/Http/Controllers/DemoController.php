<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The one-click demonstration.
 *
 * The landing page's biggest leak is the two walls between "curious" and
 * "convinced": register, then find the profile, then a Hevy API key, then a
 * sync. This walks around both — one click and the visitor is inside a fully
 * populated account. The account itself is read-only (EnsureDemoIsReadOnly),
 * so a thousand visitors can share it without wrecking it for each other.
 */
class DemoController extends Controller
{
    public function enter(Request $request)
    {
        $demo = User::where('is_demo', true)->first();

        if (! $demo) {
            // Seeding is a deploy step; if it has not run, say so rather than 500.
            return redirect()->route('home')->with('error', __('app.demo.unavailable'));
        }

        // Never "remember" — a demo session should end with the browser, not
        // greet the next person on a shared machine as Demo Lifter.
        Auth::login($demo, remember: false);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /** From demo straight to the register form, without the guest-middleware bounce. */
    public function leave(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register');
    }
}

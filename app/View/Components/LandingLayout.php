<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The shell for the one page a signed-out visitor is meant to read.
 *
 * Separate from the guest layout, which is a narrow centred card built for the
 * login form and would squeeze a landing page into a column.
 */
class LandingLayout extends Component
{
    public function render(): View
    {
        return view('layouts.landing');
    }
}

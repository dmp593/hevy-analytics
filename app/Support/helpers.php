<?php

use App\Support\Units;

if (! function_exists('units')) {
    /**
     * The signed-in user's unit system, for Blade edges. Guests and the
     * unauthenticated get metric, which is also what every calculation uses.
     */
    function units(): Units
    {
        return Units::for(auth()->user());
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported interface languages
    |--------------------------------------------------------------------------
    |
    | The single source of truth for which languages the app offers. The locale
    | switcher, the profile validation rule and the middleware all read this, so
    | adding a language means adding one entry here and a lang/<code> directory —
    | nothing else in the codebase needs to know.
    |
    | `native` is what appears in the switcher: people look for their language
    | written in their language, not in English.
    |
    */

    'supported' => [
        'en' => ['native' => 'English', 'regional' => 'en_GB'],
        'pt' => ['native' => 'Português', 'regional' => 'pt_PT'],
    ],

    /*
    | Used when a visitor has expressed no preference and their browser asks for
    | something we do not speak.
    */
    'fallback' => 'en',

];

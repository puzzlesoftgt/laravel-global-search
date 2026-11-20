<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    |
    | Search commands available in the global search. You can also register
    | them in a service provider with GlobalSearch::register().
    |
    */

    'commands' => [
        // \App\Search\OrdersSearch::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyboard Shortcut
    |--------------------------------------------------------------------------
    |
    | Shortcut used to open the global search.
    |
    | Examples:
    | ctrl+k
    | meta+k
    | mod+k
    | alt+k
    | ctrl+shift+p
    |
    | "mod" uses Ctrl on Windows/Linux and Command on macOS.
    |
    */

    'keyboard' => [
        'shortcut' => 'mod+k',
        'enabled' => true,
        'prevent_default' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    */

    'search' => [
        'debounce' => 200,
        'min_characters' => 2,
        'max_results' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface
    |--------------------------------------------------------------------------
    */

    'ui' => [
        // light, dark or auto (follows the operating system preference).
        'theme' => 'auto',

        // Null uses the translation for the active locale.
        // A string here replaces that translation in every locale.
        'placeholder' => null,
        'show_icons' => true,
        'show_descriptions' => true,
        'close_on_select' => true,
    ],

];

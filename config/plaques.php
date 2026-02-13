<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plaque predicate display mappings
    |--------------------------------------------------------------------------
    |
    | Override the display text for connection predicates on plaques.
    | Keys are the URL predicate (hyphenated, e.g. "lived-in").
    | Unmapped predicates are shown as-is with hyphens replaced by spaces.
    |
    */

    'predicate_mappings' => [
        'lived-in' => 'lived in a house on this site',
        // Add more mappings as needed, e.g.:
        // 'worked-at' => 'worked here',
        // 'studied-at' => 'studied at',
    ],

];

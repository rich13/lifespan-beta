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

    /*
    |--------------------------------------------------------------------------
    | Index map
    |--------------------------------------------------------------------------
    |
    | Default view for /plaques. Centre/zoom are starting points and can be
    | refined later. Marker density is controlled by zoom in the markers API.
    |
    */

    'map' => [
        'centre' => [54.5, -2.8],
        'zoom' => 6,
        'plaque_zoom' => 15,
        'max_markers' => 250,
    ],

];

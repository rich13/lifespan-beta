<?php

/**
 * Target locations to query from Nominatim when generating the OSM import JSON.
 * Default: Greater London (boroughs, stations, airports). Works for any region:
 * set OSM_IMPORT_DATA_PATH and replace these entries with your area's places.
 *
 * Each entry: 'search_query' => 'category'
 * - search_query: string sent to Nominatim /search (e.g. "Westminster, London")
 * - category: logical category for the osmdata importer (borough|station|airport|...)
 *
 * Include top-level areas (city/region) in 'areas' so the JSON has boundaries that
 * contain sub-areas; the importer uses these for containment (e.g. borough inside London).
 */
return [
    'areas' => [
        'London' => 'city',
        'Greater London, England' => 'region',
    ],

    'boroughs' => [
        'City of London, London' => 'borough',
        'Barking and Dagenham, London' => 'borough',
        'Barnet, London' => 'borough',
        'Bexley, London' => 'borough',
        'Brent, London' => 'borough',
        'Bromley, London' => 'borough',
        'Camden, London' => 'borough',
        'Croydon, London' => 'borough',
        'Ealing, London' => 'borough',
        'Enfield, London' => 'borough',
        'Greenwich, London' => 'borough',
        'Hackney, London' => 'borough',
        'Hammersmith and Fulham, London' => 'borough',
        'Haringey, London' => 'borough',
        'Harrow, London' => 'borough',
        'Havering, London' => 'borough',
        'Hillingdon, London' => 'borough',
        'Hounslow, London' => 'borough',
        'Islington, London' => 'borough',
        'Kensington and Chelsea, London' => 'borough',
        'Kingston upon Thames, London' => 'borough',
        'Lambeth, London' => 'borough',
        'Lewisham, London' => 'borough',
        'Merton, London' => 'borough',
        'Newham, London' => 'borough',
        'Redbridge, London' => 'borough',
        'Richmond upon Thames, London' => 'borough',
        'Southwark, London' => 'borough',
        'Sutton, London' => 'borough',
        'Tower Hamlets, London' => 'borough',
        'Waltham Forest, London' => 'borough',
        'Wandsworth, London' => 'borough',
        'Westminster, London' => 'borough',
    ],

    'stations' => [
        'London King\'s Cross station' => 'station',
        'London St Pancras station' => 'station',
        'London Paddington station' => 'station',
        'London Waterloo station' => 'station',
        'London Victoria station' => 'station',
        'London Liverpool Street station' => 'station',
        'London Bridge station' => 'station',
        'London Euston station' => 'station',
        'London Charing Cross station' => 'station',
        'London Cannon Street station' => 'station',
        'London Marylebone station' => 'station',
        'London Fenchurch Street station' => 'station',
        'London Blackfriars station' => 'station',
        'London Waterloo East station' => 'station',
        'London City Airport station' => 'station',
    ],

    'airports' => [
        'Heathrow Airport, London' => 'airport',
        'London City Airport' => 'airport',
    ],
];

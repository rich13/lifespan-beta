<?php

/**
 * Inspect Edinburgh and London place spans to debug incorrect "Inside" relations.
 * Run: docker-compose exec app php scripts/inspect_place_relations.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// UUIDs from place URLs: first = London, second = City of Edinburgh (per user)
$londonId = '9f51dbfe-a7df-4f21-a70a-e3a95703f6d9';
$edinburghId = '9ef36ec0-ebfb-4db7-a960-fac0d18a7bcb';

$london = App\Models\Span::find($londonId);
$edinburgh = App\Models\Span::find($edinburghId);

if (!$edinburgh || !$london) {
    echo "One or both spans not found.\n";
    exit(1);
}

function bboxFromGeoJson($geo): ?array
{
    if (!$geo || !is_array($geo)) {
        return null;
    }
    if (isset($geo['geometry'])) {
        return bboxFromGeoJson($geo['geometry']);
    }
    $type = $geo['type'] ?? null;
    $coords = $geo['coordinates'] ?? null;
    if (!$coords) {
        return null;
    }
    $lats = [];
    $lons = [];
    $collect = function ($c) use (&$lats, &$lons, &$collect) {
        if (isset($c[0]) && is_numeric($c[0]) && is_numeric($c[1])) {
            $lons[] = (float) $c[0];
            $lats[] = (float) $c[1];
            return;
        }
        foreach ($c as $item) {
            if (is_array($item)) {
                $collect($item);
            }
        }
    };
    $collect($coords);
    if (empty($lats)) {
        return null;
    }
    return [
        'min_lat' => min($lats),
        'max_lat' => max($lats),
        'min_lon' => min($lons),
        'max_lon' => max($lons),
    ];
}

echo "=== LONDON (id: {$londonId}) ===\n";
echo "Name: " . $london->name . "\n";
$coordsLondon = $london->getCoordinates();
echo "getCoordinates(): " . ($coordsLondon ? json_encode($coordsLondon) : 'null') . "\n";
$centroidLondon = $london->boundaryCentroid();
echo "boundaryCentroid(): " . ($centroidLondon ? json_encode($centroidLondon) : 'null') . "\n";
echo "hasBoundary(): " . ($london->hasBoundary() ? 'true' : 'false') . "\n";
$boundaryLondon = $london->getBoundary();
if ($boundaryLondon) {
    $bboxLondon = bboxFromGeoJson($boundaryLondon);
    $typeLondon = $boundaryLondon['type'] ?? ($boundaryLondon['geometry']['type'] ?? '?');
    echo "Boundary type: {$typeLondon}\n";
    if ($bboxLondon) {
        echo "Boundary bbox: min_lat={$bboxLondon['min_lat']}, max_lat={$bboxLondon['max_lat']}, min_lon={$bboxLondon['min_lon']}, max_lon={$bboxLondon['max_lon']}\n";
    }
} else {
    echo "Boundary: none\n";
}
echo "\n";

echo "=== CITY OF EDINBURGH (id: {$edinburghId}) ===\n";
echo "Name: " . $edinburgh->name . "\n";
$coords = $edinburgh->getCoordinates();
echo "getCoordinates(): " . ($coords ? json_encode($coords) : 'null') . "\n";
$centroid = $edinburgh->boundaryCentroid();
echo "boundaryCentroid(): " . ($centroid ? json_encode($centroid) : 'null') . "\n";
echo "hasBoundary(): " . ($edinburgh->hasBoundary() ? 'true' : 'false') . "\n";

$edinburghPoint = $centroid ?? $coords;
$edinburghLat = $edinburghPoint ? (float) ($edinburghPoint['latitude'] ?? 0) : null;
$edinburghLon = $edinburghPoint ? (float) ($edinburghPoint['longitude'] ?? 0) : null;
echo "Point used for relations: lat={$edinburghLat}, lon={$edinburghLon}\n";
echo "  (Actual Edinburgh, Scotland is ~55.95, -3.19 – stored point is in " . (abs($edinburghLat - 55.95) < 1 ? 'Scotland' : 'LONDON / England') . ")\n";

$boundaryEdinburgh = $edinburgh->getBoundary();
if ($boundaryEdinburgh) {
    $typeEdinburgh = $boundaryEdinburgh['type'] ?? ($boundaryEdinburgh['geometry']['type'] ?? '?');
    echo "Boundary type: {$typeEdinburgh}\n";
}
echo "\n";

echo "=== CONTAINMENT TEST ===\n";
if ($edinburghLat !== null && $edinburghLon !== null) {
    $londonContainsEdinburghPoint = $london->containsPoint($edinburghLat, $edinburghLon);
    echo "London->containsPoint(City-of-Edinburgh's stored point {$edinburghLat}, {$edinburghLon}): " . ($londonContainsEdinburghPoint ? 'TRUE' : 'false') . "\n";
    echo "  => So London " . ($londonContainsEdinburghPoint ? 'DOES' : 'does not') . " appear in City of Edinburgh's 'Inside' list.\n";
}

echo "\n=== DIAGNOSIS ===\n";
if ($edinburghLat !== null && ($edinburghLat < 54 || $edinburghLon > -1)) {
    echo "ISSUE: City of Edinburgh span has coordinates ({$edinburghLat}, {$edinburghLon}) which are in/south of England, not Scotland (Edinburgh is ~55.95, -3.19).\n";
    echo "Re-geocoding may have picked a different 'Edinburgh' (e.g. in London) or the wrong result. Fix: re-geocode with 'Edinburgh, Scotland' or set coordinates to 55.9533, -3.1883.\n";
}

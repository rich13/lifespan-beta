<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Span;
use Illuminate\Support\Facades\DB;

echo "Testing Desert Island Discs page performance...\n\n";

// Clear query log
DB::flushQueryLog();
DB::enableQueryLog();

// Simulate the desertIslandDiscs method exactly
$query = Span::query()
    ->where('type_id', 'set')
    ->where(function($q) {
        $q->whereJsonContains('metadata->subtype', 'desertislanddiscs')
          ->orWhere('metadata->subtype', 'desertislanddiscs');
    })
    ->orderBy('name');

// Apply access filtering (public only for this test)
$query->where('access_level', 'public');

$sets = $query->paginate(20);

echo "Initial query count: " . count(DB::getQueryLog()) . "\n";

// Use cached set contents for each set (same as controller)
$sets->getCollection()->transform(function ($set) {
    $tracks = $set->getSetContents()->filter(function($item) {
        return $item->type_id === 'thing' && 
               ($item->metadata['subtype'] ?? null) === 'track';
    });
    $set->preloaded_tracks = $tracks;
    return $set;
});

echo "Final query count: " . count(DB::getQueryLog()) . "\n";
echo "Total queries: " . count(DB::getQueryLog()) . "\n";
echo "Sets processed: " . $sets->count() . "\n";
echo "Total sets available: " . $sets->total() . "\n";

// Show some query details
echo "\nQuery breakdown:\n";
$queryTypes = [];
foreach (DB::getQueryLog() as $query) {
    $type = 'other';
    if (strpos($query['query'], 'spans') !== false) {
        $type = 'spans';
    } elseif (strpos($query['query'], 'connections') !== false) {
        $type = 'connections';
    } elseif (strpos($query['query'], 'connection_types') !== false) {
        $type = 'connection_types';
    }
    
    if (!isset($queryTypes[$type])) {
        $queryTypes[$type] = 0;
    }
    $queryTypes[$type]++;
}

foreach ($queryTypes as $type => $count) {
    echo "  $type: $count queries\n";
}

echo "\nPerformance test completed!\n"; 
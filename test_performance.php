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

// Simulate the desertIslandDiscs method
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

// Pre-load all set contents with tracks, artists, and albums to avoid N+1 queries
$sets->getCollection()->transform(function ($set) {
    // Get set contents with eager loading
    $contents = $set->connectionsAsSubject()
        ->whereHas('type', function ($query) {
            $query->where('type', 'contains');
        })
        ->with([
            'child:id,name,type_id,description,start_year,end_year,owner_id,access_level,metadata',
            'child.connectionsAsObject' => function ($query) {
                $query->whereHas('type', function ($q) {
                    $q->where('type', 'created');
                })
                ->whereHas('parent', function ($q) {
                    $q->whereIn('type_id', ['person', 'band']);
                })
                ->with(['parent:id,name,type_id']);
            },
            'child.connectionsAsObject.parent'
        ])
        ->get()
        ->map(function ($connection) {
            $child = $connection->child;
            $child->pivot = (object) [
                'created_at' => $connection->created_at,
                'updated_at' => $connection->updated_at
            ];
            return $child;
        });

    // Filter to tracks only
    $tracks = $contents->filter(function($item) {
        return $item->type_id === 'thing' && 
               ($item->metadata['subtype'] ?? null) === 'track';
    });

    // Pre-load album data for tracks
    $trackIds = $tracks->pluck('id')->toArray();
    if (!empty($trackIds)) {
        $albums = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'album')
            ->whereHas('connectionsAsSubject', function ($query) use ($trackIds) {
                $query->whereIn('child_id', $trackIds)
                    ->whereHas('type', function ($q) {
                        $q->where('type', 'contains');
                    });
            })
            ->with(['connectionsAsSubject' => function ($query) use ($trackIds) {
                $query->whereIn('child_id', $trackIds)
                    ->whereHas('type', function ($q) {
                        $q->where('type', 'contains');
                    });
            }])
            ->get();

        // Create a lookup map for tracks to albums
        $trackToAlbumMap = [];
        foreach ($albums as $album) {
            foreach ($album->connectionsAsSubject as $connection) {
                $trackToAlbumMap[$connection->child_id] = $album;
            }
        }

        // Attach album data to tracks
        $tracks->each(function ($track) use ($trackToAlbumMap) {
            $track->cached_album = $trackToAlbumMap[$track->id] ?? null;
        });
    }

    // Attach the pre-loaded tracks to the set
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
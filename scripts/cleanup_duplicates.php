<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Starting duplicate span cleanup...\n";

// Find all duplicate spans by name and type
$duplicates = DB::select("
    SELECT name, type_id, COUNT(*) as count, MIN(created_at) as first_created
    FROM spans 
    WHERE type_id IN ('person', 'thing', 'organisation', 'place', 'event', 'band', 'set')
    GROUP BY name, type_id 
    HAVING COUNT(*) > 1
    ORDER BY name, type_id
");

echo "Found " . count($duplicates) . " groups of duplicates:\n";

$totalDeleted = 0;

foreach ($duplicates as $duplicate) {
    echo "\nProcessing: {$duplicate->name} ({$duplicate->type_id}) - {$duplicate->count} duplicates\n";
    
    // Get all spans with this name and type, ordered by creation date
    $spans = DB::table('spans')
        ->where('name', $duplicate->name)
        ->where('type_id', $duplicate->type_id)
        ->orderBy('created_at')
        ->get();
    
    // Keep the first one (oldest)
    $keepSpan = $spans->first();
    $deleteSpans = $spans->slice(1);
    
    echo "  Keeping: ID {$keepSpan->id} (created: {$keepSpan->created_at})\n";
    echo "  Deleting: " . $deleteSpans->count() . " duplicates\n";
    
    foreach ($deleteSpans as $deleteSpan) {
        echo "    - Deleting ID {$deleteSpan->id} (created: {$deleteSpan->created_at})\n";
        
        // Delete associated connections first
        $connectionsDeleted = DB::table('connections')
            ->where('parent_id', $deleteSpan->id)
            ->orWhere('child_id', $deleteSpan->id)
            ->delete();
        
        if ($connectionsDeleted > 0) {
            echo "      Deleted {$connectionsDeleted} connections\n";
        }
        
        // Delete the span
        DB::table('spans')->where('id', $deleteSpan->id)->delete();
        $totalDeleted++;
    }
}

echo "\nCleanup complete! Deleted {$totalDeleted} duplicate spans.\n";

// Show final count
$finalCount = DB::table('spans')->count();
echo "Total spans remaining: {$finalCount}\n"; 
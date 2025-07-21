<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Str;

echo "Testing connection types...\n";

// Check available connection types
$connectionTypes = ConnectionType::all();
echo "Available connection types:\n";
foreach ($connectionTypes as $ct) {
    echo "- {$ct->type} ({$ct->forward_predicate})\n";
}

// Test creating a simple connection
echo "\nTesting connection creation...\n";

try {
    // Create test spans
    $person = Span::create([
        'id' => Str::uuid(),
        'type_id' => 'person',
        'name' => 'Test Person',
        'state' => 'placeholder',
        'owner_id' => 1,
        'updater_id' => 1,
    ]);
    
    $thing = Span::create([
        'id' => Str::uuid(),
        'type_id' => 'thing',
        'name' => 'Test Thing',
        'state' => 'placeholder',
        'owner_id' => 1,
        'updater_id' => 1,
    ]);
    
    // Find connection type
    $connectionType = ConnectionType::where('type', 'created')->first();
    if (!$connectionType) {
        throw new Exception("Connection type 'created' not found");
    }
    
    // Create connection span
    $connectionSpan = Span::create([
        'id' => Str::uuid(),
        'type_id' => 'connection',
        'name' => "Connection: Test Person created Test Thing",
        'owner_id' => 1,
        'updater_id' => 1,
        'metadata' => [
            'connection_type' => 'created',
            'subject_role' => 'person',
            'object_role' => 'thing'
        ]
    ]);
    
    // Create connection
    $connection = Connection::create([
        'id' => Str::uuid(),
        'type_id' => $connectionType->type,
        'parent_id' => $person->id,
        'child_id' => $thing->id,
        'connection_span_id' => $connectionSpan->id
    ]);
    
    echo "✓ Connection created successfully!\n";
    echo "  - Type: {$connection->type_id}\n";
    echo "  - Parent: {$person->name}\n";
    echo "  - Child: {$thing->name}\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
} 
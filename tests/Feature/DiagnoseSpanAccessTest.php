<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;

class DiagnoseSpanAccessTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_diagnose_connection_span_access(): void
    {
        // Create a user
        $user = User::factory()->create(['is_admin' => false]);
        
        // Create two person spans
        $subject = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => 'Richard Northover'
        ]);
        
        $object = Span::factory()->create([
            'type_id' => 'place',
            'owner_id' => $user->id,
            'access_level' => 'public',
            'name' => "St Saviour's"
        ]);
        
        // Create a connection span (like "studied at") - use unique slug to avoid collisions
        $uniqueSlug = $this->uniqueSlug('richard-northover-studied-at-st-saviours');
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => "Richard Northover studied at St Saviour's",
            'slug' => $uniqueSlug
        ]);
        
        // Create the connection
        $connection = \App\Models\Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        // Test accessing the connection span as the owner
        $response = $this->actingAs($user)
            ->get('/spans/' . $uniqueSlug);
        
        echo "Response status: " . $response->status() . PHP_EOL;
        echo "Connection Span Owner ID: " . $connectionSpan->owner_id . PHP_EOL;
        echo "Connection Span Access Level: " . $connectionSpan->access_level . PHP_EOL;
        echo "Connection Span Type: " . $connectionSpan->type_id . PHP_EOL;
        echo "User ID: " . $user->id . PHP_EOL;
        echo "User is_admin: " . ($user->is_admin ? 'yes' : 'no') . PHP_EOL;
        
        $this->assertEquals(200, $response->status());
    }
}

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
        
        // Test accessing the connection span as the owner. Use canonical triple URL so we assert
        // the same access as production (slug URL redirects to triple URL).
        $connection->load(['subject', 'object', 'type']);
        $predicate = $connection->parent_id === $connection->subject->id
            ? str_replace(' ', '-', $connection->type->forward_predicate)
            : str_replace(' ', '-', $connection->type->inverse_predicate);
        $url = route('spans.connection', [
            'subject' => $connection->subject,
            'predicate' => $predicate,
            'object' => $connection->object,
        ]);
        $response = $this->actingAs($user)->get($url);

        $this->assertEquals(200, $response->status());
    }
}

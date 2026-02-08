<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionSpanAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_access_connection_span_if_they_own_parent_and_child(): void
    {
        // Create a user
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        
        // Create two private person spans owned by the user
        $subject = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => 'Richard Northover'
        ]);
        
        $object = Span::factory()->create([
            'type_id' => 'place',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => "St Saviour's"
        ]);
        
        // Create a connection span (different owner; connection span is public so triple URL is accessible)
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $otherUser->id,
            'updater_id' => $otherUser->id,
            'access_level' => 'public',
            'name' => "Richard Northover studied at St Saviour's",
            'slug' => 'richard-northover-studied-at-st-saviours'
        ]);
        
        // Create the connection linking the spans
        $connection = Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        // Test: User should be able to access the connection span because they own both parent and child.
        // Use canonical 4-segment URL (short_id).
        $connection->load(['subject', 'object', 'type', 'connectionSpan']);
        $predicate = $connection->parent_id === $connection->subject->id
            ? str_replace(' ', '-', $connection->type->forward_predicate)
            : str_replace(' ', '-', $connection->type->inverse_predicate);
        $url = route('spans.connection.by-id', [
            'subject' => $connection->subject,
            'predicate' => $predicate,
            'object' => $connection->object,
            'shortId' => $connection->connectionSpan->short_id,
        ]);
        $response = $this->actingAs($user)->get($url);

        $this->assertEquals(200, $response->status());
    }

    public function test_user_cannot_access_connection_span_if_they_cannot_access_child(): void
    {
        // Create users
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        
        // User owns parent span
        $subject = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => 'Richard Northover'
        ]);
        
        // Other user owns child span (user cannot access)
        $object = Span::factory()->create([
            'type_id' => 'place',
            'owner_id' => $otherUser->id,
            'access_level' => 'private',
            'name' => "St Saviour's"
        ]);
        
        // Connection span
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $otherUser->id,
            'updater_id' => $otherUser->id,
            'access_level' => 'private',
            'name' => "Richard Northover studied at St Saviour's",
            'slug' => 'richard-northover-studied-at-st-saviours-2'
        ]);
        
        // Create connection
        Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        // Test: User should NOT be able to access because they cannot access the child span.
        // span.access middleware returns 403 when resolving the object in the triple URL.
        $conn = Connection::where('connection_span_id', $connectionSpan->id)->with(['subject', 'object', 'type'])->first();
        $predicate = $conn->parent_id === $conn->subject->id
            ? str_replace(' ', '-', $conn->type->forward_predicate)
            : str_replace(' ', '-', $conn->type->inverse_predicate);
        $url = route('spans.connection', [
            'subject' => $conn->subject,
            'predicate' => $predicate,
            'object' => $conn->object,
        ]);
        $response = $this->actingAs($user)->get($url);
        $this->assertEquals(403, $response->status());
    }

    public function test_user_can_access_connection_span_if_child_is_public(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create(['is_admin' => false]);
        
        // User owns parent span
        $subject = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => 'Richard Northover'
        ]);
        
        // Child span is public (anyone can access)
        $object = Span::factory()->create([
            'type_id' => 'place',
            'owner_id' => $otherUser->id,
            'access_level' => 'public',
            'name' => "London"
        ]);
        
        // Connection span with different owner (public so triple URL is accessible)
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $otherUser->id,
            'updater_id' => $otherUser->id,
            'access_level' => 'public',
            'name' => "Richard Northover visited London",
            'slug' => 'richard-northover-visited-london'
        ]);
        
        Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        // Test: User should be able to access because parent is theirs and child is public.
        // Use canonical 4-segment URL (short_id).
        $conn = Connection::where('connection_span_id', $connectionSpan->id)->with(['subject', 'object', 'type', 'connectionSpan'])->first();
        $predicate = $conn->parent_id === $conn->subject->id
            ? str_replace(' ', '-', $conn->type->forward_predicate)
            : str_replace(' ', '-', $conn->type->inverse_predicate);
        $url = route('spans.connection.by-id', [
            'subject' => $conn->subject,
            'predicate' => $predicate,
            'object' => $conn->object,
            'shortId' => $conn->connectionSpan->short_id,
        ]);
        $response = $this->actingAs($user)->get($url);

        $this->assertEquals(200, $response->status());
    }
}

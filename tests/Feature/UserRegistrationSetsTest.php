<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRegistrationSetsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function default_sets_are_created_during_user_registration()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Test User';
        $personalSpan->save();
        $user->refresh();

        // Check that default sets were created
        $starredSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->whereJsonContains('metadata->subtype', 'starred')
            ->first();

        $desertIslandDiscsSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
            ->first();

        $this->assertNotNull($starredSet, 'Starred set should be created during registration');
        $this->assertNotNull($desertIslandDiscsSet, 'Desert Island Discs set should be created during registration');

        // Check that sets have correct properties
        $this->assertEquals('Starred', $starredSet->name);
        $this->assertEquals('Your starred items', $starredSet->description);
        $this->assertEquals('bi-star-fill', $starredSet->metadata['icon']);
        $this->assertEquals('test-user-starred', $starredSet->slug);

        $this->assertEquals('Desert Island Discs', $desertIslandDiscsSet->name);
        $this->assertEquals('Your desert island discs', $desertIslandDiscsSet->description);
        $this->assertEquals('bi-music-note-beamed', $desertIslandDiscsSet->metadata['icon']);
        $this->assertEquals('test-user-desert-island-discs', $desertIslandDiscsSet->slug);
    }

    /** @test */
    public function created_connections_are_made_from_personal_span_to_sets()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Test User';
        $personalSpan->save();
        $user->refresh();

        // Get the default sets
        $starredSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->subtype', 'starred')
            ->first();

        $desertIslandDiscsSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
            ->first();

        // Check that "created" connections exist from personal span to sets
        $starredConnection = Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $starredSet->id)
            ->where('type_id', 'created')
            ->first();

        $desertIslandDiscsConnection = Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $desertIslandDiscsSet->id)
            ->where('type_id', 'created')
            ->first();

        $this->assertNotNull($starredConnection, 'Created connection should exist from personal span to starred set');
        $this->assertNotNull($desertIslandDiscsConnection, 'Created connection should exist from personal span to desert island discs set');

        // Check that the connections have the correct metadata
        $this->assertEquals('starred', $starredConnection->metadata['set_type']);
        $this->assertEquals('desert-island-discs', $desertIslandDiscsConnection->metadata['set_type']);
    }

    /** @test */
    public function sets_show_on_personal_span_page()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Test User';
        $personalSpan->save();
        $user->refresh();

        // Check that the desert island discs set is found for the personal span
        $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($personalSpan);
        
        $this->assertNotNull($desertIslandDiscsSet, 'Desert Island Discs set should be found for personal span');
        $this->assertEquals('Desert Island Discs', $desertIslandDiscsSet->name);
    }

    /** @test */
    public function ensure_default_sets_exist_creates_missing_sets()
    {
        // Create a user without default sets (by deleting them after creation)
        $user = User::factory()->create();
        
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Test User';
        $personalSpan->save();
        $user->refresh();

        // Delete the default sets
        Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->delete();

        // Delete the connections
        Connection::where('parent_id', $personalSpan->id)
            ->where('type_id', 'created')
            ->delete();

        // Verify sets are gone
        $this->assertNull(Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->subtype', 'starred')
            ->first());

        // Call ensureDefaultSetsExist
        $user->ensureDefaultSetsExist();

        // Verify sets are recreated
        $starredSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->subtype', 'starred')
            ->first();

        $desertIslandDiscsSet = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
            ->first();

        $this->assertNotNull($starredSet, 'Starred set should be recreated');
        $this->assertNotNull($desertIslandDiscsSet, 'Desert Island Discs set should be recreated');

        // Verify connections are recreated
        $this->assertNotNull(Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $starredSet->id)
            ->where('type_id', 'created')
            ->first());
    }
} 
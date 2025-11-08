<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Str;

class SpanTest extends TestCase
{
    public function test_set_slugs_include_owner_name()
    {
        // Create a user
        $user = User::factory()->create();
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Richard Northover';
        $personalSpan->save();
        $user->refresh();

        // Create a set
        $set = Span::factory()->create([
            'name' => 'Desert Island Discs',
            'type_id' => 'set',
            'owner_id' => $user->id,
            'slug' => null // Let it auto-generate
        ]);

        // The slug should include the owner's name
        $this->assertEquals('richard-northover-desert-island-discs', $set->slug);
    }

    public function test_non_set_slugs_dont_include_owner_name()
    {
        // Create a user
        $user = User::factory()->create();
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Richard Northover';
        $personalSpan->save();
        $user->refresh();

        // Create a person span (not a set)
        $person = Span::factory()->create([
            'name' => 'John Doe',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'slug' => null // Let it auto-generate
        ]);

        // The slug should NOT include the owner's name
        $this->assertEquals('john-doe', $person->slug);
    }

    public function test_default_sets_are_created_with_icons()
    {
        // Create a user
        $user = User::factory()->create();
        // Set the name on the user's existing personal span
        $personalSpan = $user->personalSpan;
        $personalSpan->name = 'Richard Northover';
        $personalSpan->save();
        $user->refresh();

        // Get default sets (this should create them)
        $defaultSets = Span::getDefaultSets($user);

        // Should have at least 2 traditional default sets (plus smart sets)
        $this->assertGreaterThanOrEqual(2, $defaultSets->count());

        // Check Starred set
        $starredSet = $defaultSets->where('name', 'Starred')->first();
        $this->assertNotNull($starredSet);
        $this->assertEquals('bi-star-fill', $starredSet->metadata['icon']);
        $this->assertTrue($starredSet->metadata['is_default']);
        $this->assertEquals('Your starred items', $starredSet->description);

        // Check Desert Island Discs set
        $expectedDesertIslandDiscsName = $personalSpan->name . "'s Desert Island Discs";
        $desertIslandSet = $defaultSets->where('name', $expectedDesertIslandDiscsName)->first();
        $this->assertNotNull($desertIslandSet);
        $this->assertEquals('bi-music-note-beamed', $desertIslandSet->metadata['icon']);
        $this->assertTrue($desertIslandSet->metadata['is_default']);
        $this->assertEquals('Your desert island discs', $desertIslandSet->description);
    }

    public function test_default_sets_have_correct_slugs()
    {
        // Create a user
        $user = User::factory()->create();
        // Get the actual name used for the personal span
        $actualName = $user->personalSpan->name;

        // Now get default sets (this should create them if needed)
        $defaultSets = Span::getDefaultSets($user);

        // Check Starred set slug - should include the user's name (from personal span)
        $starredSet = $defaultSets->where('name', 'Starred')->first();
        $this->assertNotNull($starredSet, 'Starred set should exist');
        $expectedStarredSlug = Str::slug($actualName) . '-starred';
        $this->assertEquals($expectedStarredSlug, $starredSet->slug);

        // Check Desert Island Discs set slug - should include the user's name (from personal span)
        $expectedDesertIslandDiscsName = $actualName . "'s Desert Island Discs";
        $desertIslandSet = $defaultSets->where('name', $expectedDesertIslandDiscsName)->first();
        $this->assertNotNull($desertIslandSet, 'Desert Island Discs set should exist');
        $expectedDesertIslandSlug = Str::slug($actualName) . '-desert-island-discs';
        $this->assertEquals($expectedDesertIslandSlug, $desertIslandSet->slug);
    }

    public function test_get_or_create_public_desert_island_discs_set()
    {
        // Create a person span
        $user = User::factory()->create();
        $person = Span::create([
            'name' => 'John Doe',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1980,
            'state' => 'complete',
            'access_level' => 'public'
        ]);

        // Get or create the Desert Island Discs set
        $set = Span::getOrCreatePublicDesertIslandDiscsSet($person);

        // Verify the set was created correctly
        $this->assertEquals('Desert Island Discs', $set->name);
        $this->assertEquals('set', $set->type_id);
        $this->assertEquals('public', $set->access_level);
        $this->assertTrue($set->metadata['is_public_desert_island_discs']);
        $this->assertEquals('bi-music-note-beamed', $set->metadata['icon']);
        $this->assertEquals('desertislanddiscs', $set->metadata['subtype']);

        // Verify the set is owned by system user
        $systemUser = User::where('email', 'system@lifespan.app')->first();
        $this->assertEquals($systemUser->id, $set->owner_id);

        // Verify the created connection exists
        $connection = $person->connectionsAsSubject()
            ->where('type_id', 'created')
            ->where('child_id', $set->id)
            ->first();
        
        $this->assertNotNull($connection);

        // Test that calling it again returns the same set
        $set2 = Span::getOrCreatePublicDesertIslandDiscsSet($person);
        $this->assertEquals($set->id, $set2->id);

        // Verify no duplicate connections were created
        $connectionCount = $person->connectionsAsSubject()
            ->where('type_id', 'created')
            ->where('child_id', $set->id)
            ->count();
        
        $this->assertEquals(1, $connectionCount);
    }
} 
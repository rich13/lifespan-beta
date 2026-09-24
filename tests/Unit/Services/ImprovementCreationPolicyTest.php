<?php

namespace Tests\Unit\Services;

use App\Models\Span;
use App\Models\User;
use App\Services\ImprovementCreationPolicy;
use Tests\TestCase;

class ImprovementCreationPolicyTest extends TestCase
{
    public function test_only_an_original_span_may_create_children_and_a_run_stops_at_the_cap(): void
    {
        config(['services.improvement.max_new_spans_per_run' => 1]);
        $owner = User::factory()->create();
        $policy = app(ImprovementCreationPolicy::class);

        $original = Span::create([
            'name' => 'Original',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'improvement_generation' => 0,
            'improvement_mode' => 'auto',
        ]);
        $child = Span::create([
            'name' => 'Already A Child',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'improvement_generation' => 1,
        ]);

        $this->assertFalse($policy->allowsCreation($child));
        $this->assertNull($policy->createChild($child, [
            'name' => 'Grandchild',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
        ]));

        $policy->beginRun();
        $created = $policy->createChild($original, [
            'name' => 'Photo of Original',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => ['subtype' => 'photo'],
        ]);
        $this->assertNotNull($created);
        $this->assertSame(1, $created->improvement_generation);
        $this->assertSame('defer', $created->improvement_mode);
        $this->assertSame($original->id, $created->improvement_parent_id);
        $this->assertNull($policy->createChild($original, [
            'name' => 'Another photo',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
        ]));

        $policy->endRun();
        $this->assertNotNull($policy->createChild($original, [
            'name' => 'Allowed outside a run',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
        ]));
    }

    public function test_a_run_stops_one_span_at_its_own_child_cap_without_counting_connections(): void
    {
        config([
            'services.improvement.max_new_spans_per_run' => 10,
            'services.improvement.max_child_spans' => 1,
        ]);
        $owner = User::factory()->create();
        $policy = app(ImprovementCreationPolicy::class);
        $original = Span::create([
            'name' => 'Parent',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'improvement_generation' => 0,
        ]);

        $policy->beginRun();
        $photo = $policy->createChild($original, [
            'name' => 'Photo of Parent',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
        ]);
        $connection = $policy->createChild($original, [
            'name' => 'Photo features Parent',
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => [
                'connection_type' => 'features',
                'timeless' => true,
            ],
        ]);
        $another = $policy->createChild($original, [
            'name' => 'Second photo',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
        ]);
        $policy->endRun();

        $this->assertNotNull($photo);
        $this->assertNotNull($connection);
        $this->assertNull($another);
    }
}

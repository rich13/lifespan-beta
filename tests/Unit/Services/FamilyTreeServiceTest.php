<?php

namespace Tests\Unit\Services;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use App\Services\FamilyTreeService;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FamilyTreeServiceTest extends TestCase
{

    private FamilyTreeService $service;
    private User $user;
    private Span $grandparent1;
    private Span $grandparent2;
    private Span $parent1;
    private Span $parent2;
    private Span $child;
    private Span $sibling;
    private Span $cousin;
    private Span $uncle;
    private Span $grandchild;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        if (!DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'connection')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A connection between spans',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create the family connection type with proper configuration
        DB::table('connection_types')->updateOrInsert(
            ['type' => 'family'],
            [
                'forward_predicate' => 'is family of',
                'forward_description' => 'Is a family member of',
                'inverse_predicate' => 'is family of',
                'inverse_description' => 'Is a family member of',
                'constraint_type' => 'single',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['person']
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        $this->service = new FamilyTreeService();
        $this->user = User::factory()->create();

        // Create family members
        $this->grandparent1 = Span::create([
            'name' => 'Grandparent 1',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1900,
            'access_level' => 'public'
        ]);

        $this->grandparent2 = Span::create([
            'name' => 'Grandparent 2',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1905,
            'access_level' => 'public'
        ]);

        $this->parent1 = Span::create([
            'name' => 'Parent 1',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1930,
            'access_level' => 'public'
        ]);

        $this->parent2 = Span::create([
            'name' => 'Parent 2',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1935,
            'access_level' => 'public'
        ]);

        $this->uncle = Span::create([
            'name' => 'Uncle',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1932,
            'access_level' => 'public'
        ]);

        $this->cousin = Span::create([
            'name' => 'Cousin',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1955,
            'access_level' => 'public'
        ]);

        $this->child = Span::create([
            'name' => 'Child',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1960,
            'access_level' => 'public'
        ]);

        $this->sibling = Span::create([
            'name' => 'Sibling',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1962,
            'access_level' => 'public'
        ]);

        $this->grandchild = Span::create([
            'name' => 'Grandchild',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1980,
            'access_level' => 'public'
        ]);

        // Create family connections
        $this->createFamilyConnection($this->grandparent1, $this->parent1, 1930);
        $this->createFamilyConnection($this->grandparent2, $this->parent1, 1930);
        $this->createFamilyConnection($this->grandparent1, $this->uncle, 1932);
        $this->createFamilyConnection($this->parent1, $this->child, 1960);
        $this->createFamilyConnection($this->parent2, $this->child, 1960);
        $this->createFamilyConnection($this->parent1, $this->sibling, 1962);
        $this->createFamilyConnection($this->uncle, $this->cousin, 1955);
        $this->createFamilyConnection($this->child, $this->grandchild, 1980);
    }

    private function createFamilyConnection(Span $parent, Span $child, int $startYear): void
    {
        // Create the connection span
        $connectionSpan = Span::create([
            'name' => "{$parent->name} - {$child->name} Family Connection",
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => $child->start_year,
            'start_month' => $child->start_month,
            'start_day' => $child->start_day,
            'access_level' => 'public'
        ]);

        // Create the connection
        Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
            'metadata' => [
                'relationship_type' => 'parent'
            ]
        ]);
    }

    private function debugDatabaseState(): void
    {
        if (env('APP_DEBUG')) {
            $connections = DB::table('connections')
                ->join('spans as parent_spans', 'connections.parent_id', '=', 'parent_spans.id')
                ->join('spans as child_spans', 'connections.child_id', '=', 'child_spans.id')
                ->select([
                    'connections.*',
                    'parent_spans.name as parent_name',
                    'child_spans.name as child_name'
                ])
                ->where('connections.type_id', 'family')
                ->get();

            Log::debug("Connections in database:");
            foreach ($connections as $connection) {
                Log::debug(sprintf(
                    "- %s -> %s (Connection ID: %s)",
                    $connection->parent_name,
                    $connection->child_name,
                    $connection->id
                ));
            }
        }
    }

    public function test_gets_parents(): void
    {
        $this->debugDatabaseState();
        $parents = $this->service->getParents($this->child);
        
        $this->assertCount(2, $parents);
        $this->assertTrue($parents->contains(function ($parent) {
            return $parent->id === $this->parent1->id;
        }));
        $this->assertTrue($parents->contains(function ($parent) {
            return $parent->id === $this->parent2->id;
        }));
    }

    public function test_gets_grandparents(): void
    {
        $grandparents = $this->service->getGrandparents($this->child);
        
        $this->assertCount(2, $grandparents);
        $this->assertTrue($grandparents->contains(function ($grandparent) {
            return $grandparent->id === $this->grandparent1->id;
        }));
        $this->assertTrue($grandparents->contains(function ($grandparent) {
            return $grandparent->id === $this->grandparent2->id;
        }));
    }

    public function test_gets_siblings(): void
    {
        $siblings = $this->service->getSiblings($this->child);
        
        $this->assertCount(1, $siblings);
        $this->assertTrue($siblings->contains(function ($sibling) {
            return $sibling->id === $this->sibling->id;
        }));
    }

    public function test_gets_uncles_and_aunts(): void
    {
        $unclesAndAunts = $this->service->getUnclesAndAunts($this->child);
        
        $this->assertCount(1, $unclesAndAunts);
        $this->assertTrue($unclesAndAunts->contains(function ($uncleAunt) {
            return $uncleAunt->id === $this->uncle->id;
        }));
    }

    public function test_gets_cousins(): void
    {
        $cousins = $this->service->getCousins($this->child);
        
        $this->assertCount(1, $cousins);
        $this->assertTrue($cousins->contains(function ($cousin) {
            return $cousin->id === $this->cousin->id;
        }));
    }

    public function test_gets_ancestors(): void
    {
        $ancestors = $this->service->getAncestors($this->child);
        
        $this->assertCount(4, $ancestors);
        $this->assertTrue($ancestors->contains(function ($ancestor) {
            return $ancestor['span']->id === $this->parent1->id;
        }));
        $this->assertTrue($ancestors->contains(function ($ancestor) {
            return $ancestor['span']->id === $this->parent2->id;
        }));
        $this->assertTrue($ancestors->contains(function ($ancestor) {
            return $ancestor['span']->id === $this->grandparent1->id;
        }));
        $this->assertTrue($ancestors->contains(function ($ancestor) {
            return $ancestor['span']->id === $this->grandparent2->id;
        }));
    }

    public function test_gets_descendants(): void
    {
        $descendants = $this->service->getDescendants($this->grandparent1);
        
        // Debug output
        echo "\nDescendants of {$this->grandparent1->name}:\n";
        foreach ($descendants as $descendant) {
            echo "- {$descendant['span']->name} (ID: {$descendant['span']->id}, Generation: {$descendant['generation']})\n";
        }
        
        $this->assertCount(5, $descendants);
        $this->assertTrue($descendants->contains(function ($descendant) {
            return $descendant['span']->id === $this->parent1->id;
        }));
        $this->assertTrue($descendants->contains(function ($descendant) {
            return $descendant['span']->id === $this->uncle->id;
        }));
        $this->assertTrue($descendants->contains(function ($descendant) {
            return $descendant['span']->id === $this->child->id;
        }));
        $this->assertTrue($descendants->contains(function ($descendant) {
            return $descendant['span']->id === $this->cousin->id;
        }));
        $this->assertTrue($descendants->contains(function ($descendant) {
            return $descendant['span']->id === $this->sibling->id;
        }));
    }
} 
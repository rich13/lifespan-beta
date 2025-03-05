<?php

namespace App\Models\Traits;

use App\Models\Connection;
use App\Models\Span;
use App\Services\FamilyTreeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Trait for managing family relationships between spans.
 * This trait provides functionality for handling family connections
 * between person spans.
 */
trait HasFamilyCapabilities
{
    protected ?FamilyTreeService $familyTreeService = null;

    /**
     * Get the family tree service instance
     */
    protected function getFamilyTreeService(): FamilyTreeService
    {
        if (!$this->familyTreeService) {
            $this->familyTreeService = new FamilyTreeService();
        }
        return $this->familyTreeService;
    }

    /**
     * Get all family relationships where this person is a parent
     */
    public function asParentConnections()
    {
        return $this->hasMany(Connection::class, 'parent_id')
            ->where('type_id', 'family');
    }

    /**
     * Get all family relationships where this person is a child
     */
    public function asChildConnections()
    {
        return $this->hasMany(Connection::class, 'child_id')
            ->where('type_id', 'family');
    }

    /**
     * Get all parents of this person
     */
    public function parents()
    {
        return $this->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
            ->where('connections.type_id', 'family');
    }

    /**
     * Get all children of this person
     */
    public function children()
    {
        return $this->belongsToMany(Span::class, 'connections', 'parent_id', 'child_id')
            ->where('connections.type_id', 'family');
    }

    /**
     * Get all siblings of this person (same parents)
     */
    public function siblings()
    {
        return $this->getFamilyTreeService()->getSiblings($this);
    }

    /**
     * Get all ancestors up to a certain number of generations
     */
    public function ancestors(int $generations = 2): Collection
    {
        $ancestors = $this->getFamilyTreeService()->getAncestors($this, $generations);
        return Collection::make($ancestors->map(function($item) {
            return $item['span'];
        }));
    }

    /**
     * Get all descendants up to a certain number of generations
     */
    public function descendants(int $generations = 2): Collection
    {
        $descendants = $this->getFamilyTreeService()->getDescendants($this, $generations);
        return Collection::make($descendants->map(function($item) {
            return $item['span'];
        }));
    }

    /**
     * Get uncles and aunts (siblings of parents)
     */
    public function unclesAndAunts(): Collection
    {
        $unclesAndAunts = $this->getFamilyTreeService()->getUnclesAndAunts($this);
        return new Collection($unclesAndAunts->all());
    }

    /**
     * Get cousins (children of uncles and aunts)
     */
    public function cousins(): Collection
    {
        $cousins = $this->getFamilyTreeService()->getCousins($this);
        return new Collection($cousins->all());
    }

    /**
     * Get nephews and nieces (children of siblings)
     */
    public function nephewsAndNieces(): Collection
    {
        $nephewsAndNieces = $this->getFamilyTreeService()->getNephewsAndNieces($this);
        return new Collection($nephewsAndNieces->all());
    }

    /**
     * Add a parent to this person
     */
    public function addParent(Span $parent, array $metadata = [])
    {
        if ($parent->type_id !== 'person') {
            throw new \InvalidArgumentException('Parent must be a person span');
        }

        // Check if relationship already exists
        if ($this->parents()->where('spans.id', $parent->id)->exists()) {
            Log::info('Parent relationship already exists', [
                'child' => $this->id,
                'parent' => $parent->id
            ]);
            return;
        }

        // Create the connection
        Connection::create([
            'type_id' => 'family',
            'parent_id' => $parent->id,
            'child_id' => $this->id,
            'metadata' => array_merge([
                'relationship_type' => 'parent'
            ], $metadata)
        ]);

        Log::info('Added parent relationship', [
            'child' => $this->id,
            'parent' => $parent->id,
            'metadata' => $metadata
        ]);
    }

    /**
     * Add a child to this person
     */
    public function addChild(Span $child, array $metadata = [])
    {
        if ($child->type_id !== 'person') {
            throw new \InvalidArgumentException('Child must be a person span');
        }

        // Check if relationship already exists
        if ($this->children()->where('spans.id', $child->id)->exists()) {
            Log::info('Child relationship already exists', [
                'parent' => $this->id,
                'child' => $child->id
            ]);
            return;
        }

        // Create the connection
        Connection::create([
            'type_id' => 'family',
            'parent_id' => $this->id,
            'child_id' => $child->id,
            'metadata' => array_merge([
                'relationship_type' => 'child'
            ], $metadata)
        ]);

        Log::info('Added child relationship', [
            'parent' => $this->id,
            'child' => $child->id,
            'metadata' => $metadata
        ]);
    }

    /**
     * Remove a parent relationship
     */
    public function removeParent(Span $parent)
    {
        $this->asChildConnections()
            ->where('parent_id', $parent->id)
            ->delete();

        Log::info('Removed parent relationship', [
            'child' => $this->id,
            'parent' => $parent->id
        ]);
    }

    /**
     * Remove a child relationship
     */
    public function removeChild(Span $child)
    {
        $this->asParentConnections()
            ->where('child_id', $child->id)
            ->delete();

        Log::info('Removed child relationship', [
            'parent' => $this->id,
            'child' => $child->id
        ]);
    }

    /**
     * Get the relationship type with another person
     */
    public function getRelationshipWith(Span $person): ?string
    {
        if ($this->parents()->where('spans.id', $person->id)->exists()) {
            return 'parent';
        }
        if ($this->children()->where('spans.id', $person->id)->exists()) {
            return 'child';
        }
        if ($this->siblings()->where('spans.id', $person->id)->exists()) {
            return 'sibling';
        }
        if ($this->ancestors(2)->where('id', $person->id)->isNotEmpty()) {
            return 'grandparent';
        }
        if ($this->descendants(2)->where('id', $person->id)->isNotEmpty()) {
            return 'grandchild';
        }
        if ($this->unclesAndAunts()->where('id', $person->id)->isNotEmpty()) {
            return 'uncle/aunt';
        }
        if ($this->cousins()->where('id', $person->id)->isNotEmpty()) {
            return 'cousin';
        }
        if ($this->nephewsAndNieces()->where('id', $person->id)->isNotEmpty()) {
            return 'nephew/niece';
        }
        return null;
    }

    /**
     * Validate family metadata
     */
    protected function validateFamilyMetadata()
    {
        if ($this->type_id !== 'person') {
            return;
        }

        $metadata = $this->metadata ?? [];

        // Validate relationship metadata if it exists
        if (isset($metadata['family'])) {
            $familyData = $metadata['family'];

            // Validate birth order if set
            if (isset($familyData['birth_order']) && !is_int($familyData['birth_order'])) {
                throw new \InvalidArgumentException('Birth order must be an integer');
            }

            // Validate relationship types
            if (isset($familyData['relationships'])) {
                foreach ($familyData['relationships'] as $relationship) {
                    if (!in_array($relationship['type'], ['biological', 'adopted', 'step', 'foster'])) {
                        throw new \InvalidArgumentException('Invalid relationship type: ' . $relationship['type']);
                    }
                }
            }
        }

        Log::debug('Family metadata validation passed', [
            'span_id' => $this->id,
            'metadata' => $metadata
        ]);
    }
}
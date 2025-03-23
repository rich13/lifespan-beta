<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FamilyTreeService
{
    /**
     * Get all ancestors of a person span up to a certain number of generations
     */
    public function getAncestors(Span $span, int $generations = 2): Collection
    {
        $ancestors = collect();
        $this->traverseAncestors($span, $ancestors, $generations);
        return $ancestors->unique(function ($item) {
            return $item['span']->id;
        })->values();
    }

    /**
     * Get all descendants of a person span up to a certain number of generations
     */
    public function getDescendants(Span $span, int $generations = 2): Collection
    {
        $descendants = collect();
        $this->traverseDescendants($span, $descendants, $generations);
        if (env('APP_DEBUG')) {
            Log::debug("Descendants of {$span->name}:");
            foreach ($descendants as $descendant) {
                Log::debug(sprintf("- %s (ID: %s, Generation: %d)", 
                    $descendant['span']->name, 
                    $descendant['span']->id, 
                    $descendant['generation']
                ));
            }
        }
        return $descendants->unique(function ($item) {
            return $item['span']->id;
        })->values();
    }

    /**
     * Get siblings of a person span (spans that share at least one parent)
     */
    public function getSiblings(Span $span): Collection
    {
        // Get parents
        $parents = $this->getParents($span);
        
        // Get all children of these parents except the current span
        return $parents->flatMap(function ($parent) {
            return $this->getChildren($parent);
        })->reject(function ($sibling) use ($span) {
            return $sibling->id === $span->id;
        })->unique('id')->values();
    }

    /**
     * Get immediate parents of a span
     */
    public function getParents(Span $span): Collection
    {
        return $span->parents()->get();
    }

    /**
     * Get grandparents of a span (parents of parents)
     */
    public function getGrandparents(Span $span): Collection
    {
        $parents = $this->getParents($span);
        if (env('APP_DEBUG')) {
            Log::debug("Parents:");
            foreach ($parents as $parent) {
                Log::debug(sprintf("- %s (ID: %s)", $parent->name, $parent->id));
                $parentParents = $this->getParents($parent);
                Log::debug("  Parents of {$parent->name}:");
                foreach ($parentParents as $grandparent) {
                    Log::debug(sprintf("  - %s (ID: %s)", $grandparent->name, $grandparent->id));
                }
            }
        }
        return $parents->flatMap(function ($parent) {
            return $this->getParents($parent);
        })->unique('id')->values();
    }

    /**
     * Get immediate children of a span
     */
    public function getChildren(Span $span): Collection
    {
        return $span->children()->get();
    }

    /**
     * Recursively traverse and collect ancestors
     */
    protected function traverseAncestors(Span $span, Collection &$ancestors, int $generations, int $currentGen = 1): void
    {
        if ($currentGen > $generations) {
            return;
        }

        $parents = $this->getParents($span);
        foreach ($parents as $parent) {
            $ancestors->push([
                'span' => $parent,
                'generation' => $currentGen,
                'relationship' => $currentGen === 1 ? 'parent' : 'ancestor'
            ]);
            $this->traverseAncestors($parent, $ancestors, $generations, $currentGen + 1);
        }
    }

    /**
     * Recursively traverse and collect descendants
     */
    protected function traverseDescendants(Span $span, Collection &$descendants, int $generations, int $currentGen = 1): void
    {
        if ($currentGen > $generations) {
            return;
        }

        $children = $this->getChildren($span);
        foreach ($children as $child) {
            $descendants->push([
                'span' => $child,
                'generation' => $currentGen,
                'relationship' => $currentGen === 1 ? 'child' : 'descendant'
            ]);
            $this->traverseDescendants($child, $descendants, $generations, $currentGen + 1);
        }
    }

    /**
     * Get uncles and aunts (siblings of parents and parents of cousins, excluding step-parents)
     */
    public function getUnclesAndAunts(Span $span): Collection
    {
        if (env('APP_DEBUG')) {
            Log::debug("Getting uncles and aunts for {$span->name} (ID: {$span->id})");
        }

        // Get siblings of parents (traditional uncles/aunts)
        $siblingsOfParents = $this->getParents($span)->flatMap(function ($parent) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting siblings of parent {$parent->name} (ID: {$parent->id})");
            }
            return $this->getSiblings($parent);
        });

        if (env('APP_DEBUG')) {
            Log::debug("Found " . $siblingsOfParents->count() . " siblings of parents for {$span->name}:");
            foreach ($siblingsOfParents as $uncle) {
                Log::debug("- {$uncle->name} (ID: {$uncle->id})");
            }
        }

        // Get my siblings to exclude their parents and themselves from cousins
        $siblings = $this->getSiblings($span);
        if (env('APP_DEBUG')) {
            Log::debug("Found " . $siblings->count() . " siblings to exclude:");
            foreach ($siblings as $sibling) {
                Log::debug("- {$sibling->name} (ID: {$sibling->id})");
            }
        }

        // Get parents of siblings to exclude from cousin calculation
        $parentsOfSiblings = $siblings->flatMap(function ($sibling) {
            return $this->getParents($sibling);
        })->unique('id')->values();

        if (env('APP_DEBUG')) {
            Log::debug("Found " . $parentsOfSiblings->count() . " parents of siblings to exclude:");
            foreach ($parentsOfSiblings as $parent) {
                Log::debug("- {$parent->name} (ID: {$parent->id})");
            }
        }

        // Get cousins through grandparents, excluding siblings and their descendants
        $cousins = $this->getParents($span)->flatMap(function ($parent) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting parents of parent {$parent->name} (ID: {$parent->id})");
            }
            return $this->getParents($parent);  // Get grandparents
        })->flatMap(function ($grandparent) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting descendants of grandparent {$grandparent->name} (ID: {$grandparent->id})");
            }
            return $this->getDescendants($grandparent, 2);  // Get 2 generations of descendants
        })->filter(function ($descendant) use ($span, $siblings) {
            // Keep only cousins (same generation as span)
            // Exclude self and siblings from cousin calculation
            return $descendant['generation'] === 2 
                && $descendant['span']->id !== $span->id
                && !$siblings->contains(function ($sibling) use ($descendant) {
                    return $sibling->id === $descendant['span']->id;
                });
        })->pluck('span');

        if (env('APP_DEBUG')) {
            Log::debug("Found " . $cousins->count() . " cousins for {$span->name}:");
            foreach ($cousins as $cousin) {
                Log::debug("- {$cousin->name} (ID: {$cousin->id})");
            }
        }

        // Get parents of cousins
        $parentsOfCousins = $cousins->flatMap(function ($cousin) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting parents of cousin {$cousin->name} (ID: {$cousin->id})");
            }
            return $this->getParents($cousin);
        })->reject(function ($potentialParent) use ($span, $parentsOfSiblings) {
            // Reject the person's own parents and parents of siblings
            return $this->getParents($span)->contains(function ($parent) use ($potentialParent) {
                    return $parent->id === $potentialParent->id;
                })
                || $parentsOfSiblings->contains(function ($parent) use ($potentialParent) {
                    return $parent->id === $potentialParent->id;
                });
        });

        if (env('APP_DEBUG')) {
            Log::debug("Found " . $parentsOfCousins->count() . " parents of cousins for {$span->name}:");
            foreach ($parentsOfCousins as $parent) {
                Log::debug("- {$parent->name} (ID: {$parent->id})");
            }
        }

        // Combine siblings of parents and parents of cousins
        return $siblingsOfParents->concat($parentsOfCousins)->unique('id')->values();
    }

    /**
     * Get cousins (children of uncles and aunts)
     */
    public function getCousins(Span $span): Collection
    {
        if (env('APP_DEBUG')) {
            Log::debug("Getting cousins for {$span->name} (ID: {$span->id})");
        }

        // Get siblings to exclude them from cousins
        $siblings = $this->getSiblings($span);
        if (env('APP_DEBUG')) {
            Log::debug("Found " . $siblings->count() . " siblings to exclude:");
            foreach ($siblings as $sibling) {
                Log::debug("- {$sibling->name} (ID: {$sibling->id})");
            }
        }

        // Get cousins through grandparents (same generation descendants)
        $result = $this->getParents($span)->flatMap(function ($parent) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting parents of parent {$parent->name} (ID: {$parent->id})");
            }
            return $this->getParents($parent);  // Get grandparents
        })->flatMap(function ($grandparent) {
            if (env('APP_DEBUG')) {
                Log::debug("Getting descendants of grandparent {$grandparent->name} (ID: {$grandparent->id})");
            }
            return $this->getDescendants($grandparent, 2);  // Get 2 generations of descendants
        })->filter(function ($descendant) use ($span, $siblings) {
            // Keep only cousins (same generation as span, not self or siblings)
            return $descendant['generation'] === 2 
                && $descendant['span']->id !== $span->id
                && !$siblings->contains(function ($sibling) use ($descendant) {
                    return $sibling->id === $descendant['span']->id;
                });
        })->pluck('span')->unique('id')->values();

        if (env('APP_DEBUG')) {
            Log::debug("Found " . $result->count() . " cousins for {$span->name}:");
            foreach ($result as $cousin) {
                Log::debug("- {$cousin->name} (ID: {$cousin->id})");
            }
        }

        return $result;
    }

    /**
     * Get nephews and nieces (children of siblings)
     */
    public function getNephewsAndNieces(Span $span): Collection
    {
        return $this->getSiblings($span)->flatMap(function ($sibling) {
            return $this->getChildren($sibling);
        })->unique('id')->values();
    }

    /**
     * Helper method to compare spans by ID
     */
    protected function compareSpans(Span $a, Span $b): bool
    {
        return $a->id === $b->id;
    }
} 
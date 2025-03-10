<?php

namespace App\Services;

use App\Models\Span;
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
     * Get uncles and aunts (siblings of parents)
     */
    public function getUnclesAndAunts(Span $span): Collection
    {
        return $this->getParents($span)->flatMap(function ($parent) {
            return $this->getSiblings($parent);
        })->unique('id')->values();
    }

    /**
     * Get cousins (children of uncles and aunts)
     */
    public function getCousins(Span $span): Collection
    {
        return $this->getUnclesAndAunts($span)->flatMap(function ($uncleAunt) {
            return $this->getChildren($uncleAunt);
        })->unique('id')->values();
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
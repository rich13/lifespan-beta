<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Collection;

class FamilyTreeService
{
    /**
     * Get all ancestors of a person span up to a certain number of generations
     */
    public function getAncestors(Span $span, int $generations = 2): Collection
    {
        $ancestors = collect();
        $this->traverseAncestors($span, $ancestors, $generations);
        return $ancestors;
    }

    /**
     * Get all descendants of a person span up to a certain number of generations
     */
    public function getDescendants(Span $span, int $generations = 2): Collection
    {
        $descendants = collect();
        $this->traverseDescendants($span, $descendants, $generations);
        return $descendants;
    }

    /**
     * Get siblings of a person span (spans that share at least one parent)
     */
    public function getSiblings(Span $span): Collection
    {
        // Get parents
        $parents = $this->getParents($span);
        
        // Get all children of these parents except the current span
        return $parents->flatMap(function ($parent) use ($span) {
            return $this->getChildren($parent);
        })->unique('id')->reject(function ($sibling) use ($span) {
            return $sibling->id === $span->id;
        });
    }

    /**
     * Get immediate parents of a span
     */
    protected function getParents(Span $span): Collection
    {
        return $span->connections()
            ->where('type_id', 'family')
            ->where('child_id', $span->id)
            ->get()
            ->map(function ($connection) {
                return $connection->parent;
            })
            ->filter();
    }

    /**
     * Get immediate children of a span
     */
    protected function getChildren(Span $span): Collection
    {
        return $span->connections()
            ->where('type_id', 'family')
            ->where('parent_id', $span->id)
            ->get()
            ->map(function ($connection) {
                return $connection->child;
            })
            ->filter();
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
        })->unique('id');
    }

    /**
     * Get cousins (children of uncles and aunts)
     */
    public function getCousins(Span $span): Collection
    {
        return $this->getUnclesAndAunts($span)->flatMap(function ($uncleAunt) {
            return $this->getChildren($uncleAunt);
        })->unique('id');
    }

    /**
     * Get nephews and nieces (children of siblings)
     */
    public function getNephewsAndNieces(Span $span): Collection
    {
        return $this->getSiblings($span)->flatMap(function ($sibling) {
            return $this->getChildren($sibling);
        })->unique('id');
    }
} 
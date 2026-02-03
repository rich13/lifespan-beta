<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FamilyTreeService
{
    /** Request-level cache for getCousins so cousins/extraNephewsAndNieces/extraInLawsAndOutLaws don't recompute (e.g. on homepage). */
    private static array $cousinsBySpanId = [];

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
        // Cache key based on environment, span ID and generations to avoid cross-env collisions
        $cachePrefix = app()->environment();
        $cacheKey = "{$cachePrefix}:descendants_{$span->id}_{$generations}";
        
        return Cache::remember($cacheKey, 3600, function () use ($span, $generations) {
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
        });
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
     * Get step-parents: deduplicated parents of children of the span's parents
     * (i.e. the other parent of half-siblings, or a parent's partner who has other children).
     */
    public function getStepParents(Span $span): Collection
    {
        $directParents = $this->getParents($span);
        $directParentIds = $directParents->pluck('id')->all();

        // Children of each of the span's parents (siblings + half-siblings)
        $childrenOfMyParents = $directParents->flatMap(function ($parent) {
            return $this->getChildren($parent);
        })->unique('id')->values();

        // All parents of those children, excluding the span's direct parents
        return $childrenOfMyParents->flatMap(function ($child) {
            return $this->getParents($child);
        })->reject(function ($parent) use ($directParentIds) {
            return in_array($parent->id, $directParentIds);
        })->unique('id')->values();
    }

    /**
     * Get in-laws & out-laws: people with whom siblings have had children.
     * (Deduplicated other parents of nephews/nieces.)
     */
    public function getInLawsAndOutLaws(Span $span): Collection
    {
        $siblings = $this->getSiblings($span);
        $siblingIds = $siblings->pluck('id')->all();
        if (empty($siblingIds)) {
            return collect();
        }

        $childIdsOfSiblings = Connection::where('type_id', 'family')
            ->whereIn('parent_id', $siblingIds)
            ->pluck('child_id')
            ->unique()
            ->values()
            ->all();
        if (empty($childIdsOfSiblings)) {
            return collect();
        }

        $excludeIds = array_merge($siblingIds, [$span->id]);
        $otherParentIds = Connection::where('type_id', 'family')
            ->whereIn('child_id', $childIdsOfSiblings)
            ->whereNotIn('parent_id', $excludeIds)
            ->pluck('parent_id')
            ->unique()
            ->values()
            ->all();
        if (empty($otherParentIds)) {
            return collect();
        }

        return Span::whereIn('id', $otherParentIds)->get();
    }

    /**
     * Get extra in-laws & out-laws: people with whom cousins have had children.
     * (Deduplicated other parents of extra nephews/nieces.)
     */
    public function getExtraInLawsAndOutLaws(Span $span): Collection
    {
        $cousins = $this->getCousins($span);
        $cousinIds = $cousins->pluck('id')->all();
        if (empty($cousinIds)) {
            return collect();
        }

        $childIdsOfCousins = Connection::where('type_id', 'family')
            ->whereIn('parent_id', $cousinIds)
            ->pluck('child_id')
            ->unique()
            ->values()
            ->all();
        if (empty($childIdsOfCousins)) {
            return collect();
        }

        $excludeIds = array_merge($cousinIds, [$span->id]);
        $otherParentIds = Connection::where('type_id', 'family')
            ->whereIn('child_id', $childIdsOfCousins)
            ->whereNotIn('parent_id', $excludeIds)
            ->pluck('parent_id')
            ->unique()
            ->values()
            ->all();
        if (empty($otherParentIds)) {
            return collect();
        }

        return Span::whereIn('id', $otherParentIds)->get();
    }

    /**
     * Get children-in/out-law: people with whom the span's children have had children.
     * (Deduplicated other parents of grandchildren.)
     */
    public function getChildrenInLawsAndOutLaws(Span $span): Collection
    {
        $children = $this->getChildren($span);
        $childIds = $children->pluck('id')->all();
        if (empty($childIds)) {
            return collect();
        }

        $grandchildIds = Connection::where('type_id', 'family')
            ->whereIn('parent_id', $childIds)
            ->pluck('child_id')
            ->unique()
            ->values()
            ->all();
        if (empty($grandchildIds)) {
            return collect();
        }

        $excludeIds = array_merge($childIds, [$span->id]);
        $otherParentIds = Connection::where('type_id', 'family')
            ->whereIn('child_id', $grandchildIds)
            ->whereNotIn('parent_id', $excludeIds)
            ->pluck('parent_id')
            ->unique()
            ->values()
            ->all();
        if (empty($otherParentIds)) {
            return collect();
        }

        return Span::whereIn('id', $otherParentIds)->get();
    }

    /**
     * Get grandchildren-in/out-law: people with whom the span's grandchildren have had children.
     * (Deduplicated other parents of great-grandchildren.)
     */
    public function getGrandchildrenInLawsAndOutLaws(Span $span): Collection
    {
        $children = $this->getChildren($span);
        $childIds = $children->pluck('id')->all();
        if (empty($childIds)) {
            return collect();
        }

        $grandchildIds = Connection::where('type_id', 'family')
            ->whereIn('parent_id', $childIds)
            ->pluck('child_id')
            ->unique()
            ->values()
            ->all();
        if (empty($grandchildIds)) {
            return collect();
        }

        $greatGrandchildIds = Connection::where('type_id', 'family')
            ->whereIn('parent_id', $grandchildIds)
            ->pluck('child_id')
            ->unique()
            ->values()
            ->all();
        if (empty($greatGrandchildIds)) {
            return collect();
        }

        $excludeIds = array_merge($grandchildIds, [$span->id]);
        $otherParentIds = Connection::where('type_id', 'family')
            ->whereIn('child_id', $greatGrandchildIds)
            ->whereNotIn('parent_id', $excludeIds)
            ->pluck('parent_id')
            ->unique()
            ->values()
            ->all();
        if (empty($otherParentIds)) {
            return collect();
        }

        return Span::whereIn('id', $otherParentIds)->get();
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

        // Safety check: limit total descendants to prevent memory issues
        if ($descendants->count() > 100) {
            Log::warning('Descendants limit reached, stopping traversal', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'current_count' => $descendants->count(),
                'generation' => $currentGen
            ]);
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
        // Cache key based on environment and span ID to avoid cross-env collisions
        $cachePrefix = app()->environment();
        $cacheKey = "{$cachePrefix}:uncles_and_aunts_{$span->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($span) {
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

            // Safety check: limit cousins to prevent memory issues
            if ($cousins->count() > 50) {
                Log::warning('Cousins limit reached, truncating results', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'cousins_count' => $cousins->count()
                ]);
                $cousins = $cousins->take(50);
            }

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
        });
    }

    /**
     * Get cousins (children of uncles and aunts)
     */
    public function getCousins(Span $span): Collection
    {
        $spanId = $span->id;
        if (array_key_exists($spanId, self::$cousinsBySpanId)) {
            return self::$cousinsBySpanId[$spanId];
        }

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

        self::$cousinsBySpanId[$spanId] = $result;
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
     * Get extra nephews and nieces (children of cousins)
     */
    public function getExtraNephewsAndNieces(Span $span): Collection
    {
        return $this->getCousins($span)->flatMap(function ($cousin) {
            return $this->getChildren($cousin);
        })->unique('id')->values();
    }

    /**
     * Helper method to compare spans by ID
     */
    protected function compareSpans(Span $a, Span $b): bool
    {
        return $a->id === $b->id;
    }

    /**
     * Clear all family relationship caches for a span
     * This should be called when family relationships change
     */
    public function clearFamilyCaches(Span $span): void
    {
        // Clear request-level cousins cache so next call recomputes
        self::$cousinsBySpanId = [];

        $cachePrefix = app()->environment();

        // Clear descendants cache for different generation levels for this span
        for ($generations = 1; $generations <= 5; $generations++) {
            Cache::forget("{$cachePrefix}:descendants_{$span->id}_{$generations}");
        }

        // Clear uncles and aunts cache for this span
        Cache::forget("{$cachePrefix}:uncles_and_aunts_{$span->id}");

        // Shallow invalidation for immediate relatives only (no recursion)
        $immediateRelatives = collect()
            ->merge($this->getParents($span))
            ->merge($this->getChildren($span))
            ->merge($this->getSiblings($span))
            ->unique('id');

        foreach ($immediateRelatives as $relative) {
            for ($generations = 1; $generations <= 3; $generations++) {
                Cache::forget("{$cachePrefix}:descendants_{$relative->id}_{$generations}");
            }
            Cache::forget("{$cachePrefix}:uncles_and_aunts_{$relative->id}");
        }
    }
} 
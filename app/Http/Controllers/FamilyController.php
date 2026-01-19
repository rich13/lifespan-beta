<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $personalSpan = $user->personalSpan;
        
        if (!$personalSpan) {
            return view('family.index', [
                'span' => null,
                'message' => 'No personal span found for your account.'
            ]);
        }

        return view('family.index', [
            'span' => $personalSpan,
            'message' => null
        ]);
    }

    public function show(Span $span)
    {
        // Check if the span is a person
        if ($span->type_id !== 'person') {
            abort(404, 'Family view is only available for people.');
        }

        // Check if user has access to this span
        if (!$span->hasPermission(auth()->user(), 'view')) {
            abort(403, 'You do not have permission to view this person\'s family.');
        }

        return view('family.show', [
            'span' => $span,
            'message' => null
        ]);
    }

    public function tree(Span $span)
    {
        // Check if the span is a person
        if ($span->type_id !== 'person') {
            abort(404, 'Family tree view is only available for people.');
        }

        // Check if user has access to this span
        if (!$span->hasPermission(auth()->user(), 'view')) {
            abort(403, 'You do not have permission to view this person\'s family tree.');
        }

        // Collect all family members (same as family graph)
        $ancestors = $span->ancestors(3);
        $descendants = $span->descendants(2);
        $siblings = $span->siblings();
        $unclesAndAunts = $span->unclesAndAunts();
        $cousins = $span->cousins();
        $nephewsAndNieces = $span->nephewsAndNieces();
        $extraNephewsAndNieces = $span->extraNephewsAndNieces();
        
        // Build hierarchical tree data with ancestors on left and descendants on right
        // The root person will be positioned in the middle
        $visited = [];
        $generationMap = []; // Track which generation each person belongs to
        $treeData = $this->buildTreeDataForLayout($span, [
            'ancestors' => $ancestors,
            'descendants' => $descendants,
            'siblings' => $siblings,
            'unclesAndAunts' => $unclesAndAunts,
            'cousins' => $cousins,
            'nephewsAndNieces' => $nephewsAndNieces,
            'extraNephewsAndNieces' => $extraNephewsAndNieces
        ]);
        
        return view('family.tree', [
            'span' => $span,
            'treeData' => $treeData
        ]);
    }
    
    /**
     * Build tree data structure with ancestors on left and descendants on right
     * Creates a structure where the root person is in the middle
     */
    private function buildTreeDataForLayout(Span $span, array $familyMembers): array
    {
        $visited = [];
        $generationMap = [];
        
        // Root person with ancestors and descendants as children
        // We'll mark them with direction so the visualization can position them correctly
        $rootNode = [
            'id' => $span->id,
            'name' => $span->name,
            'start_year' => $span->start_year,
            'end_year' => $span->end_year,
            'generation' => 0,
            'isRoot' => true,
            'children' => []
        ];
        
        // Build ancestors (parents, grandparents) - these go to the left
        $parents = $span->parents()->get();
        foreach ($parents as $parent) {
            $parentNode = $this->buildAncestorsBranch($parent, 1, 4, $visited, $generationMap, $familyMembers);
            if ($parentNode) {
                $parentNode['direction'] = 'left'; // Mark as going left
                $rootNode['children'][] = $parentNode;
            }
        }
        
        // Build descendants (children, grandchildren) - these go to the right
        $children = $span->children()->get();
        foreach ($children as $child) {
            $childNode = $this->buildDescendantsBranch($child, -1, 4, $visited, $generationMap, $familyMembers);
            if ($childNode) {
                $childNode['direction'] = 'right'; // Mark as going right
                $rootNode['children'][] = $childNode;
            }
        }
        
        // Add siblings at root level (same generation, positioned near root)
        foreach ($familyMembers['siblings'] as $sibling) {
            if (!in_array($sibling->id, $visited)) {
                $siblingNode = [
                    'id' => $sibling->id,
                    'name' => $sibling->name,
                    'start_year' => $sibling->start_year,
                    'end_year' => $sibling->end_year,
                    'generation' => 0,
                    'direction' => 'same', // Same generation as root
                    'children' => null
                ];
                $visited[] = $sibling->id;
                $generationMap[$sibling->id] = 0;
                $rootNode['children'][] = $siblingNode;
            }
        }
        
        if (empty($rootNode['children'])) {
            $rootNode['children'] = null;
        }
        
        return $rootNode;
    }
    
    /**
     * Build ancestors branch (parents, grandparents, etc.) - goes left
     */
    private function buildAncestorsBranch(
        Span $span,
        int $generation,
        int $maxDepth,
        array &$visited,
        array &$generationMap,
        array $familyMembers
    ): ?array {
        if ($generation > $maxDepth || in_array($span->id, $visited)) {
            return null;
        }
        
        $visited[] = $span->id;
        $generationMap[$span->id] = $generation;
        
        $node = [
            'id' => $span->id,
            'name' => $span->name,
            'start_year' => $span->start_year,
            'end_year' => $span->end_year,
            'generation' => $generation,
            'direction' => 'left',
            'children' => []
        ];
        
        // Get parents (ancestors)
        $parents = $span->parents()->get();
        foreach ($parents as $parent) {
            $parentNode = $this->buildAncestorsBranch($parent, $generation + 1, $maxDepth, $visited, $generationMap, $familyMembers);
            if ($parentNode) {
                $node['children'][] = $parentNode;
            }
        }
        
        if (empty($node['children'])) {
            $node['children'] = null;
        }
        
        return $node;
    }
    
    /**
     * Build descendants branch (children, grandchildren, etc.) - goes right
     */
    private function buildDescendantsBranch(
        Span $span,
        int $generation,
        int $maxDepth,
        array &$visited,
        array &$generationMap,
        array $familyMembers
    ): ?array {
        if (abs($generation) > $maxDepth || in_array($span->id, $visited)) {
            return null;
        }
        
        $visited[] = $span->id;
        $generationMap[$span->id] = $generation;
        
        $node = [
            'id' => $span->id,
            'name' => $span->name,
            'start_year' => $span->start_year,
            'end_year' => $span->end_year,
            'generation' => $generation,
            'direction' => 'right',
            'children' => []
        ];
        
        // Get children (descendants)
        $children = $span->children()->get();
        foreach ($children as $child) {
            $childNode = $this->buildDescendantsBranch($child, $generation - 1, $maxDepth, $visited, $generationMap, $familyMembers);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }
        
        // Handle partners/spouses - they should be at generation 0, not as children
        // This will be handled at the root level
        
        if (empty($node['children'])) {
            $node['children'] = null;
        }
        
        return $node;
    }
    
    /**
     * Recursively build tree data structure for dendrogram (legacy method - kept for reference)
     * Builds a tree including all family members with proper generational separation
     * Generation: 0 = root person, positive = ancestors, negative = descendants
     */
    private function buildTreeData(
        Span $span, 
        int $generation = 0, 
        int $maxDepth = 4, 
        array &$visited = [], 
        array &$generationMap = [],
        array $familyMembers = []
    ): ?array {
        // Check depth limits (absolute value of generation)
        if (abs($generation) > $maxDepth || in_array($span->id, $visited)) {
            return null;
        }
        
        // If we've seen this person before, check if we're at the correct generation
        if (isset($generationMap[$span->id])) {
            // Only include if we're at the same or better (closer to root) generation
            if (abs($generation) > abs($generationMap[$span->id])) {
                return null; // We already have this person at a better generation
            }
        }
        
        $visited[] = $span->id;
        $generationMap[$span->id] = $generation;
        
        $node = [
            'id' => $span->id,
            'name' => $span->name,
            'start_year' => $span->start_year,
            'end_year' => $span->end_year,
            'generation' => $generation,
            'children' => []
        ];
        
        // Get parents (ancestors) - generation increases (positive direction)
        $parents = $span->parents()->get();
        foreach ($parents as $parent) {
            $parentNode = $this->buildTreeData($parent, $generation + 1, $maxDepth, $visited, $generationMap, $familyMembers);
            if ($parentNode) {
                $node['children'][] = $parentNode;
            }
        }
        
        // Get children (descendants) - generation decreases (negative direction)
        // Only add direct children, not all descendants (they'll be added recursively)
        $children = $span->children()->get();
        foreach ($children as $child) {
            $childNode = $this->buildTreeData($child, $generation - 1, $maxDepth, $visited, $generationMap, $familyMembers);
            if ($childNode) {
                $node['children'][] = $childNode;
            }
        }
        
        // For parents: add their other children (siblings of current person or siblings of ancestors)
        // This ensures siblings appear as children of parents, maintaining generational structure
        if ($generation > 0) {
            // We're at a parent level, add siblings of this parent's children
            $parentChildren = $span->children()->get();
            foreach ($parentChildren as $parentChild) {
                // Get siblings of this child (which includes the person we came from)
                $siblingsOfChild = $parentChild->siblings();
                foreach ($siblingsOfChild as $sibling) {
                    if ($sibling->id !== $span->id && !in_array($sibling->id, $visited)) {
                        // This sibling should be at generation -1 (same as children)
                        $siblingNode = $this->buildTreeData($sibling, $generation - 1, $maxDepth, $visited, $generationMap, $familyMembers);
                        if ($siblingNode) {
                            $node['children'][] = $siblingNode;
                        }
                    }
                }
            }
        }
        
        // Handle siblings at root generation - they should appear as children of parents
        // This is handled above when we traverse parents
        
        // Handle partners/spouses - they should be at the same generation, shown as a special connection
        // For dendrogram purposes, we'll show them at the same level but mark them differently
        if ($generation === 0) {
            // Get spouse connections
            $spouseConnections = \App\Models\Connection::where(function($query) use ($span) {
                $query->where('parent_id', $span->id)
                      ->where('type_id', 'married');
            })->orWhere(function($query) use ($span) {
                $query->where('child_id', $span->id)
                      ->where('type_id', 'married');
            })->get();
            
            foreach ($spouseConnections as $connection) {
                $spouse = $connection->parent_id === $span->id ? $connection->child : $connection->parent;
                if ($spouse && !in_array($spouse->id, $visited)) {
                    // Spouse is at the same generation (0) - add as a special node
                    // We'll mark it so it can be styled differently in the visualization
                    $spouseNode = $this->buildTreeData($spouse, 0, $maxDepth, $visited, $generationMap, $familyMembers);
                    if ($spouseNode) {
                        $spouseNode['isSpouse'] = true;
                        $node['children'][] = $spouseNode;
                    }
                }
            }
            
            // Also detect partners through shared children (if not already connected as spouse)
            $children = $span->children()->get();
            foreach ($children as $child) {
                $childParents = $child->parents()->get();
                foreach ($childParents as $otherParent) {
                    if ($otherParent->id !== $span->id && !in_array($otherParent->id, $visited)) {
                        // Check if already connected as spouse
                        $isSpouse = \App\Models\Connection::where(function($query) use ($span, $otherParent) {
                            $query->where('parent_id', $span->id)
                                  ->where('child_id', $otherParent->id)
                                  ->where('type_id', 'married');
                        })->orWhere(function($query) use ($span, $otherParent) {
                            $query->where('parent_id', $otherParent->id)
                                  ->where('child_id', $span->id)
                                  ->where('type_id', 'married');
                        })->exists();
                        
                        if (!$isSpouse) {
                            // Partner at same generation (0)
                            $partnerNode = $this->buildTreeData($otherParent, 0, $maxDepth, $visited, $generationMap, $familyMembers);
                            if ($partnerNode) {
                                $partnerNode['isPartner'] = true;
                                $node['children'][] = $partnerNode;
                            }
                        }
                    }
                }
            }
        }
        
        // If no children, set to null for D3
        if (empty($node['children'])) {
            $node['children'] = null;
        }
        
        return $node;
    }

    /**
     * API endpoint to create family connections
     */
    public function createConnection(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|uuid|exists:spans,id',
            'child_id' => 'required|uuid|exists:spans,id',
            'relationship' => 'required|in:mother,father,parent'
        ]);

        $parent = Span::findOrFail($validated['parent_id']);
        $child = Span::findOrFail($validated['child_id']);

        // Check if both spans are people
        if ($parent->type_id !== 'person' || $child->type_id !== 'person') {
            return response()->json(['error' => 'Both spans must be people'], 400);
        }

        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $parent->id)
            ->where('child_id', $child->id)
            ->where('type_id', 'family')
            ->first();

        if ($existingConnection) {
            return response()->json(['error' => 'Family connection already exists'], 400);
        }

        try {
            // Create the connection span
            $connectionSpan = Span::create([
                'name' => "{$parent->name} - {$child->name} Family Connection",
                'type_id' => 'connection',
                'owner_id' => Auth::id(),
                'updater_id' => Auth::id(),
                'start_year' => $child->start_year ?? null,
                'start_month' => $child->start_month ?? null,
                'start_day' => $child->start_day ?? null,
                'access_level' => 'private',
                'state' => 'placeholder', // Set as placeholder since we don't have exact dates
                'start_precision' => 'year', // Set default precision
                'end_precision' => 'year' // Set default precision
            ]);

            // Create the family connection
            $connection = Connection::create([
                'type_id' => 'family',
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'connection_span_id' => $connectionSpan->id,
                'metadata' => [
                    'relationship_type' => $validated['relationship']
                ]
            ]);

            Log::info('Family connection created', [
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'relationship' => $validated['relationship'],
                'connection_id' => $connection->id
            ]);

            return response()->json([
                'success' => true,
                'connection' => $connection,
                'message' => 'Family connection created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create family connection', [
                'error' => $e->getMessage(),
                'parent_id' => $parent->id,
                'child_id' => $child->id
            ]);

            return response()->json(['error' => 'Failed to create family connection'], 500);
        }
    }
} 
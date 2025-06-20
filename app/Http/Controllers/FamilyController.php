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
                'familyData' => null,
                'message' => 'No personal span found for your account.'
            ]);
        }

        $familyData = $this->buildFamilyTree($personalSpan);
        
        return view('family.index', [
            'familyData' => $familyData,
            'message' => null
        ]);
    }

    /**
     * API endpoint to get family tree data
     */
    public function data()
    {
        $user = auth()->user();
        $personalSpan = $user->personalSpan;
        
        if (!$personalSpan) {
            return response()->json(['error' => 'No personal span found'], 404);
        }

        $familyData = $this->buildFamilyTree($personalSpan);
        
        return response()->json($familyData);
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
                'start_year' => $child->start_year,
                'start_month' => $child->start_month,
                'start_day' => $child->start_day,
                'access_level' => 'private'
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

    private function buildFamilyTree(Span $rootPerson)
    {
        Log::debug("Building family tree for: " . $rootPerson->name);
        
        // Get all related spans
        $allSpans = $this->getAllRelatedSpans($rootPerson);
        
        // Create nodes array
        $nodes = [];
        $links = [];
        
        foreach ($allSpans as $span) {
            $node = [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $this->getNodeType($span, $rootPerson),
                'gender' => $span->getMeta('gender'),
                'span' => $span
            ];
            $nodes[] = $node;
        }
        
        // Create links for parent-child relationships
        foreach ($allSpans as $span) {
            $children = $span->children()->get();
            foreach ($children as $child) {
                $links[] = [
                    'source' => $span->id,
                    'target' => $child->id,
                    'type' => 'parent-child'
                ];
            }
        }
        
        $tree = [
            'nodes' => $nodes,
            'links' => $links
        ];
        
        Log::debug("Force-directed tree structure: " . json_encode($tree));
        
        return $tree;
    }
    
    private function getAllRelatedSpans(Span $rootPerson)
    {
        $spans = collect([$rootPerson]);
        $processed = collect();
        
        while ($spans->count() > $processed->count()) {
            $current = $spans->diff($processed)->first();
            $processed->push($current);
            
            // Add parents
            $parents = $current->parents()->get();
            foreach ($parents as $parent) {
                if (!$spans->contains('id', $parent->id)) {
                    $spans->push($parent);
                }
            }
            
            // Add children
            $children = $current->children()->get();
            foreach ($children as $child) {
                if (!$spans->contains('id', $child->id)) {
                    $spans->push($child);
                }
            }
        }
        
        return $spans;
    }
    
    private function getNodeType(Span $span, Span $rootPerson)
    {
        if ($span->id === $rootPerson->id) {
            return 'current-user';
        }
        
        // Check if it's a parent of the root person
        $rootParents = $rootPerson->parents()->get();
        if ($rootParents->contains('id', $span->id)) {
            return 'parent';
        }
        
        // Check if it's a sibling of the root person
        $rootSiblings = $rootPerson->siblings();
        if ($rootSiblings->contains('id', $span->id)) {
            return 'sibling';
        }
        
        // Check if it's a child of the root person
        $rootChildren = $rootPerson->children()->get();
        if ($rootChildren->contains('id', $span->id)) {
            return 'child';
        }
        
        // Check if it's a grandparent of the root person
        foreach ($rootParents as $parent) {
            $grandparents = $parent->parents()->get();
            if ($grandparents->contains('id', $span->id)) {
                return 'grandparent';
            }
        }
        
        // Check if it's a grandchild of the root person
        foreach ($rootChildren as $child) {
            $grandchildren = $child->children()->get();
            if ($grandchildren->contains('id', $span->id)) {
                return 'grandchild';
            }
        }
        
        // Check if it's an uncle/aunt (sibling of parent)
        foreach ($rootParents as $parent) {
            $parentSiblings = $parent->siblings();
            if ($parentSiblings->contains('id', $span->id)) {
                return 'uncle-aunt';
            }
        }
        
        // Check if it's a cousin (child of uncle/aunt, or child of sibling)
        // First check if it's a child of an uncle/aunt
        foreach ($rootParents as $parent) {
            $parentSiblings = $parent->siblings();
            foreach ($parentSiblings as $uncleAunt) {
                $cousins = $uncleAunt->children()->get();
                if ($cousins->contains('id', $span->id)) {
                    return 'cousin';
                }
            }
        }
        
        // Check if it's a child of a sibling (niece/nephew)
        $rootSiblings = $rootPerson->siblings();
        foreach ($rootSiblings as $sibling) {
            $niecesNephews = $sibling->children()->get();
            if ($niecesNephews->contains('id', $span->id)) {
                return 'niece-nephew';
            }
        }
        
        // Check if it's a great-grandparent
        foreach ($rootParents as $parent) {
            $grandparents = $parent->parents()->get();
            foreach ($grandparents as $grandparent) {
                $greatGrandparents = $grandparent->parents()->get();
                if ($greatGrandparents->contains('id', $span->id)) {
                    return 'great-grandparent';
                }
            }
        }
        
        // Check if it's a great-grandchild
        foreach ($rootChildren as $child) {
            $grandchildren = $child->children()->get();
            foreach ($grandchildren as $grandchild) {
                $greatGrandchildren = $grandchild->children()->get();
                if ($greatGrandchildren->contains('id', $span->id)) {
                    return 'great-grandchild';
                }
            }
        }
        
        // Default based on whether it has children (ancestor) or parents (descendant)
        $children = $span->children()->get();
        $parents = $span->parents()->get();
        
        if ($children->count() > 0 && $parents->count() > 0) {
            return 'ancestor';
        } elseif ($children->count() > 0) {
            return 'ancestor';
        } else {
            return 'descendant';
        }
    }
} 
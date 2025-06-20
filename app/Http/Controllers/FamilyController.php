<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
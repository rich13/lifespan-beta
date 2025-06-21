<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FriendsController extends Controller
{
    public function index()
    {
        return view('friends.index');
    }

    /**
     * API endpoint to get friends data for the current user
     */
    public function data()
    {
        $personalSpan = Auth::user()->personalSpan;
        
        if (!$personalSpan) {
            return response()->json(['error' => 'No personal span found'], 404);
        }

        $friendsData = $this->buildFriendsNetwork($personalSpan);
        
        return response()->json($friendsData);
    }

    /**
     * API endpoint to create friend connections
     */
    public function createConnection(Request $request)
    {
        $validated = $request->validate([
            'person1_id' => 'required|uuid|exists:spans,id',
            'person2_id' => 'required|uuid|exists:spans,id',
            'connection_type' => 'required|in:friend,relationship',
            'relationship_type' => 'nullable|in:partner,spouse,dating'
        ]);

        $person1 = Span::findOrFail($validated['person1_id']);
        $person2 = Span::findOrFail($validated['person2_id']);

        // Check if both spans are people
        if ($person1->type_id !== 'person' || $person2->type_id !== 'person') {
            return response()->json(['error' => 'Both spans must be people'], 400);
        }

        // Check if connection already exists
        $existingConnection = Connection::where(function ($query) use ($person1, $person2) {
            $query->where('parent_id', $person1->id)
                  ->where('child_id', $person2->id);
        })->orWhere(function ($query) use ($person1, $person2) {
            $query->where('parent_id', $person2->id)
                  ->where('child_id', $person1->id);
        })->where('type_id', $validated['connection_type'])
        ->first();

        if ($existingConnection) {
            return response()->json(['error' => 'Connection already exists'], 400);
        }

        try {
            // Create the connection span
            $connectionSpan = Span::create([
                'name' => "{$person1->name} - {$person2->name} {$validated['connection_type']} Connection",
                'type_id' => 'connection',
                'owner_id' => Auth::id(),
                'updater_id' => Auth::id(),
                'start_year' => null,
                'start_month' => null,
                'start_day' => null,
                'access_level' => 'private',
                'state' => 'placeholder',
                'start_precision' => 'year',
                'end_precision' => 'year'
            ]);

            // Create the connection
            $connection = Connection::create([
                'type_id' => $validated['connection_type'],
                'parent_id' => $person1->id,
                'child_id' => $person2->id,
                'connection_span_id' => $connectionSpan->id,
                'metadata' => [
                    'relationship_type' => $validated['relationship_type'] ?? null
                ]
            ]);

            Log::info('Connection created', [
                'person1_id' => $person1->id,
                'person2_id' => $person2->id,
                'connection_type' => $validated['connection_type'],
                'relationship_type' => $validated['relationship_type'] ?? null,
                'connection_id' => $connection->id
            ]);

            return response()->json([
                'success' => true,
                'connection' => $connection,
                'message' => 'Connection created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create connection', [
                'error' => $e->getMessage(),
                'person1_id' => $person1->id,
                'person2_id' => $person2->id
            ]);

            return response()->json(['error' => 'Failed to create connection'], 500);
        }
    }

    private function buildFriendsNetwork(Span $rootPerson)
    {
        Log::debug("Building friends network for: " . $rootPerson->name);
        
        // Get all related spans through friend and relationship connections
        $allSpans = $this->getAllRelatedSpans($rootPerson);
        
        // Create nodes array
        $nodes = [];
        $links = collect();
        
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
        
        // Create links for friend and relationship connections
        foreach ($allSpans as $span) {
            // Get friends
            $friends = $span->friends()->get();
            foreach ($friends as $friend) {
                // Only add the link once (avoid duplicates)
                $linkExists = $links->some(function($link) use ($span, $friend) {
                    return ($link['source'] === $span->id && $link['target'] === $friend->id) ||
                           ($link['source'] === $friend->id && $link['target'] === $span->id);
                });
                
                if (!$linkExists) {
                    $links->push([
                        'source' => $span->id,
                        'target' => $friend->id,
                        'type' => 'friend'
                    ]);
                }
            }
            
            // Get relationships
            $relationships = $span->relationships()->get();
            foreach ($relationships as $relationship) {
                // Only add the link once (avoid duplicates)
                $linkExists = $links->some(function($link) use ($span, $relationship) {
                    return ($link['source'] === $span->id && $link['target'] === $relationship->id) ||
                           ($link['source'] === $relationship->id && $link['target'] === $span->id);
                });
                
                if (!$linkExists) {
                    $links->push([
                        'source' => $span->id,
                        'target' => $relationship->id,
                        'type' => 'relationship'
                    ]);
                }
            }
        }
        
        $network = [
            'nodes' => $nodes,
            'links' => $links->toArray()
        ];
        
        Log::debug("Friends network structure: " . json_encode($network));
        
        return $network;
    }
    
    private function getAllRelatedSpans(Span $rootPerson)
    {
        $spans = collect([$rootPerson]);
        $processed = collect();
        
        while ($spans->count() > $processed->count()) {
            $current = $spans->diff($processed)->first();
            $processed->push($current);
            
            // Add friends
            $friends = $current->friends()->get();
            foreach ($friends as $friend) {
                if (!$spans->contains('id', $friend->id)) {
                    $spans->push($friend);
                }
            }
            
            // Add relationships
            $relationships = $current->relationships()->get();
            foreach ($relationships as $relationship) {
                if (!$spans->contains('id', $relationship->id)) {
                    $spans->push($relationship);
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
        
        // Check if it's a direct friend of the root person
        $rootFriends = $rootPerson->friends()->get();
        if ($rootFriends->contains('id', $span->id)) {
            return 'friend';
        }
        
        // Check if it's a direct relationship of the root person
        $rootRelationships = $rootPerson->relationships()->get();
        if ($rootRelationships->contains('id', $span->id)) {
            return 'relationship';
        }
        
        // Check if it's a friend of a friend or relationship
        foreach ($rootFriends as $friend) {
            $friendsOfFriend = $friend->friends()->get();
            if ($friendsOfFriend->contains('id', $span->id)) {
                return 'friend';
            }
        }
        
        foreach ($rootRelationships as $relationship) {
            $friendsOfRelationship = $relationship->friends()->get();
            if ($friendsOfRelationship->contains('id', $span->id)) {
                return 'friend';
            }
        }
        
        return 'acquaintance';
    }
} 
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToolsController extends Controller
{
    /**
     * Show the admin tools page
     */
    public function index(Request $request)
    {
        $similarSpans = collect();
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => \App\Models\User::count(),
            'total_connections' => Connection::count(),
            'orphaned_spans' => 0, // TODO: Implement orphaned spans detection
        ];

        // If there's a search query, find similar spans
        if ($request->has('search') && !empty($request->search)) {
            $similarSpans = $this->findSimilarSpansForView($request->search);
        }

        return view('admin.tools.index', compact('similarSpans', 'stats'));
    }

    /**
     * Find similar spans for the view (returns collection instead of JSON)
     */
    private function findSimilarSpansForView(string $query)
    {
        return Span::where('name', 'ILIKE', '%' . $query . '%')
            ->orWhere('slug', 'ILIKE', '%' . $query . '%')
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    /**
     * Find spans with similar names (potential duplicates)
     */
    public function findSimilarSpans(Request $request)
    {
        $query = $request->get('query', '');
        $limit = $request->get('limit', 50);

        if (empty($query)) {
            return response()->json(['similar_spans' => []]);
        }

        // Find spans with similar names
        $similarSpans = Span::where('name', 'ILIKE', '%' . $query . '%')
            ->orWhere('slug', 'ILIKE', '%' . $query . '%')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function ($span) {
                return [
                    'id' => $span->id,
                    'name' => $span->name,
                    'slug' => $span->slug,
                    'type' => $span->type_id,
                    'state' => $span->state,
                    'connections_count' => $span->connectionsAsSubject->count() + $span->connectionsAsObject->count(),
                    'created_at' => $span->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json(['similar_spans' => $similarSpans]);
    }

    /**
     * Merge spans - move all connections from source to target and delete source
     */
    public function mergeSpans(Request $request)
    {
        try {
            $validated = $request->validate([
                'target_span_id' => 'required|exists:spans,id',
                'source_span_id' => 'required|exists:spans,id|different:target_span_id',
            ]);

            $targetSpan = Span::findOrFail($validated['target_span_id']);
            $sourceSpan = Span::findOrFail($validated['source_span_id']);

            // Additional validation: if source span is a connection span, target must also be a connection span
            if ($sourceSpan->type_id === 'connection' && $targetSpan->type_id !== 'connection') {
                return response()->json([
                    'error' => 'Cannot merge a connection span into a non-connection span'
                ], 422);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Move all connections from source to target
            $this->moveConnections($sourceSpan, $targetSpan);

            // Update any references to the source span in other spans' metadata
            $this->updateSpanReferences($sourceSpan, $targetSpan);

            // Delete the source span
            $sourceSpan->delete();

            DB::commit();

            Log::info('Spans merged successfully', [
                'target_span_id' => $targetSpan->id,
                'target_span_name' => $targetSpan->name,
                'source_span_id' => $sourceSpan->id,
                'source_span_name' => $sourceSpan->name,
                'merged_by' => auth()->id(),
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to merge spans', [
                'target_span_id' => $targetSpan->id,
                'source_span_id' => $sourceSpan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to merge spans: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move all connections from source span to target span
     */
    private function moveConnections(Span $sourceSpan, Span $targetSpan): void
    {
        // Move outgoing connections (where source is the subject/parent)
        $outgoingConnections = $sourceSpan->connectionsAsSubject;
        foreach ($outgoingConnections as $connection) {
            // If this would create a self-referencing connection, delete it instead
            if ($connection->child_id === $targetSpan->id) {
                $connection->delete();
            } else {
                $connection->update(['parent_id' => $targetSpan->id]);
            }
        }

        // Move incoming connections (where source is the object/child)
        $incomingConnections = $sourceSpan->connectionsAsObject;
        foreach ($incomingConnections as $connection) {
            // If this would create a self-referencing connection, delete it instead
            if ($connection->parent_id === $targetSpan->id) {
                $connection->delete();
            } else {
                $connection->update(['child_id' => $targetSpan->id]);
            }
        }

        // Move connection spans (where source is the connection span)
        $connectionSpans = Connection::where('connection_span_id', $sourceSpan->id)->get();
        foreach ($connectionSpans as $connection) {
            $connection->update(['connection_span_id' => $targetSpan->id]);
        }
    }

    /**
     * Update any references to the source span in other spans' metadata
     */
    private function updateSpanReferences(Span $sourceSpan, Span $targetSpan): void
    {
        // This would need to be implemented based on how references are stored
        // For now, we'll just log that this step was considered
        Log::info('Span references update considered', [
            'source_span_id' => $sourceSpan->id,
            'target_span_id' => $targetSpan->id,
        ]);
    }

    /**
     * Create Desert Island Discs set for a person
     */
    public function createDesertIslandDiscs(Request $request)
    {
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => \App\Models\User::count(),
            'total_connections' => Connection::count(),
            'orphaned_spans' => 0, // TODO: Implement orphaned spans detection
        ];

        try {
            // Handle search for people
            if ($request->has('person_search') && !empty($request->person_search)) {
                $people = Span::where('type_id', 'person')
                    ->where('name', 'ILIKE', '%' . $request->person_search . '%')
                    ->orderBy('name')
                    ->limit(20)
                    ->get();

                return view('admin.tools.index', compact('people', 'stats'));
            }

            // Handle creating the set
            if ($request->has('person_id')) {
                $request->validate([
                    'person_id' => 'required|exists:spans,id'
                ]);

                $person = Span::findOrFail($request->person_id);
                
                // Check if person is actually a person type
                if ($person->type_id !== 'person') {
                    return back()->withErrors(['person_id' => 'Selected span is not a person.']);
                }

                // Check if person already has a Desert Island Discs set
                $existingSet = Span::getPublicDesertIslandDiscsSet($person);
                if ($existingSet) {
                    return back()->with('desert_island_discs_created', "{$person->name} already has a Desert Island Discs set!");
                }

                // Create the Desert Island Discs set using the existing method
                $set = Span::getOrCreatePublicDesertIslandDiscsSet($person);

                Log::info('Desert Island Discs set created', [
                    'person_id' => $person->id,
                    'person_name' => $person->name,
                    'set_id' => $set->id,
                    'created_by' => auth()->id(),
                ]);

                return back()->with('desert_island_discs_created', "Desert Island Discs set created successfully for {$person->name}!");
            }

            return back()->withErrors(['person_search' => 'Please search for a person first.']);

        } catch (\Exception $e) {
            Log::error('Failed to create Desert Island Discs set', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return back()->withErrors(['general' => 'Failed to create Desert Island Discs set: ' . $e->getMessage()]);
        }
    }

    /**
     * Get span details for merging
     */
    public function getSpanDetails(Request $request)
    {
        $spanId = $request->get('span_id');
        $span = Span::with(['connectionsAsSubject', 'connectionsAsObject'])->findOrFail($spanId);

        return response()->json([
            'span' => [
                'id' => $span->id,
                'name' => $span->name,
                'slug' => $span->slug,
                'type' => $span->type_id,
                'state' => $span->state,
                'description' => $span->description,
                'connections_count' => $span->connectionsAsSubject->count() + $span->connectionsAsObject->count(),
                'created_at' => $span->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $span->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * Show the Make Things Public tool interface
     */
    public function showMakeThingsPublic(Request $request)
    {
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => \App\Models\User::count(),
            'total_connections' => Connection::count(),
            'orphaned_spans' => 0,
        ];

        // Get current statistics for things
        $things = Span::where('type_id', 'thing')->get();
        $bySubtype = $things->groupBy(function ($thing) {
            return $thing->metadata['subtype'] ?? 'none';
        });

        $thingStats = [];
        foreach ($bySubtype as $subtype => $subtypeThings) {
            $public = $subtypeThings->where('access_level', 'public')->count();
            $private = $subtypeThings->where('access_level', 'private')->count();
            $thingStats[$subtype] = [
                'total' => $subtypeThings->count(),
                'public' => $public,
                'private' => $private
            ];
        }

        return view('admin.tools.make-things-public', compact('stats', 'thingStats'));
    }

    /**
     * Execute the Make Things Public operation
     */
    public function executeMakeThingsPublic(Request $request)
    {
        try {
            $request->validate([
                'subtype' => 'nullable|string|in:book,album,track',
                'owner_email' => 'nullable|email|exists:users,email',
                'dry_run' => 'boolean'
            ]);

            $subtype = $request->get('subtype');
            $ownerEmail = $request->get('owner_email');
            $isDryRun = $request->boolean('dry_run', true); // Default to dry run for safety

            // Build the query
            $query = Span::where('type_id', 'thing')
                ->where('access_level', 'private');

            if ($subtype) {
                $query->whereJsonContains('metadata->subtype', $subtype);
            }

            if ($ownerEmail) {
                $user = \App\Models\User::where('email', $ownerEmail)->first();
                $query->where('owner_id', $user->id);
            }

            $privateThings = $query->get();

            if ($privateThings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No private thing spans found to make public.',
                    'changes' => []
                ]);
            }

            // Group by subtype for reporting
            $bySubtype = $privateThings->groupBy(function ($thing) {
                return $thing->metadata['subtype'] ?? 'none';
            });

            $changes = [];
            foreach ($bySubtype as $subtype => $things) {
                $changes[$subtype] = $things->count();
            }

            if ($isDryRun) {
                return response()->json([
                    'success' => true,
                    'message' => "Found {$privateThings->count()} private thing spans to make public.",
                    'changes' => $changes,
                    'dry_run' => true
                ]);
            }

            // Make the changes
            $updatedCount = 0;
            foreach ($privateThings as $thing) {
                $thing->access_level = 'public';
                $thing->save();
                $updatedCount++;
            }

            Log::info('Things made public', [
                'updated_count' => $updatedCount,
                'subtype_filter' => $subtype,
                'owner_filter' => $ownerEmail,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully made {$updatedCount} thing spans public!",
                'changes' => $changes,
                'dry_run' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to make things public', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to make things public: ' . $e->getMessage()
            ], 500);
        }
    }
} 
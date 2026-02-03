<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BulkDeleteZeroConnectionDuplicatesJob;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MergeController extends Controller
{
    /**
     * Show the span merge tool page
     */
    public function index(Request $request)
    {
        $similarSpans = collect();
        $availableSpanTypes = $this->getAvailableSpanTypes();
        $exactDuplicateGroups = collect();

        // If there's a search query, find similar spans
        if ($request->has('search') && !empty($request->search)) {
            $spanType = $request->get('span_type');
            $state = $request->get('state');
            $similarSpans = $this->findSimilarSpansForView($request->search, $spanType, $state);
        }

        // Zero-connection duplicate groups (all spans in group have 0 connections) - separate section, bulk delete older
        $zeroConnectionDuplicateGroups = $this->getZeroConnectionDuplicateGroups($availableSpanTypes);
        // Exact duplicate groups (at least one span has connections) - merge workflow; exclude zero-connection groups
        $exactDuplicateGroups = $this->getExactDuplicateGroups($availableSpanTypes);

        return view('admin.merge.index', compact('similarSpans', 'availableSpanTypes', 'exactDuplicateGroups', 'zeroConnectionDuplicateGroups'));
    }

    /**
     * Find groups of spans with identical type_id, subtype and name where every span has 0 connections (2+ per group).
     * For each group, spans sorted by created_at desc: first = keep (newest), rest = delete (older).
     */
    private function getZeroConnectionDuplicateGroups(array $availableSpanTypes): \Illuminate\Support\Collection
    {
        $groupKeys = DB::table('spans')
            ->selectRaw("type_id, name, COALESCE(metadata->>'subtype', '') as subtype")
            ->groupBy('type_id', 'name', DB::raw("COALESCE(metadata->>'subtype', '')"))
            ->havingRaw('count(*) > 1')
            ->get();

        if ($groupKeys->isEmpty()) {
            return collect();
        }

        $spans = Span::where(function ($query) use ($groupKeys) {
            foreach ($groupKeys as $key) {
                $query->orWhere(function ($q) use ($key) {
                    $q->where('type_id', $key->type_id)
                        ->where('name', $key->name)
                        ->whereRaw("COALESCE(metadata->>'subtype', '') = ?", [$key->subtype]);
                });
            }
        })
            ->withCount(['connectionsAsSubject', 'connectionsAsObject'])
            ->orderBy('created_at', 'desc')
            ->orderBy('slug')
            ->get();

        $grouped = $spans->groupBy(fn ($span) => $this->duplicateGroupKey($span));

        return $grouped->map(function ($groupSpans, $key) use ($availableSpanTypes) {
            $first = $groupSpans->first();
            $totalConnections = $groupSpans->sum(fn ($s) => $s->connections_as_subject_count + $s->connections_as_object_count);
            if ($totalConnections > 0) {
                return null;
            }
            $spansByCreated = $groupSpans->sortByDesc('created_at')->values();
            $keepSpan = $spansByCreated->first();
            $deleteSpans = $spansByCreated->slice(1);
            return [
                'name' => $first->name,
                'type_id' => $first->type_id,
                'subtype' => $first->getMeta('subtype') ?? '',
                'type_label' => $availableSpanTypes[$first->type_id] ?? $first->type_id,
                'spans' => $groupSpans,
                'keep_span_id' => $keepSpan->id,
                'spans_to_delete' => $deleteSpans,
            ];
        })->filter()->values();
    }

    /**
     * Group key for duplicate detection: type_id + subtype + name (so album vs track are not grouped together).
     */
    private function duplicateGroupKey(Span $span): string
    {
        $subtype = $span->getMeta('subtype') ?? '';
        return $span->type_id . '|' . $subtype . '|' . $span->name;
    }

    /**
     * Find groups of spans with identical type_id, subtype and name (2+ per group) where at least one span has connections.
     * Excludes groups where all spans have 0 connections (those are in zero-connection section).
     */
    private function getExactDuplicateGroups(array $availableSpanTypes): \Illuminate\Support\Collection
    {
        $groupKeys = DB::table('spans')
            ->selectRaw("type_id, name, COALESCE(metadata->>'subtype', '') as subtype")
            ->groupBy('type_id', 'name', DB::raw("COALESCE(metadata->>'subtype', '')"))
            ->havingRaw('count(*) > 1')
            ->get();

        if ($groupKeys->isEmpty()) {
            return collect();
        }

        $spans = Span::where(function ($query) use ($groupKeys) {
            foreach ($groupKeys as $key) {
                $query->orWhere(function ($q) use ($key) {
                    $q->where('type_id', $key->type_id)
                        ->where('name', $key->name)
                        ->whereRaw("COALESCE(metadata->>'subtype', '') = ?", [$key->subtype]);
                });
            }
        })
            ->withCount(['connectionsAsSubject', 'connectionsAsObject'])
            ->with([
                'connectionsAsSubject:id,parent_id,child_id,type_id',
                'connectionsAsSubject.child:id,name,slug',
                'connectionsAsObject:id,parent_id,child_id,type_id',
                'connectionsAsObject.parent:id,name,slug',
            ])
            ->orderBy('name')
            ->orderBy('slug')
            ->get();

        $grouped = $spans->groupBy(fn ($span) => $this->duplicateGroupKey($span));

        $allGroups = $grouped->map(function ($groupSpans, $key) use ($availableSpanTypes) {
            $first = $groupSpans->first();
            $totalConnections = $groupSpans->sum(fn ($s) => $s->connections_as_subject_count + $s->connections_as_object_count);
            if ($totalConnections === 0) {
                return null;
            }
            $spansSortedByConnections = $groupSpans->sortByDesc(function ($span) {
                return $span->connections_as_subject_count + $span->connections_as_object_count;
            })->values();
            $suggestedTarget = $spansSortedByConnections->first();
            $suggestedSource = $spansSortedByConnections->first(fn ($s) => $s->id !== $suggestedTarget->id)
                ?? $spansSortedByConnections->last();
            return [
                'name' => $first->name,
                'type_id' => $first->type_id,
                'subtype' => $first->getMeta('subtype') ?? '',
                'type_label' => $availableSpanTypes[$first->type_id] ?? $first->type_id,
                'spans' => $groupSpans,
                'suggested_target_span_id' => $suggestedTarget->id,
                'suggested_source_span_id' => $suggestedSource->id,
            ];
        })->filter()->values();

        return $allGroups;
    }

    /**
     * Find similar spans for the view (returns collection instead of JSON)
     */
    private function findSimilarSpansForView(string $query, ?string $spanType = null, ?string $state = null)
    {
        $query = Span::where(function($q) use ($query) {
            $q->where('name', 'ILIKE', '%' . $query . '%')
              ->orWhere('slug', 'ILIKE', '%' . $query . '%');
        });

        if ($spanType) {
            $query->where('type_id', $spanType);
        }

        if ($state) {
            $query->where('state', $state);
        }

        return $query->orderBy('name')
            ->limit(50)
            ->get();
    }

    /**
     * Find spans with similar names (potential duplicates)
     */
    public function findSimilarSpans(Request $request)
    {
        $query = $request->get('query', '');
        $spanType = $request->get('span_type');
        $state = $request->get('state');
        $limit = $request->get('limit', 50);

        if (empty($query)) {
            return response()->json(['similar_spans' => []]);
        }

        // Find spans with similar names
        $spansQuery = Span::where(function($q) use ($query) {
            $q->where('name', 'ILIKE', '%' . $query . '%')
              ->orWhere('slug', 'ILIKE', '%' . $query . '%');
        });

        if ($spanType) {
            $spansQuery->where('type_id', $spanType);
        }

        if ($state) {
            $spansQuery->where('state', $state);
        }

        $similarSpans = $spansQuery->orderBy('name')
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
        // connection_span_id has a UNIQUE constraint: only one connection per connection span.
        // If target is already used as connection_span_id, we cannot reassign; delete the source's instead.
        $connectionsUsingSource = Connection::where('connection_span_id', $sourceSpan->id)->get();
        $targetAlreadyUsed = Connection::where('connection_span_id', $targetSpan->id)->exists();

        foreach ($connectionsUsingSource as $connection) {
            if ($targetAlreadyUsed) {
                $connection->delete();
            } else {
                $connection->update(['connection_span_id' => $targetSpan->id]);
                $targetAlreadyUsed = true;
            }
        }
    }

    /**
     * Update any references to the source span in other spans' metadata
     */
    private function updateSpanReferences(Span $sourceSpan, Span $targetSpan): void
    {
        // Find all spans that might reference the source span in their metadata
        $spansWithMetadata = Span::whereNotNull('metadata')->get();
        
        foreach ($spansWithMetadata as $span) {
            $metadata = $span->metadata;
            $updated = false;
            
            // Check if metadata contains references to the source span
            if (is_array($metadata)) {
                $metadata = $this->recursivelyUpdateReferences($metadata, $sourceSpan->id, $targetSpan->id, $updated);
                
                if ($updated) {
                    $span->update(['metadata' => $metadata]);
                }
            }
        }
    }

    /**
     * Recursively update references in metadata arrays
     */
    private function recursivelyUpdateReferences($data, $sourceId, $targetId, &$updated): array
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->recursivelyUpdateReferences($value, $sourceId, $targetId, $updated);
                } elseif (is_string($value) && $value === $sourceId) {
                    $data[$key] = $targetId;
                    $updated = true;
                }
            }
        }
        
        return $data;
    }

    /**
     * Get available span types for filtering
     */
    private function getAvailableSpanTypes(): array
    {
        return [
            'person' => 'Person',
            'place' => 'Place',
            'organisation' => 'Organisation',
            'thing' => 'Thing',
            'event' => 'Event',
            'band' => 'Band',
            'connection' => 'Connection',
            'set' => 'Set',
            'role' => 'Role',
            'phase' => 'Phase',
        ];
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
     * Bulk delete the older span(s) in zero-connection duplicate groups (keep newest per group).
     * Optional: type_id + name to process one group only (synchronous).
     * When neither is provided, dispatches a queued job to process all groups (scalable).
     */
    public function bulkDeleteZeroConnectionDuplicates(Request $request)
    {
        $typeId = $request->get('type_id');
        $name = $request->get('name');

        if ($typeId !== null && $typeId !== '' && $name !== null && $name !== '') {
            $subtype = $request->get('subtype', '');
            return $this->bulkDeleteZeroConnectionDuplicatesSync($typeId, $name, $subtype);
        }

        $availableSpanTypes = $this->getAvailableSpanTypes();
        $groups = $this->getZeroConnectionDuplicateGroups($availableSpanTypes);
        $totalGroups = $groups->count();

        if ($totalGroups === 0) {
            return redirect()->route('admin.merge.index')
                ->with('success', 'No zero-connection duplicates to delete.');
        }

        $runId = (string) Str::uuid();
        DB::table('bulk_delete_progress')->insert([
            'run_id' => $runId,
            'total_groups' => $totalGroups,
            'groups_processed' => 0,
            'deleted_count' => 0,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BulkDeleteZeroConnectionDuplicatesJob::dispatch($runId, $totalGroups);

        return redirect()->route('admin.merge.index', ['bulk_delete_run' => $runId])
            ->with('success', 'Bulk delete has been queued. Progress will appear below.');
    }

    /**
     * Return progress for a bulk delete zero-connection run (for polling).
     */
    public function bulkDeleteZeroConnectionProgress(Request $request)
    {
        $runId = $request->get('run_id');
        if (!$runId) {
            return response()->json(['status' => 'not_started']);
        }

        $row = DB::table('bulk_delete_progress')->where('run_id', $runId)->first();
        if ($row === null) {
            return response()->json(['status' => 'not_started']);
        }

        return response()->json([
            'total_groups' => (int) $row->total_groups,
            'groups_processed' => (int) $row->groups_processed,
            'deleted_count' => (int) $row->deleted_count,
            'status' => $row->status,
        ]);
    }

    /**
     * Synchronously delete the older span(s) in a single zero-connection duplicate group.
     */
    private function bulkDeleteZeroConnectionDuplicatesSync(string $typeId, string $name, string $subtype = '')
    {
        $availableSpanTypes = $this->getAvailableSpanTypes();
        $groups = $this->getZeroConnectionDuplicateGroups($availableSpanTypes)
            ->where('type_id', $typeId)
            ->where('name', $name)
            ->where('subtype', $subtype)
            ->values();

        $deletedCount = 0;
        try {
            DB::beginTransaction();
            foreach ($groups as $group) {
                foreach ($group['spans_to_delete'] as $span) {
                    $span->delete();
                    $deletedCount++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete zero-connection duplicates failed', [
                'error' => $e->getMessage(),
                'deleted_count' => $deletedCount,
            ]);
            return redirect()->route('admin.merge.index')
                ->with('error', 'Failed to delete duplicates: ' . $e->getMessage());
        }

        $message = $deletedCount === 0
            ? 'No zero-connection duplicates to delete in that group.'
            : "Deleted {$deletedCount} older duplicate span(s). Kept the newest.";
        return redirect()->route('admin.merge.index')->with('success', $message);
    }
}

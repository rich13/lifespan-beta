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

        $wikipediaCachedDays = $this->countWikipediaCachedDays();
        $wikipediaTotalDays = 366; // Always count leap years for completeness

        return view('admin.tools.index', compact('similarSpans', 'stats', 'wikipediaCachedDays', 'wikipediaTotalDays'));
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

            // Build the query for private things
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

            // Find private connections to these things
            $privateConnections = collect();
            $privateConnectionSpans = collect();
            $relatedSpans = collect();

            if ($privateThings->isNotEmpty()) {
                $thingIds = $privateThings->pluck('id')->toArray();

                // Find private connections where things are the object (child)
                $childConnections = \App\Models\Connection::whereIn('child_id', $thingIds)
                    ->whereHas('connectionSpan', function($q) {
                        $q->where('access_level', 'private');
                    })
                    ->with('connectionSpan')
                    ->get();

                // Find private connections where things are the subject (parent)
                $parentConnections = \App\Models\Connection::whereIn('parent_id', $thingIds)
                    ->whereHas('connectionSpan', function($q) {
                        $q->where('access_level', 'private');
                    })
                    ->with('connectionSpan')
                    ->get();

                // Combine all connections
                $privateConnections = $childConnections->merge($parentConnections);

                // Find private connection spans related to these connections
                $connectionSpanIds = $privateConnections->pluck('connection_span_id')->filter()->toArray();
                $privateConnectionSpans = Span::whereIn('id', $connectionSpanIds)
                    ->where('access_level', 'private')
                    ->get();

                // Find related spans that are connected to these things and are private
                $relatedSpanIds = collect();
                $relatedSpanIds = $relatedSpanIds->merge($childConnections->pluck('parent_id'));
                $relatedSpanIds = $relatedSpanIds->merge($parentConnections->pluck('child_id'));
                $relatedSpanIds = $relatedSpanIds->unique()->filter()->toArray();
                
                $relatedSpans = Span::whereIn('id', $relatedSpanIds)
                    ->where('access_level', 'private')
                    ->get();
            }

            // Check if we have anything to process
            $totalItems = $privateThings->count() + $privateConnections->count() + $privateConnectionSpans->count() + $relatedSpans->count();
            
            if ($totalItems === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No private items found to make public.',
                    'changes' => []
                ]);
            }

            // Group things by subtype for reporting
            $bySubtype = $privateThings->groupBy(function ($thing) {
                return $thing->metadata['subtype'] ?? 'none';
            });

            $changes = [
                'things' => $bySubtype->map->count(),
                'connections' => $privateConnections->count(),
                'connection_spans' => $privateConnectionSpans->count(),
                'related_spans' => $relatedSpans->count()
            ];

            if ($isDryRun) {
                return response()->json([
                    'success' => true,
                    'message' => "Found {$totalItems} private items to make public: " . 
                                "{$privateThings->count()} things, " .
                                "{$privateConnectionSpans->count()} connection spans, " .
                                "{$relatedSpans->count()} related spans. " .
                                "Will also make {$privateConnections->count()} connections accessible.",
                    'changes' => $changes,
                    'dry_run' => true
                ]);
            }

            // Make the changes
            $updatedThings = 0;
            $updatedConnectionSpans = 0;
            $updatedRelatedSpans = 0;

            // Update things
            foreach ($privateThings as $thing) {
                $thing->access_level = 'public';
                $thing->save();
                $updatedThings++;
            }

            // Update connection spans
            foreach ($privateConnectionSpans as $connectionSpan) {
                $connectionSpan->access_level = 'public';
                $connectionSpan->save();
                $updatedConnectionSpans++;
            }

            // Update related spans
            foreach ($relatedSpans as $span) {
                $span->access_level = 'public';
                $span->save();
                $updatedRelatedSpans++;
            }

            $totalUpdated = $updatedThings + $updatedConnectionSpans + $updatedRelatedSpans;

            Log::info('Things and related items made public', [
                'updated_things' => $updatedThings,
                'updated_connection_spans' => $updatedConnectionSpans,
                'updated_related_spans' => $updatedRelatedSpans,
                'connections_found' => $privateConnections->count(),
                'subtype_filter' => $subtype,
                'owner_filter' => $ownerEmail,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully made {$totalUpdated} items public: " .
                            "{$updatedThings} things, " .
                            "{$updatedConnectionSpans} connection spans, " .
                            "{$updatedRelatedSpans} related spans! " .
                            "Found {$privateConnections->count()} connections that will be accessible.",
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

    /**
     * Prewarm the Wikipedia On This Day cache for every day in the year
     */
    public function prewarmWikipediaCache(Request $request)
    {
        $this->middleware(['auth', 'admin']);
        
        // Check if this is an AJAX request for progress updates
        if ($request->ajax() && $request->has('action') && $request->action === 'progress') {
            return $this->getPrewarmProgress();
        }
        
        // Check if this is an AJAX request to check current status
        if ($request->ajax() && $request->has('action') && $request->action === 'check-status') {
            return $this->getPrewarmStatus();
        }
        
        // Check if this is an AJAX request to start the prewarm
        if ($request->ajax() && $request->has('action') && $request->action === 'start') {
            $mode = $request->get('mode', 'prewarm'); // Default to prewarm if not specified
            return $this->startPrewarmOperation($mode);
        }
        
        // Check if this is an AJAX request to process a single day
        if ($request->ajax() && $request->has('action') && $request->action === 'process-day') {
            return $this->processSingleDay($request);
        }
        
        // Show the progress page
        return view('admin.tools.prewarm-wikipedia-cache');
    }
    
    /**
     * Start the prewarm operation
     */
    private function startPrewarmOperation($mode = 'prewarm')
    {
        $allDates = $this->generateAllValidDates();
        $existingResults = session('wikipedia_prewarm_results');
        
        if ($mode === 'refresh' || !$existingResults) {
            // Refresh mode or no existing session - start from scratch
            session(['wikipedia_prewarm_results' => [
                'total_days' => count($allDates),
                'success_days' => 0,
                'errors' => [],
                'progress' => [],
                'pending_dates' => $allDates,
                'processed_dates' => [],
                'current_date' => null,
                'is_complete' => false,
                'is_running' => true
            ]]);
            
            return response()->json([
                'success' => true,
                'message' => 'Refresh operation initialized - starting from scratch',
                'total_days' => count($allDates)
            ]);
        } else {
            // Prewarm mode with existing session - continue from where we left off
            $processedDates = $existingResults['processed_dates'] ?? [];
            $pendingDates = array_filter($allDates, function($date) use ($processedDates) {
                return !in_array($date['key'], $processedDates);
            });
            
            // Reset the session with remaining dates
            session(['wikipedia_prewarm_results' => [
                'total_days' => count($allDates),
                'success_days' => $existingResults['success_days'] ?? 0,
                'errors' => $existingResults['errors'] ?? [],
                'progress' => $existingResults['progress'] ?? [],
                'pending_dates' => array_values($pendingDates), // Re-index array
                'processed_dates' => $processedDates,
                'current_date' => null,
                'is_complete' => false,
                'is_running' => true
            ]]);
            
            return response()->json([
                'success' => true,
                'message' => 'Prewarm operation continued - ' . count($pendingDates) . ' days remaining',
                'total_days' => count($allDates),
                'remaining_days' => count($pendingDates),
                'already_processed' => count($processedDates)
            ]);
        }
    }
    
    /**
     * Process a single day
     */
    private function processSingleDay(Request $request)
    {
        $results = session('wikipedia_prewarm_results');
        
        if (!$results || !$results['is_running']) {
            return response()->json([
                'success' => false,
                'message' => 'No active prewarm operation'
            ]);
        }
        
        // Get the next pending date
        if (empty($results['pending_dates'])) {
            // All dates processed
            $results['is_complete'] = true;
            $results['is_running'] = false;
            session(['wikipedia_prewarm_results' => $results]);
            
            return response()->json([
                'success' => true,
                'is_complete' => true,
                'summary' => [
                    'total_days' => $results['total_days'],
                    'success_days' => $results['success_days'],
                    'errors' => $results['errors'],
                    'progress' => $results['progress']
                ]
            ]);
        }
        
        // Get next date to process
        $nextDate = array_shift($results['pending_dates']);
        $month = $nextDate['month'];
        $day = $nextDate['day'];
        $dateKey = $nextDate['key'];
        $dateLabel = $nextDate['label'];
        
        // Update current date
        $results['current_date'] = $dateKey;
        session(['wikipedia_prewarm_results' => $results]);
        
        // Process this specific date
        $service = new \App\Services\WikipediaOnThisDayService();
        $rawData = null;
        $totalEvents = $totalBirths = $totalDeaths = 0;
        try {
            // Fetch the raw Wikipedia data (from cache or API)
            $cacheKey = "wikipedia_onthisday_raw_{$month}_{$day}";
            $rawData = \Cache::get($cacheKey);
            if (!$rawData) {
                // If not in cache, fetch and cache it
                $service->getOnThisDay($month, $day); // This will cache the raw data
                $rawData = \Cache::get($cacheKey);
            }
            $totalEvents = isset($rawData['events']) && is_array($rawData['events']) ? count($rawData['events']) : 0;
            $totalBirths = isset($rawData['births']) && is_array($rawData['births']) ? count($rawData['births']) : 0;
            $totalDeaths = isset($rawData['deaths']) && is_array($rawData['deaths']) ? count($rawData['deaths']) : 0;
        } catch (\Throwable $e) {
            // If something goes wrong, just leave totals as 0
        }
        try {
            $data = $service->getOnThisDay($month, $day);
            $hasData = !empty($data['events']) || !empty($data['births']) || !empty($data['deaths']);
            
            if ($hasData) {
                $results['success_days']++;
                $status = 'success';
                $message = 'Cached successfully';
            } else {
                $status = 'warning';
                $message = 'No data available';
            }
            
            $progressItem = [
                'date' => $dateKey,
                'label' => $dateLabel,
                'status' => $status,
                'message' => $message,
                'events_count' => $totalEvents,
                'births_count' => $totalBirths,
                'deaths_count' => $totalDeaths
            ];
            
        } catch (\Throwable $e) {
            $results['errors'][] = "{$month}-{$day}: " . $e->getMessage();
            $progressItem = [
                'date' => $dateKey,
                'label' => $dateLabel,
                'status' => 'error',
                'message' => $e->getMessage(),
                'events_count' => $totalEvents,
                'births_count' => $totalBirths,
                'deaths_count' => $totalDeaths
            ];
        }
        
        // Add to progress and processed dates
        $results['progress'][] = $progressItem;
        $results['processed_dates'][] = $dateKey;
        
        // Update session
        session(['wikipedia_prewarm_results' => $results]);
        
        return response()->json([
            'success' => true,
            'current_date' => $dateKey,
            'current_label' => $dateLabel,
            'progress_item' => $progressItem,
            'summary' => [
                'total_days' => $results['total_days'],
                'success_days' => $results['success_days'],
                'errors' => $results['errors'],
                'remaining_days' => count($results['pending_dates'])
            ]
        ]);
    }
    
    /**
     * Generate all valid dates for the year
     */
    private function generateAllValidDates()
    {
        $dates = [];
        
        for ($month = 1; $month <= 12; $month++) {
            for ($day = 1; $day <= 31; $day++) {
                // Skip invalid dates
                if (!checkdate($month, $day, 2024)) continue;
                
                $dateKey = sprintf('%02d-%02d', $month, $day);
                $dateLabel = date('F j', mktime(0, 0, 0, $month, $day, 2024));
                
                $dates[] = [
                    'month' => $month,
                    'day' => $day,
                    'key' => $dateKey,
                    'label' => $dateLabel
                ];
            }
        }
        
        return $dates;
    }
    
    /**
     * Get prewarm progress (for AJAX calls)
     */
    private function getPrewarmProgress()
    {
        $results = session('wikipedia_prewarm_results');
        
        if (!$results) {
            return response()->json([
                'success' => false,
                'message' => 'No prewarm operation found'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'summary' => $results
        ]);
    }
    
    /**
     * Get prewarm status (for initial page load)
     */
    private function getPrewarmStatus()
    {
        $results = session('wikipedia_prewarm_results');
        
        if (!$results) {
            return response()->json([
                'success' => false,
                'message' => 'No prewarm operation found'
            ]);
        }
        
        $processedDays = count($results['processed_dates'] ?? []);
        $remainingDays = count($results['pending_dates'] ?? []);
        $totalDays = $results['total_days'] ?? 366;
        
        return response()->json([
            'success' => true,
            'total_days' => $totalDays,
            'processed_days' => $processedDays,
            'remaining_days' => $remainingDays,
            'success_days' => $results['success_days'] ?? 0,
            'errors' => $results['errors'] ?? [],
            'is_complete' => $results['is_complete'] ?? false,
            'is_running' => $results['is_running'] ?? false
        ]);
    }

    /**
     * Count the number of cached Wikipedia On This Day days
     */
    private function countWikipediaCachedDays(): int
    {
        $count = 0;
        for ($month = 1; $month <= 12; $month++) {
            for ($day = 1; $day <= 31; $day++) {
                if (!checkdate($month, $day, 2024)) continue;
                $cacheKey = "wikipedia_onthisday_raw_{$month}_{$day}";
                if (\Cache::has($cacheKey)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Show the person subtype management page
     */
    public function managePersonSubtypes(Request $request)
    {
        $query = Span::where('type_id', 'person')
            ->with(['owner', 'updater'])
            ->select('spans.*')
            ->selectRaw("metadata->>'subtype' as subtype")
            ->selectRaw('EXISTS(SELECT 1 FROM users WHERE users.personal_span_id = spans.id) as is_personal_span');

        // Apply subtype filter
        if ($request->filled('filter_subtype')) {
            $subtypeFilter = $request->filter_subtype;
            if ($subtypeFilter === 'uncategorized') {
                $query->whereRaw("metadata->>'subtype' IS NULL OR metadata->>'subtype' = ''");
            } else {
                $query->whereRaw("metadata->>'subtype' = ?", [$subtypeFilter]);
            }
        }

        // Apply access level filter
        if ($request->filled('filter_access')) {
            $query->where('access_level', $request->filter_access);
        }

        $people = $query->orderBy('name')->paginate(50)->appends($request->query());

        $subtypeCounts = Span::where('type_id', 'person')
            ->selectRaw("metadata->>'subtype' as subtype, COUNT(*) as count")
            ->groupBy(DB::raw("metadata->>'subtype'"))
            ->pluck('count', 'subtype')
            ->toArray();

        return view('admin.tools.manage-person-subtypes', compact('people', 'subtypeCounts'));
    }

    /**
     * Update person subtypes in bulk
     */
    public function updatePersonSubtypes(Request $request)
    {
        $validated = $request->validate([
            'selected_subtypes' => 'required|string',
        ]);

        $selectedSubtypes = json_decode($validated['selected_subtypes'], true);
        
        if (!is_array($selectedSubtypes)) {
            return redirect()->route('admin.tools.manage-person-subtypes')
                ->with('status', 'Invalid data format.');
        }

        $updated = 0;
        $errors = [];

        foreach ($selectedSubtypes as $spanId => $subtype) {
            try {
                $span = Span::find($spanId);
                
                if (!$span) {
                    $errors[] = "Span with ID {$spanId} not found.";
                    continue;
                }
                
                // Validate subtype
                if (!in_array($subtype, ['public_figure', 'private_individual'])) {
                    $errors[] = "Invalid subtype '{$subtype}' for {$span->name}.";
                    continue;
                }
                
                // Update the subtype in metadata
                $metadata = $span->metadata ?? [];
                $metadata['subtype'] = $subtype;
                $span->metadata = $metadata;
                
                // If changing to public_figure, also set access_level to public
                if ($subtype === 'public_figure' && $span->access_level === 'private') {
                    $span->access_level = 'public';
                }
                
                $span->save();
                
                // If this is now a public figure, ensure all its connections are public
                if ($subtype === 'public_figure') {
                    $this->makePublicFigureConnectionsPublic($span);
                }
                
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to update {$span->name}: " . $e->getMessage();
            }
        }

        $message = "Updated {$updated} people successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return redirect()->route('admin.tools.manage-person-subtypes')
            ->with('status', $message);
    }
    
    /**
     * Show the public figure connection fixer page
     */
    public function fixPublicFigureConnections(Request $request)
    {
        // Get all public figures
        $publicFigures = Span::where('type_id', 'person')
            ->whereRaw("metadata->>'subtype' = 'public_figure'")
            ->with(['owner', 'updater'])
            ->orderBy('name')
            ->get();
        
        $stats = [
            'total_public_figures' => $publicFigures->count(),
            'public_figures_with_private_connections' => 0,
            'total_private_connections' => 0,
            'fixed_connections' => 0
        ];
        
        // Count public figures with private connections
        foreach ($publicFigures as $figure) {
            $privateConnections = $this->getPrivateConnectionsForSpan($figure);
            if ($privateConnections->count() > 0) {
                $stats['public_figures_with_private_connections']++;
                $stats['total_private_connections'] += $privateConnections->count();
            }
        }
        
        return view('admin.tools.fix-public-figure-connections', compact('publicFigures', 'stats'));
    }
    
    /**
     * Fix public figure connections (make them public)
     */
    public function fixPublicFigureConnectionsAction(Request $request)
    {
        $validated = $request->validate([
            'figure_ids' => 'required|string',
            'batch_size' => 'nullable|integer|min:1|max:100'
        ]);
        
        $figureIds = explode(',', $validated['figure_ids']);
        $batchSize = $validated['batch_size'] ?? 10; // Default batch size of 10
        $totalFigures = count($figureIds);
        $processedFigures = 0;
        $fixedConnections = 0;
        $errors = [];
        
        // Process in batches
        $batches = array_chunk($figureIds, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            $totalBatches = count($batches);
            
            Log::info("Processing batch {$batchNumber}/{$totalBatches} for public figure connections", [
                'batch_size' => count($batch),
                'total_figures' => $totalFigures,
                'processed_so_far' => $processedFigures
            ]);
            
            foreach ($batch as $figureId) {
                try {
                    $figure = Span::find($figureId);
                    
                    if (!$figure) {
                        $errors[] = "Public figure with ID {$figureId} not found.";
                        continue;
                    }
                    
                    // Verify this is actually a public figure
                    $metadata = $figure->metadata ?? [];
                    $subtype = $metadata['subtype'] ?? null;
                    
                    if ($subtype !== 'public_figure') {
                        $errors[] = "Span '{$figure->name}' is not a public figure.";
                        continue;
                    }
                    
                    // Make the figure public if it isn't already
                    if ($figure->access_level !== 'public') {
                        $figure->access_level = 'public';
                        $figure->save();
                    }
                    
                    // Get all connections for this figure
                    $subjectConnections = \App\Models\Connection::where('parent_id', $figure->id)->get();
                    $objectConnections = \App\Models\Connection::where('child_id', $figure->id)->get();
                    $allConnections = $subjectConnections->merge($objectConnections);
                    
                    foreach ($allConnections as $connection) {
                        if ($connection->connectionSpan && $connection->connectionSpan->access_level !== 'public') {
                            $connection->connectionSpan->access_level = 'public';
                            $connection->connectionSpan->saveQuietly();
                            $connection->connectionSpan->clearAllTimelineCaches();
                            $fixedConnections++;
                        }
                    }
                    
                    // Clear timeline caches for the figure
                    $figure->clearAllTimelineCaches();
                    $processedFigures++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Failed to fix connections for {$figure->name}: " . $e->getMessage();
                }
            }
            
            // Add a small delay between batches to prevent overwhelming the system
            if ($batchIndex < count($batches) - 1) {
                usleep(100000); // 0.1 second delay
            }
        }
        
        $message = "Fixed {$fixedConnections} private connections for {$processedFigures} public figures (processed in " . count($batches) . " batches).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        return redirect()->route('admin.tools.fix-public-figure-connections')
            ->with('status', $message);
    }
    
    /**
     * Start batch processing of public figure connections
     */
    public function startBatchFixPublicFigureConnections(Request $request)
    {
        $validated = $request->validate([
            'figure_ids' => 'required|string',
            'batch_size' => 'nullable|integer|min:1|max:100'
        ]);
        
        $figureIds = explode(',', $validated['figure_ids']);
        $batchSize = $validated['batch_size'] ?? 10;
        
        // Store batch information in session for progress tracking
        session([
            'batch_fix_public_figures' => [
                'figure_ids' => $figureIds,
                'batch_size' => $batchSize,
                'total_figures' => count($figureIds),
                'total_batches' => ceil(count($figureIds) / $batchSize),
                'current_batch' => 0,
                'processed_figures' => 0,
                'fixed_connections' => 0,
                'errors' => [],
                'started_at' => now(),
                'status' => 'running'
            ]
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Batch processing started',
            'total_figures' => count($figureIds),
            'total_batches' => ceil(count($figureIds) / $batchSize),
            'batch_size' => $batchSize
        ]);
    }
    
    /**
     * Process next batch of public figure connections
     */
    public function processBatchFixPublicFigureConnections(Request $request)
    {
        $batchInfo = session('batch_fix_public_figures');
        
        if (!$batchInfo || $batchInfo['status'] !== 'running') {
            return response()->json([
                'success' => false,
                'message' => 'No batch processing in progress'
            ]);
        }
        
        $figureIds = $batchInfo['figure_ids'];
        $batchSize = $batchInfo['batch_size'];
        $currentBatch = $batchInfo['current_batch'];
        $totalBatches = $batchInfo['total_batches'];
        
        // Get current batch of figure IDs
        $batches = array_chunk($figureIds, $batchSize);
        
        $currentBatchIds = $batches[$currentBatch];
        $batchFixedConnections = 0;
        $batchErrors = [];
        
        // Process current batch
        foreach ($currentBatchIds as $figureId) {
            try {
                $figure = Span::find($figureId);
                
                if (!$figure) {
                    $batchErrors[] = "Public figure with ID {$figureId} not found.";
                    continue;
                }
                
                // Verify this is actually a public figure
                $metadata = $figure->metadata ?? [];
                $subtype = $metadata['subtype'] ?? null;
                
                if ($subtype !== 'public_figure') {
                    $batchErrors[] = "Span '{$figure->name}' is not a public figure.";
                    continue;
                }
                
                // Make the figure public if it isn't already
                if ($figure->access_level !== 'public') {
                    $figure->access_level = 'public';
                    $figure->save();
                }
                
                // Get all connections for this figure
                $subjectConnections = \App\Models\Connection::where('parent_id', $figure->id)->get();
                $objectConnections = \App\Models\Connection::where('child_id', $figure->id)->get();
                $allConnections = $subjectConnections->merge($objectConnections);
                
                foreach ($allConnections as $connection) {
                    if ($connection->connectionSpan && $connection->connectionSpan->access_level !== 'public') {
                        $connection->connectionSpan->access_level = 'public';
                        $connection->connectionSpan->saveQuietly();
                        $connection->connectionSpan->clearAllTimelineCaches();
                        $batchFixedConnections++;
                    }
                }
                
                // Clear timeline caches for the figure
                $figure->clearAllTimelineCaches();
                $batchInfo['processed_figures']++;
                
            } catch (\Exception $e) {
                $batchErrors[] = "Failed to fix connections for {$figure->name}: " . $e->getMessage();
            }
        }
        
        // Update batch info
        $batchInfo['current_batch']++;
        $batchInfo['fixed_connections'] += $batchFixedConnections;
        $batchInfo['errors'] = array_merge($batchInfo['errors'], $batchErrors);
        
        // Check if all batches have been processed
        if ($batchInfo['processed_figures'] >= $batchInfo['total_figures']) {
            // All batches processed
            $batchInfo['status'] = 'completed';
            session(['batch_fix_public_figures' => $batchInfo]);
            
            return response()->json([
                'success' => true,
                'completed' => true,
                'message' => 'Batch processing completed',
                'total_fixed_connections' => $batchInfo['fixed_connections'],
                'total_processed_figures' => $batchInfo['processed_figures'],
                'errors' => $batchInfo['errors']
            ]);
        }
        
        session(['batch_fix_public_figures' => $batchInfo]);
        
        return response()->json([
            'success' => true,
            'completed' => false,
            'current_batch' => $batchInfo['current_batch'],
            'total_batches' => $totalBatches,
            'processed_figures' => $batchInfo['processed_figures'],
            'total_figures' => $batchInfo['total_figures'],
            'batch_fixed_connections' => $batchFixedConnections,
            'total_fixed_connections' => $batchInfo['fixed_connections'],
            'batch_errors' => $batchErrors,
            'progress_percentage' => round(($batchInfo['current_batch'] / $totalBatches) * 100, 1)
        ]);
    }
    
    /**
     * Get batch processing status
     */
    public function getBatchFixPublicFigureConnectionsStatus(Request $request)
    {
        $batchInfo = session('batch_fix_public_figures');
        
        if (!$batchInfo) {
            return response()->json([
                'success' => false,
                'message' => 'No batch processing in progress'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'status' => $batchInfo['status'],
            'current_batch' => $batchInfo['current_batch'],
            'total_batches' => $batchInfo['total_batches'],
            'processed_figures' => $batchInfo['processed_figures'],
            'total_figures' => $batchInfo['total_figures'],
            'fixed_connections' => $batchInfo['fixed_connections'],
            'errors' => $batchInfo['errors'],
            'started_at' => $batchInfo['started_at'],
            'progress_percentage' => $batchInfo['status'] === 'completed' ? 100 : round(($batchInfo['current_batch'] / $batchInfo['total_batches']) * 100, 1)
        ]);
    }
    
    /**
     * Show the private individual connection fixer page
     */
    public function fixPrivateIndividualConnections(Request $request)
    {
        // Get all private individuals
        $privateIndividuals = Span::where('type_id', 'person')
            ->whereRaw("metadata->>'subtype' = 'private_individual'")
            ->with(['owner', 'updater'])
            ->orderBy('name')
            ->get();
        
        $stats = [
            'total_private_individuals' => $privateIndividuals->count(),
            'private_individuals_with_public_connections' => 0,
            'total_public_connections' => 0,
            'fixed_connections' => 0
        ];
        
        // Count private individuals with public connections
        foreach ($privateIndividuals as $individual) {
            $publicConnections = $this->getPublicConnectionsForSpan($individual);
            if ($publicConnections->count() > 0) {
                $stats['private_individuals_with_public_connections']++;
                $stats['total_public_connections'] += $publicConnections->count();
            }
        }
        
        return view('admin.tools.fix-private-individual-connections', compact('privateIndividuals', 'stats'));
    }
    
    /**
     * Fix private individual connections (make them private)
     */
    public function fixPrivateIndividualConnectionsAction(Request $request)
    {
        $validated = $request->validate([
            'individual_ids' => 'required|string',
            'batch_size' => 'nullable|integer|min:1|max:100'
        ]);
        
        $individualIds = explode(',', $validated['individual_ids']);
        $batchSize = $validated['batch_size'] ?? 10; // Default batch size of 10
        $totalIndividuals = count($individualIds);
        $processedIndividuals = 0;
        $fixedConnections = 0;
        $errors = [];
        
        // Process in batches
        $batches = array_chunk($individualIds, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchNumber = $batchIndex + 1;
            $totalBatches = count($batches);
            
            Log::info("Processing batch {$batchNumber}/{$totalBatches} for private individual connections", [
                'batch_size' => count($batch),
                'total_individuals' => $totalIndividuals,
                'processed_so_far' => $processedIndividuals
            ]);
            
            foreach ($batch as $individualId) {
                try {
                    $individual = Span::find($individualId);
                    
                    if (!$individual) {
                        $errors[] = "Private individual with ID {$individualId} not found.";
                        continue;
                    }
                    
                    // Verify this is actually a private individual
                    $metadata = $individual->metadata ?? [];
                    $subtype = $metadata['subtype'] ?? null;
                    
                    if ($subtype !== 'private_individual') {
                        $errors[] = "Span '{$individual->name}' is not a private individual.";
                        continue;
                    }
                    
                    // Make the individual private if it isn't already
                    if ($individual->access_level !== 'private') {
                        $individual->access_level = 'private';
                        $individual->save();
                    }
                    
                    // Get all connections for this individual
                    $subjectConnections = \App\Models\Connection::where('parent_id', $individual->id)->get();
                    $objectConnections = \App\Models\Connection::where('child_id', $individual->id)->get();
                    $allConnections = $subjectConnections->merge($objectConnections);
                    
                    foreach ($allConnections as $connection) {
                        if ($connection->connectionSpan && $connection->connectionSpan->access_level !== 'private') {
                            $connection->connectionSpan->access_level = 'private';
                            $connection->connectionSpan->saveQuietly();
                            $connection->connectionSpan->clearAllTimelineCaches();
                            $fixedConnections++;
                        }
                    }
                    
                    // Clear timeline caches for the individual
                    $individual->clearAllTimelineCaches();
                    $processedIndividuals++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Failed to fix connections for {$individual->name}: " . $e->getMessage();
                }
            }
            
            // Add a small delay between batches to prevent overwhelming the system
            if ($batchIndex < count($batches) - 1) {
                usleep(100000); // 0.1 second delay
            }
        }
        
        $message = "Fixed {$fixedConnections} public connections for {$processedIndividuals} private individuals (processed in " . count($batches) . " batches).";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        return redirect()->route('admin.tools.fix-private-individual-connections')
            ->with('status', $message);
    }
    
    /**
     * Get public connections for a span
     */
    public function getPublicConnectionsForSpan(Span $span): \Illuminate\Support\Collection
    {
        $subjectConnections = \App\Models\Connection::where('parent_id', $span->id)
            ->whereHas('connectionSpan', function($q) {
                $q->where('access_level', 'public');
            })
            ->get();
            
        $objectConnections = \App\Models\Connection::where('child_id', $span->id)
            ->whereHas('connectionSpan', function($q) {
                $q->where('access_level', 'public');
            })
            ->get();
            
        return $subjectConnections->merge($objectConnections);
    }
    
    /**
     * Get private connections for a span
     */
    public function getPrivateConnectionsForSpan(Span $span): \Illuminate\Support\Collection
    {
        $subjectConnections = \App\Models\Connection::where('parent_id', $span->id)
            ->whereHas('connectionSpan', function($q) {
                $q->where('access_level', '!=', 'public');
            })
            ->get();
            
        $objectConnections = \App\Models\Connection::where('child_id', $span->id)
            ->whereHas('connectionSpan', function($q) {
                $q->where('access_level', '!=', 'public');
            })
            ->get();
            
        return $subjectConnections->merge($objectConnections);
    }
    
    /**
     * Make all connections for a public figure public
     */
    private function makePublicFigureConnectionsPublic(Span $span): void
    {
        // Get all connections where this span is the subject (parent)
        $subjectConnections = \App\Models\Connection::where('parent_id', $span->id)->get();
        
        // Get all connections where this span is the object (child)
        $objectConnections = \App\Models\Connection::where('child_id', $span->id)->get();
        
        $allConnections = $subjectConnections->merge($objectConnections);
        
        foreach ($allConnections as $connection) {
            // Get the connection span (the span that represents this connection)
            if ($connection->connectionSpan) {
                $connectionSpan = $connection->connectionSpan;
                
                // If the connection span is not public, make it public
                if ($connectionSpan->access_level !== 'public') {
                    Log::info('Making public figure connection public', [
                        'public_figure_id' => $span->id,
                        'public_figure_name' => $span->name,
                        'connection_id' => $connection->id,
                        'connection_span_id' => $connectionSpan->id,
                        'old_access_level' => $connectionSpan->access_level,
                        'new_access_level' => 'public'
                    ]);
                    
                    $connectionSpan->access_level = 'public';
                    $connectionSpan->saveQuietly(); // Save without triggering observers
                    
                    // Clear timeline caches for the connection span
                    $connectionSpan->clearAllTimelineCaches();
                }
            }
        }
        
        // Clear timeline caches for the public figure
        $span->clearAllTimelineCaches();
    }
} 
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
} 
<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\Auth\EmailFirstAuthController;
use App\Http\Controllers\Auth\SessionBridgeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpanController as AdminSpanController;

use App\Http\Controllers\Admin\SpanAccessController;
use App\Http\Controllers\Admin\SpanAccessManagerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SpanTypeController;
use App\Http\Controllers\Admin\ConnectionTypeController;
use App\Http\Controllers\Admin\ConnectionController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\VisualizerController;
use App\Http\Controllers\Admin\MusicBrainzImportController;
use App\Http\Controllers\Admin\FilmImportController;
use App\Http\Controllers\Admin\BookImportController;
use App\Http\Controllers\AdminModeController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\NewSpanController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\FooterController;
use App\Http\Middleware\SpanShowTimeoutMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Providers\RouteServiceProvider;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Core routes for the Lifespan application. We start with just the basics
| and will add more sophisticated routing as we build out the system.
|
*/

// Health check endpoint for production monitoring
Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();
        
        // Check if we can perform a simple query
        DB::select('SELECT 1');
        
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'database' => 'connected',
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage()
        ], 500);
    }
});

// Debug route for troubleshooting
Route::get('/debug', function() {
    try {
        // Test basic database connection
        $dbStatus = DB::connection()->getPdo() ? 'connected' : 'failed';
        
        // Check if spans table exists and get count
        $spansCount = DB::table('spans')->count();
        
        // List installed tables
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', ['public']);
        $tableNames = array_map(function($table) {
            return $table->table_name;
        }, $tables);
        
        return response()->json([
            'status' => 'debug info',
            'database' => $dbStatus,
            'spans_count' => $spansCount,
            'tables' => $tableNames,
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'debug_enabled' => config('app.debug')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Error testing route - only available in non-production environments
Route::get('/error', function(Request $request) {
    if (app()->environment('production')) {
        abort(404);
    }
    
    $code = $request->query('code', '404');
    $validCodes = ['400', '401', '403', '404', '419', '422', '429', '500', '503'];
    
    if (!in_array($code, $validCodes)) {
        // Return 404 for invalid error codes instead of showing a Laravel error
        abort(404);
    }
    
    // Simulate the error by calling abort with the specified code
    abort((int) $code);
})->name('error.test');

// Sentry test route - works in all environments
Route::post('/sentry-test', function() {
    try {
        if (app()->bound('sentry')) {
            // Send a test event to Sentry with proper Severity object
            app('sentry')->captureMessage('Test event from ' . app()->environment(), \Sentry\Severity::info());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Test event sent to Sentry',
                'environment' => app()->environment(),
                'sentry_configured' => true
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Sentry not configured',
                'environment' => app()->environment(),
                'sentry_configured' => false
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to send test event',
            'error' => $e->getMessage(),
            'environment' => app()->environment()
        ], 500);
    }
})->name('sentry.test');

Route::middleware('web')->group(function () {
    // Footer content route (for modal content)
    Route::get('/footer/content/{type}', [FooterController::class, 'content'])
        ->where('type', 'about|privacy|terms|contact')
        ->name('footer.content');
    
    // Home route - public for guests, but requires profile completion for authenticated users
    // The profile.complete middleware checks Auth::check() first, so guests can still access
    Route::get('/', function () {
        return view('home');
    })->middleware('profile.complete')->name('home');
    
    // Personal homepage mode
    Route::get('/me', function () {
        return view('me');
    })->middleware('profile.complete')->name('me');
    
    // Activity workspace mode
    Route::get('/activity', function () {
        return view('activity');
    })->middleware('profile.complete')->name('activity');

        Route::get('/places/{span}/boundary', [\App\Http\Controllers\PlaceBoundaryController::class, 'show'])
            ->name('places.boundary');

    // Places routes (index must come before show to avoid conflicts)
    Route::get('/places', [\App\Http\Controllers\PlacesController::class, 'index'])->name('places.index');
    // Geo edit (must come before /places/{span} so {span}/geo is matched)
    Route::get('/places/{span}/geo', [\App\Http\Controllers\PlaceGeoController::class, 'edit'])->name('places.geo.edit');
    Route::put('/places/{span}/geo', [\App\Http\Controllers\PlaceGeoController::class, 'update'])->name('places.geo.update');
    // Route model binding handles both UUIDs and slugs via RouteServiceProvider
    Route::get('/places/{span}', [\App\Http\Controllers\PlacesController::class, 'show'])
        ->name('places.show');

    // Explore routes
    Route::prefix('explore')->group(function () {
        Route::get('/', [SpanController::class, 'explore'])->name('explore.index');
        Route::get('/desert-island-discs', [SpanController::class, 'desertIslandDiscs'])->name('explore.desert-island-discs');
        Route::get('/plaques', [SpanController::class, 'explorePlaques'])->name('explore.plaques');
        Route::get('/family', [SpanController::class, 'exploreFamily'])->name('explore.family');
        Route::get('/journeys', [JourneyController::class, 'index'])->name('explore.journeys');
        Route::post('/journeys/discover', [JourneyController::class, 'discover'])->name('explore.journeys.discover');
        Route::get('/journeys/random', [JourneyController::class, 'random'])->name('explore.journeys.random');
        Route::get('/at-your-age', [SpanController::class, 'atYourAge'])->name('explore.at-your-age');
        Route::get('/films', [SpanController::class, 'exploreFilms'])->name('explore.films');
    });

    // Date exploration route - supports YYYY, YYYY-MM, and YYYY-MM-DD formats
    Route::get('/date/{date}', [SpanController::class, 'exploreDate'])
        ->where('date', '[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?')
        ->name('date.explore');

    // Span connection tooltip data (JSON)
    Route::get('/api/spans/{span}/connection-to/{other}', [\App\Http\Controllers\Api\SpanConnectionController::class, 'show'])
        ->name('api.spans.connection');

    Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
        // Info page with summary and stats
        Route::get('/info', [App\Http\Controllers\InfoController::class, 'index'])->name('info');
        
        // Research routes
        Route::get('/research', [App\Http\Controllers\ResearchController::class, 'index'])->name('research.index');
        Route::get('/research/{span}', [App\Http\Controllers\ResearchController::class, 'show'])->name('research.show');
        
        Route::prefix('new')->group(function () {
            Route::get('/span', [NewSpanController::class, 'showSpan'])->name('new.span');
            Route::get('/person-role-org', [NewSpanController::class, 'showPersonRoleOrganisation'])->name('new.person-role-org');
            Route::post('/person-role-org', [NewSpanController::class, 'storePersonRoleOrganisation'])->name('new.person-role-org.store');
            Route::post('/person-role-org/preview', [NewSpanController::class, 'previewBulkPersonRoleOrganisation'])->name('new.person-role-org.preview');
            Route::post('/person-role-org/bulk-row', [NewSpanController::class, 'storeBulkPersonRoleOrganisationRow'])->name('new.person-role-org.bulk-row');
            Route::post('/person-role-org/bulk', [NewSpanController::class, 'storeBulkPersonRoleOrganisation'])->name('new.person-role-org.bulk');
        });
    });

    // Span routes
    Route::prefix('spans')->group(function () {
            // Search route (works with session auth)
            Route::get('/search', [SpanController::class, 'search'])->name('spans.search');
            
            // Timeline routes moved to /api/spans/{span} for better separation of HTML and JSON endpoints
            
            // Types route (public)
            Route::get('/types', [SpanController::class, 'types'])->name('spans.types');
            Route::get('/types/{type}', [SpanController::class, 'showType'])->name('spans.types.show');
            Route::get('/types/{type}/subtype-options', [SpanController::class, 'subtypeOptions'])->name('spans.types.subtype-options');
            Route::get('/types/{type}/subtypes', [SpanController::class, 'showSubtypes'])->name('spans.types.subtypes');
            Route::get('/types/{type}/subtypes/{subtype}', [SpanController::class, 'showTypeSubtype'])->name('spans.types.subtypes.show');
            
            // Protected routes
            Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {

            Route::get('/shared-with-me', [SpanController::class, 'sharedWithMe'])->name('spans.shared-with-me');
            Route::get('/create', [SpanController::class, 'create'])->name('spans.create');
            Route::post('/', [SpanController::class, 'store'])->name('spans.store');
            Route::get('/{span}/edit', [SpanController::class, 'edit'])->name('spans.edit');
            Route::get('/{span}/yaml', [SpanController::class, 'getYaml'])->name('spans.yaml')->middleware('timeout.prevention');
            Route::get('/{span}/editor', [SpanController::class, 'yamlEditor'])->name('spans.yaml-editor')->middleware('timeout.prevention');
            Route::get('/{span}/spanner', [SpanController::class, 'spreadsheetEditor'])->name('spans.spanner')->middleware('timeout.prevention');
Route::put('/{span}/spanner', [SpanController::class, 'updateFromSpreadsheet'])->name('spans.spanner-update')->middleware('timeout.prevention');
Route::post('/{span}/spanner/validate', [SpanController::class, 'validateSpreadsheetData'])->name('spans.spanner-validate')->middleware('timeout.prevention');
Route::post('/{span}/spanner/validate-connection', [SpanController::class, 'validateConnectionRow'])->name('spans.spanner-validate-connection')->middleware('timeout.prevention');
Route::post('/{span}/spanner/preview', [SpanController::class, 'previewSpreadsheetChanges'])->name('spans.spanner-preview')->middleware('timeout.prevention');
            Route::post('/{span}/editor/validate', [SpanController::class, 'validateYaml'])->name('spans.yaml-validate')->middleware('timeout.prevention');
            Route::post('/{span}/editor/apply', [SpanController::class, 'applyYaml'])->name('spans.yaml-apply')->middleware('timeout.prevention');
            Route::post('/{span}/improve/preview', [SpanController::class, 'previewImprovement'])->name('spans.improve.preview')->middleware('timeout.prevention');
            Route::post('/{span}/improve', [SpanController::class, 'improveWithAi'])->name('spans.improve')->middleware('timeout.prevention');
            Route::put('/{span}', [SpanController::class, 'update'])->name('spans.update');
            Route::put('/{span}/notes', function (Request $request, \App\Models\Span $span) {
                $user = auth()->user();
                if (!$user || !($span->isEditableBy($user) || $user->is_admin || $span->owner_id === $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
                $request->validate([
                    'notes' => 'nullable|string'
                ]);
                $span->update(['notes' => $request->notes]);
                return response()->json([
                    'success' => true,
                    'message' => 'Notes updated successfully',
                    'notes' => $span->notes
                ]);
            })->name('spans.notes.update');
            Route::delete('/{span}', [SpanController::class, 'destroy'])->name('spans.destroy');
            Route::get('/{span}/compare', [SpanController::class, 'compare'])->name('spans.compare');
            
            // New route for opening YAML editor with content (for new spans)
            Route::post('/editor/new', [SpanController::class, 'yamlEditorNew'])->name('spans.yaml-editor-new');
            Route::get('/editor/new', [SpanController::class, 'yamlEditorNewFromSession'])->name('spans.yaml-editor-new-session');
            Route::post('/editor/new/validate', [SpanController::class, 'validateYamlNew'])->name('spans.yaml-validate-new');
            Route::post('/editor/new/apply', [SpanController::class, 'applyYamlNew'])->name('spans.yaml-apply-new');
            
            // Merge functionality routes
            Route::post('/{span}/editor/merge', [SpanController::class, 'applyMergedYaml'])->name('spans.yaml-merge');
            
            // API search endpoint for AJAX calls (authenticated)
            Route::get('/api/search', [App\Http\Controllers\Api\SpanSearchController::class, 'search'])->name('spans.api.search');
            
            // API endpoints for comparison page functionality
            Route::post('/api/spans', function (Request $request) {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'type_id' => 'required|string|exists:span_types,type_id',
                    'access_level' => 'required|in:public,private,shared',
                    'state' => 'nullable|in:draft,placeholder,complete',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31'
                ]);
                
                $spanData = [
                    'name' => $validated['name'],
                    'type_id' => $validated['type_id'],
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => $validated['access_level'],
                    'state' => $validated['state'] ?? 'placeholder'  // Default to placeholder for API calls
                ];
                
                // Add date fields if provided
                if (isset($validated['start_year'])) $spanData['start_year'] = $validated['start_year'];
                if (isset($validated['start_month'])) $spanData['start_month'] = $validated['start_month'];
                if (isset($validated['start_day'])) $spanData['start_day'] = $validated['start_day'];
                
                $span = \App\Models\Span::create($spanData);
                
                return response()->json([
                    'success' => true,
                    'span' => $span
                ]);
            });
            
            Route::post('/api/connections', function (Request $request) {
                $validated = $request->validate([
                    'parent_id' => 'required|uuid|exists:spans,id',
                    'child_id' => 'required|uuid|exists:spans,id|different:parent_id',
                    'type_id' => 'required|string|exists:connection_types,type',
                    'age' => 'nullable|integer|min:0',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31'
                ]);
                
                // Get the parent span to calculate the connection date
                $parentSpan = \App\Models\Span::find($validated['parent_id']);
                $childSpan = \App\Models\Span::find($validated['child_id']);
                $connectionYear = null;
                
                // Use provided date if available, otherwise calculate from age
                if (isset($validated['start_year'])) {
                    $connectionYear = $validated['start_year'];
                } elseif ($parentSpan && $parentSpan->start_year && isset($validated['age'])) {
                    $connectionYear = $parentSpan->start_year + $validated['age'];
                }
                
                // Generate connection span name using the connection type predicate
                $connectionTypeName = "Connection";
                if ($parentSpan && $childSpan) {
                    $connectionType = \App\Models\ConnectionType::find($validated['type_id']);
                    if ($connectionType) {
                        $connectionTypeName = "{$parentSpan->name} {$connectionType->forward_predicate} {$childSpan->name}";
                    } else {
                        $connectionTypeName = "{$parentSpan->name} - {$childSpan->name}";
                    }
                }
                
                // Create a connection span with temporal information
                $connectionSpanData = [
                    'name' => $connectionTypeName,
                    'type_id' => 'connection',
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'private',
                    'state' => 'placeholder'
                ];
                
                // Add start date fields if provided
                if (isset($validated['start_year'])) $connectionSpanData['start_year'] = $validated['start_year'];
                if (isset($validated['start_month'])) $connectionSpanData['start_month'] = $validated['start_month'];
                if (isset($validated['start_day'])) $connectionSpanData['start_day'] = $validated['start_day'];
                
                // Add end date fields if provided
                if (isset($validated['end_year'])) $connectionSpanData['end_year'] = $validated['end_year'];
                if (isset($validated['end_month'])) $connectionSpanData['end_month'] = $validated['end_month'];
                if (isset($validated['end_day'])) $connectionSpanData['end_day'] = $validated['end_day'];
                
                $connectionSpan = \App\Models\Span::create($connectionSpanData);
                
                // Check for existing connection to prevent duplicates
                $existingConnection = \App\Models\Connection::where('parent_id', $validated['parent_id'])
                    ->where('child_id', $validated['child_id'])
                    ->where('type_id', $validated['type_id'])
                    ->first();
                
                if ($existingConnection) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A connection of this type already exists between these spans'
                    ], 422);
                }
                
                // Create the connection
                $connection = \App\Models\Connection::create([
                    'parent_id' => $validated['parent_id'],
                    'child_id' => $validated['child_id'],
                    'type_id' => $validated['type_id'],
                    'connection_span_id' => $connectionSpan->id
                ]);
                
                return response()->json([
                    'success' => true,
                    'connection' => $connection,
                    'connection_span' => $connectionSpan
                ]);
            });

            // New API routes for add connection modal
            // Note: connection-types endpoint moved to routes/api.php

            Route::get('/api/spans/search', function (Request $request) {
                $query = $request->get('q', '');
                $types = $request->get('types', '');
                $exclude = $request->get('exclude', '');
                $user = auth()->user();
                
                $spansQuery = \App\Models\Span::query()
                    ->where('name', 'ilike', "%{$query}%")
                    ->where('type_id', '!=', 'connection');
                
                // Apply access control
                if (!$user) {
                    // Guest users can only see public spans
                    $spansQuery->where('access_level', 'public');
                } elseif (!$user->is_admin) {
                    // Regular users can see public spans, their own spans, and spans they have permission to view
                    $spansQuery->where(function ($q) use ($user) {
                        $q->where('access_level', 'public')
                          ->orWhere('owner_id', $user->id)
                          ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                              $permQ->where('user_id', $user->id)
                                    ->whereIn('permission_type', ['view', 'edit']);
                          })
                          ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                              $permQ->whereNotNull('group_id')
                                    ->whereIn('permission_type', ['view', 'edit'])
                                    ->whereHas('group', function ($groupQ) use ($user) {
                                        $groupQ->whereHas('users', function ($userQ) use ($user) {
                                            $userQ->where('user_id', $user->id);
                                        });
                                    });
                          });
                    });
                }
                // Admins can see all spans (no additional where clause needed)
                
                if ($types) {
                    $typeArray = explode(',', $types);
                    $spansQuery->whereIn('type_id', $typeArray);
                }
                
                if ($exclude) {
                    $spansQuery->where('id', '!=', $exclude);
                }
                
                $spans = $spansQuery->limit(10)->get(['id', 'name', 'type_id', 'start_year']);
                
                // Add type_name to each span
                $spansWithTypeName = $spans->map(function($span) {
                    $span->type_name = ucfirst($span->type_id);
                    return $span;
                });
                
                return response()->json(['spans' => $spansWithTypeName]);
            });

            // New connection creation endpoint
            Route::post('/api/connections/create', function (Request $request) {
                $validated = $request->validate([
                    'subject_id' => 'required|uuid|exists:spans,id',
                    'object_id' => 'required|uuid|exists:spans,id|different:subject_id',
                    'predicate' => 'required|string|exists:connection_types,type',
                    'direction' => 'nullable|in:forward,inverse', // Optional, defaults to forward
                    'state' => 'required|in:placeholder,draft,complete',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31',
                    'end_year' => 'nullable|integer|min:1000|max:2100',
                    'end_month' => 'nullable|integer|min:1|max:12',
                    'end_day' => 'nullable|integer|min:1|max:31'
                ]);

                try {
                    // Get the spans and connection type
                    $subject = \App\Models\Span::findOrFail($validated['subject_id']);
                    $object = \App\Models\Span::findOrFail($validated['object_id']);
                    $connectionType = \App\Models\ConnectionType::findOrFail($validated['predicate']);
                    
                    // Determine direction - default to forward if not specified
                    $direction = $validated['direction'] ?? 'forward';
                    
                    // If inverse direction, swap subject and object for validation and creation
                    $parent = $direction === 'inverse' ? $object : $subject;
                    $child = $direction === 'inverse' ? $subject : $object;

                    // Check if user can access both spans
                    if (!$subject->isAccessibleBy(auth()->user()) || !$object->isAccessibleBy(auth()->user())) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have permission to create connections between these spans.'
                        ], 403);
                    }

                    // Validate span types based on the actual parent/child relationship
                    if (!$connectionType->isSpanTypeAllowed($parent->type_id, 'parent')) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid parent span type. Expected one of: " . 
                                        implode(', ', $connectionType->getAllowedSpanTypes('parent'))
                        ], 422);
                    }

                    if (!$connectionType->isSpanTypeAllowed($child->type_id, 'child')) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid child span type. Expected one of: " . 
                                        implode(', ', $connectionType->getAllowedSpanTypes('child'))
                        ], 422);
                    }

                    // Check for existing connection (check both directions)
                    $existingConnection = \App\Models\Connection::where(function($query) use ($parent, $child) {
                        $query->where('parent_id', $parent->id)
                              ->where('child_id', $child->id);
                    })->orWhere(function($query) use ($parent, $child) {
                        $query->where('parent_id', $child->id)
                              ->where('child_id', $parent->id);
                    })->where('type_id', $validated['predicate'])
                    ->first();

                    if ($existingConnection) {
                        return response()->json([
                            'success' => false,
                            'message' => 'A connection of this type already exists between these spans'
                        ], 422);
                    }

                    // Get the appropriate predicate based on direction
                    $predicate = $direction === 'inverse' 
                        ? $connectionType->inverse_predicate 
                        : $connectionType->forward_predicate;

                    // Create connection span with appropriate predicate
                    $connectionSpanData = [
                        'name' => "{$parent->name} {$predicate} {$child->name}",
                        'type_id' => 'connection',
                        'owner_id' => auth()->id(),
                        'updater_id' => auth()->id(),
                        'access_level' => 'private',
                        'state' => $validated['state']
                    ];

                    // Add date fields
                    if ($validated['start_year']) $connectionSpanData['start_year'] = $validated['start_year'];
                    if ($validated['start_month']) $connectionSpanData['start_month'] = $validated['start_month'];
                    if ($validated['start_day']) $connectionSpanData['start_day'] = $validated['start_day'];
                    if ($validated['end_year']) $connectionSpanData['end_year'] = $validated['end_year'];
                    if ($validated['end_month']) $connectionSpanData['end_month'] = $validated['end_month'];
                    if ($validated['end_day']) $connectionSpanData['end_day'] = $validated['end_day'];

                    $connectionSpan = \App\Models\Span::create($connectionSpanData);

                    // Create the connection with parent/child in the correct order
                    $connection = \App\Models\Connection::create([
                        'parent_id' => $parent->id,
                        'child_id' => $child->id,
                        'type_id' => $validated['predicate'],
                        'connection_span_id' => $connectionSpan->id
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Connection created successfully',
                        'data' => $connection
                    ]);

                } catch (\Exception $e) {
                    \Log::error('Error creating connection', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }
            });

            // Get connection data for editing
            Route::get('/api/connections/{connection}', function (\App\Models\Connection $connection) {
                try {
                    // Check if user can view the connection
                    if (!$connection->parent->isAccessibleBy(auth()->user()) || !$connection->child->isAccessibleBy(auth()->user())) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have permission to view this connection.'
                        ], 403);
                    }

                    $connectionSpan = $connection->connectionSpan;
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $connection->id,
                            'type' => $connection->type_id,
                            'parent_id' => $connection->parent_id,
                            'parent_name' => $connection->parent->name,
                            'parent_type' => $connection->parent->type_id,
                            'child_id' => $connection->child_id,
                            'child_name' => $connection->child->name,
                            'child_type' => $connection->child->type_id,
                            'state' => $connectionSpan ? $connectionSpan->state : 'placeholder',
                            'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                            'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                            'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                            'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                            'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                            'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        ]
                    ]);

                } catch (\Exception $e) {
                    \Log::error('Error fetching connection', [
                        'connection_id' => $connection->id,
                        'error' => $e->getMessage()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }
            });

            // Update connection dates and state endpoint
            Route::put('/api/connections/{connection}/update', function (Request $request, \App\Models\Connection $connection) {
                $validated = $request->validate([
                    'state' => 'required|in:placeholder,draft,complete',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31',
                    'end_year' => 'nullable|integer|min:1000|max:2100',
                    'end_month' => 'nullable|integer|min:1|max:12',
                    'end_day' => 'nullable|integer|min:1|max:31'
                ]);

                try {
                    // Check if user can edit the connection
                    if (!$connection->isEditableBy(auth()->user())) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have permission to edit this connection.'
                        ], 403);
                    }

                    // Get the connection span
                    $connectionSpan = $connection->connectionSpan;
                    if (!$connectionSpan) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Connection span not found'
                        ], 404);
                    }

                    // Update connection span dates and state
                    $updateData = [
                        'state' => $validated['state'],
                        'updater_id' => auth()->id()
                    ];

                    // Update date fields (set to null if not provided)
                    $updateData['start_year'] = $validated['start_year'] ?? null;
                    $updateData['start_month'] = $validated['start_month'] ?? null;
                    $updateData['start_day'] = $validated['start_day'] ?? null;
                    $updateData['end_year'] = $validated['end_year'] ?? null;
                    $updateData['end_month'] = $validated['end_month'] ?? null;
                    $updateData['end_day'] = $validated['end_day'] ?? null;

                    $connectionSpan->update($updateData);

                    // Refresh the connection to get updated relationship data
                    $connection->refresh();
                    $connection->load('connectionSpan');

                    // Clear timeline caches for parent, child, and connection spans
                    // This ensures timelines are refreshed after connection updates
                    $connection->clearTimelineCaches();
                    $connection->clearSetCaches();

                    // Also explicitly clear the all-connections cache for both parent and child
                    // This is the cache used by the /spans/{span}/connections page
                    $parentId = $connection->parent_id;
                    $childId = $connection->child_id;
                    
                    // Clear for all possible user IDs (including guest and current user)
                    // We can't iterate through all users, so clear for guest and current user explicitly
                    Cache::forget("connections_all_v3_{$parentId}_guest");
                    Cache::forget("connections_all_v3_{$childId}_guest");
                    Cache::forget("connections_all_v4_{$parentId}_guest");
                    Cache::forget("connections_all_v4_{$childId}_guest");
                    
                    if (auth()->check()) {
                        $currentUserId = auth()->id();
                        Cache::forget("connections_all_v3_{$parentId}_{$currentUserId}");
                        Cache::forget("connections_all_v3_{$childId}_{$currentUserId}");
                        Cache::forget("connections_all_v4_{$parentId}_{$currentUserId}");
                        Cache::forget("connections_all_v4_{$childId}_{$currentUserId}");
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Connection updated successfully',
                        'data' => $connection->fresh(['connectionSpan'])
                    ]);

                } catch (\Exception $e) {
                    \Log::error('Error updating connection', [
                        'connection_id' => $connection->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }
            });

            // Get notes that fall within a date range
            Route::post('/api/notes-in-date-range', function (Request $request) {
                $validated = $request->validate([
                    'span_id' => 'required|string|exists:spans,id',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31',
                    'end_year' => 'nullable|integer|min:1000|max:2100',
                    'end_month' => 'nullable|integer|min:1|max:12',
                    'end_day' => 'nullable|integer|min:1|max:31'
                ]);

                $user = auth()->user();
                $span = \App\Models\Span::findOrFail($validated['span_id']);

                // Check if user can access this span
                if (!$span->isAccessibleBy($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to access this span.'
                    ], 403);
                }

                // Find notes that fall within the span's date range
                $notes = \App\Models\Span::where('type_id', 'note')
                    ->where(function ($q) use ($user) {
                        $q->where('owner_id', $user->id)
                          ->orWhere('access_level', 'public')
                          ->orWhereHas('spanPermissions', function ($pq) use ($user) {
                              $pq->whereHas('group', function ($gq) use ($user) {
                                  $gq->whereHas('users', function ($uq) use ($user) {
                                      $uq->where('users.id', $user->id);
                                  });
                              });
                          });
                    })
                    ->where(function ($q) use ($validated) {
                        if ($validated['start_year']) {
                            $q->where('start_year', '>=', $validated['start_year']);
                        }
                        if ($validated['end_year']) {
                            $q->where('start_year', '<=', $validated['end_year']);
                        }
                    })
                    ->get()
                    ->map(function ($note) {
                        return [
                            'id' => $note->id,
                            'description' => $note->description,
                            'formatted_date' => $note->getFormattedDateRange(),
                            'author_name' => $note->owner?->personalSpan?->name ?? 'Unknown'
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'notes' => $notes
                ]);
            });

            // Create annotates connection between a note and a span
            Route::post('/api/connections/create-annotates', function (Request $request) {
                $validated = $request->validate([
                    'note_id' => 'required|string|exists:spans,id',
                    'span_id' => 'required|string|exists:spans,id|different:note_id'
                ]);

                $user = auth()->user();
                $note = \App\Models\Span::findOrFail($validated['note_id']);
                $span = \App\Models\Span::findOrFail($validated['span_id']);

                // Verify the first span is a note
                if ($note->type_id !== 'note') {
                    return response()->json([
                        'success' => false,
                        'message' => 'The first span must be a note.'
                    ], 422);
                }

                // Check permissions
                if (!$note->isAccessibleBy($user) || !$span->isAccessibleBy($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to access these spans.'
                    ], 403);
                }

                // Check if connection already exists
                $existing = \App\Models\Connection::where('type_id', 'annotates')
                    ->where('parent_id', $note->id)
                    ->where('child_id', $span->id)
                    ->exists();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This note is already connected to this span.'
                    ], 422);
                }

                try {
                    // Create connection span
                    $connectionSpan = \App\Models\Span::create([
                        'name' => "{$note->name} annotates {$span->name}",
                        'type_id' => 'connection',
                        'owner_id' => $user->id,
                        'updater_id' => $user->id,
                        'access_level' => 'private',
                        'state' => 'complete',
                        'start_year' => $note->start_year,
                        'start_month' => $note->start_month,
                        'start_day' => $note->start_day,
                        'end_year' => $note->end_year,
                        'end_month' => $note->end_month,
                        'end_day' => $note->end_day,
                        'start_precision' => $note->start_precision ?? 'day',
                        'end_precision' => $note->end_precision ?? 'day',
                        'metadata' => ['connection_type' => 'annotates']
                    ]);

                    // Create the connection
                    \App\Models\Connection::create([
                        'parent_id' => $note->id,
                        'child_id' => $span->id,
                        'type_id' => 'annotates',
                        'connection_span_id' => $connectionSpan->id
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Note connected successfully!'
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error creating annotates connection', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage()
                    ], 500);
                }
            });
        });

        // Public routes with span access control
        Route::middleware('span.access')->group(function () {
            // Primary route structure - handle both span show and connections
            Route::get('/', [SpanController::class, 'index'])->name('spans.index');
            
            // Global time travel routes (must come before span-specific routes to avoid conflicts)
            Route::get('/time-travel/exit', [SpanController::class, 'exitTimeTravelGlobal'])
                ->name('time-travel.exit');
            
            // Global time travel toggle route (for header use)
            Route::get('/time-travel/toggle', [SpanController::class, 'toggleTimeTravel'])
                ->name('time-travel.toggle');
            
            // Time travel modal route
            Route::get('/time-travel/modal', [SpanController::class, 'showTimeTravelModal'])
                ->name('time-travel.modal');
            Route::post('/time-travel/modal', [SpanController::class, 'startTimeTravel'])
                ->name('time-travel.start');
            
            // Family route (must come before general span route)
            Route::get('/{span}/family', [FamilyController::class, 'show'])->name('family.show');
            Route::get('/{span}/family/tree', [FamilyController::class, 'tree'])->name('family.tree');
            
            // Specific span routes (must come before general span route to avoid conflicts)
            Route::get('/{span}/story', [SpanController::class, 'story'])->name('spans.story');
            
            // Time travel exit route - clear cookie and return to present
            Route::get('/{span}/at/exit', [SpanController::class, 'exitTimeTravel'])
                ->name('spans.at-date-exit');
            
            // Time travel route - show span at specific date
            Route::get('/{span}/at/{date}', [SpanController::class, 'showAtDate'])
                ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
                ->name('spans.at-date');
            
            // Connection routes (must come before general span route)
            Route::get('/{subject}/connections', [SpanController::class, 'allConnections'])->name('spans.all-connections');
            Route::get('/{subject}/{predicate}', [SpanController::class, 'listConnections'])->name('spans.connections');
            Route::get('/{subject}/{predicate}/{object}', [SpanController::class, 'showConnection'])->name('spans.connection');
            
            // Legacy connection type routes
            Route::get('/{span}/connection_types', [SpanController::class, 'connectionTypes'])->name('spans.connection-types.index');
            Route::get('/{span}/connection_types/{connectionType}', [SpanController::class, 'connectionsByType'])->name('spans.connection-types.show');
            
            // General span show route (must come last as catch-all). Higher PHP timeout for cold-cache/heavy pages.
            Route::get('/{subject}', [SpanController::class, 'show'])->name('spans.show')->middleware(SpanShowTimeoutMiddleware::class);
        });

        // New POST route for creating a new span from YAML
        Route::post('/yaml-create', [\App\Http\Controllers\SpanController::class, 'createFromYaml'])->name('spans.yaml-create');
    });

    // Span version history (using /history/:span to avoid conflicts with connection routes)
    Route::middleware('span.access')->group(function () {
        Route::get('/history/{span}/{version?}', [\App\Http\Controllers\SpanController::class, 'history'])->name('spans.history');
        Route::get('/history/{span}/{version}/details', [\App\Http\Controllers\SpanController::class, 'showVersion'])->name('spans.history.version');
    });

    // Quick Education creation (scoped feature)
    Route::post('/spans/quick-education', [\App\Http\Controllers\SpanController::class, 'quickAddEducation'])
        ->middleware('auth')
        ->name('spans.quick-education.store');

    // Quick Residence creation
    Route::post('/spans/quick-residence', [\App\Http\Controllers\SpanController::class, 'quickAddResidence'])
        ->middleware('auth')
        ->name('spans.quick-residence.store');

    // Photo routes - dedicated routes for photo spans (thing type with photo subtype)
    Route::prefix('photos')->group(function () {
        // Public routes with photo access control
        Route::middleware('span.access')->group(function () {
            // Primary route structure - handle both photo show and connections
            Route::get('/', [PhotoController::class, 'index'])->name('photos.index');
            // Photos from a specific date (YYYY or YYYY-MM or YYYY-MM-DD) – must be before /{photo}
            $datePattern = '[0-9]{4}(-[0-9]{2}){0,2}';
            Route::get('/from/{fromDate}/to/{toDate}', [PhotoController::class, 'indexFromTo'])
                ->where(['fromDate' => $datePattern, 'toDate' => $datePattern])
                ->name('photos.from.to');
            Route::get('/from/{date}', [PhotoController::class, 'indexFrom'])
                ->where('date', $datePattern)
                ->name('photos.from');
            // Photos during a span's date range (span start/end as from/to)
            // Use plain slug strings to allow graceful handling of non-existent spans (show empty results instead of 404)
            Route::get('/during/{slug}', [PhotoController::class, 'indexDuring'])->name('photos.during');
            // Photos featuring a specific span (must be before /{photo} so "of" is not captured as photo slug)
            Route::get('/of/{slug}/from/{fromDate}/to/{toDate}', [PhotoController::class, 'indexOfFromTo'])
                ->where(['fromDate' => $datePattern, 'toDate' => $datePattern])
                ->name('photos.of.from.to');
            Route::get('/of/{slug}/from/{date}', [PhotoController::class, 'indexOfFrom'])
                ->where('date', $datePattern)
                ->name('photos.of.from');
            // Photos featuring a span during another span's date range
            Route::get('/of/{slug}/during/{duringSlug}', [PhotoController::class, 'indexOfDuring'])->name('photos.of.during');
            Route::get('/of/{slug}', [PhotoController::class, 'indexOf'])->name('photos.of');

            Route::get('/{photo}', [PhotoController::class, 'show'])->name('photos.show');
            
            // Specific photo routes
            Route::get('/{photo}/story', [PhotoController::class, 'story'])->name('photos.story');
            
            // Time travel routes for photos
            Route::get('/{photo}/at/{date}', [PhotoController::class, 'showAtDate'])
                ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
                ->name('photos.at-date');
            
            // Connection routes for photos (more specific routes first)
            Route::get('/{photo}/connections', [PhotoController::class, 'allConnections'])->name('photos.all-connections');
            Route::get('/{photo}/{predicate}', [PhotoController::class, 'listConnections'])->name('photos.connections');
            Route::get('/{photo}/{predicate}/{object}', [PhotoController::class, 'showConnection'])->name('photos.connection');
        });

        // Protected routes for photo management
        Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
            Route::get('/{photo}/edit', [PhotoController::class, 'edit'])->name('photos.edit');
            Route::put('/{photo}', [PhotoController::class, 'update'])->name('photos.update');
            Route::delete('/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
            Route::get('/{photo}/compare', [PhotoController::class, 'compare'])->name('photos.compare');
        });
    });

    // Sets routes with access control
    Route::middleware('sets.access')->group(function () {
        Route::get('/sets', [\App\Http\Controllers\SetsController::class, 'index'])->name('sets.index');
        Route::get('/sets/modal-data', [\App\Http\Controllers\SetsController::class, 'getModalData'])->name('sets.modal-data');
        Route::get('/sets/{set}', [\App\Http\Controllers\SetsController::class, 'show'])->name('sets.show');
        Route::get('/api/sets/containing/{item}', [\App\Http\Controllers\SetsController::class, 'getContainingSets'])->name('sets.containing');
        Route::get('/api/sets/{set}/membership/{item}', [\App\Http\Controllers\SetsController::class, 'checkMembership'])->name('sets.membership');
    });

    // Collections routes (public viewing)
    Route::get('/collections', [CollectionsController::class, 'index'])->name('collections.index');
    Route::get('/collections/{collection}', [CollectionsController::class, 'show'])->name('collections.show');
    Route::get('/api/collections/containing/{item}', [CollectionsController::class, 'getContainingCollections'])->name('collections.containing');

    // Notes routes
    Route::get('/notes', [\App\Http\Controllers\NoteController::class, 'index'])->name('notes.index');

    // Protected routes
    Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
        // Connection Management (for regular users) - must be before wildcard routes
        Route::delete('/connections/{connection}', [\App\Http\Controllers\ConnectionController::class, 'destroy'])->name('connections.destroy');
        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('logout', [EmailFirstAuthController::class, 'destroy'])->name('logout');

        // Admin Mode Toggle routes - allows admins to see what normal users see
        Route::prefix('admin-mode')->name('admin-mode.')->group(function () {
            Route::get('/status', [AdminModeController::class, 'getStatus'])->name('status');
            Route::post('/disable', [AdminModeController::class, 'disable'])->name('disable');
            Route::post('/enable', [AdminModeController::class, 'enable'])->name('enable');
            Route::post('/toggle', [AdminModeController::class, 'toggle'])->name('toggle');
        });

        // Family routes
        Route::post('/api/family/connections', [FamilyController::class, 'createConnection'])->name('family.connections.create');

        // Friends routes
        Route::get('/friends', [FriendsController::class, 'index'])->name('friends.index');
        Route::get('/friends/data', [FriendsController::class, 'data'])->name('friends.data');
        Route::post('/api/friends/connections', [FriendsController::class, 'createConnection'])->name('friends.connections.create');

        // Sets routes (authenticated only)
        Route::post('/sets', [\App\Http\Controllers\SetsController::class, 'store'])->name('sets.store');
        Route::post('/sets/{set}/add-item', [\App\Http\Controllers\SetsController::class, 'addItem'])->name('sets.add-item');
        Route::delete('/sets/{set}/remove-item', [\App\Http\Controllers\SetsController::class, 'removeItem'])->name('sets.remove-item');
        // Sets modal routes
        Route::post('/sets/{set}/items', [\App\Http\Controllers\SetsController::class, 'toggleItem'])->name('sets.toggle-item');
        
        // Wikipedia search for authenticated users (for modal use)
        Route::get('/wikipedia/search', [\App\Http\Controllers\WikipediaSearchController::class, 'search'])->name('wikipedia.search');
        
        // AI YAML Generator for authenticated users (for modal use)
        Route::post('/ai-yaml-generator/generate', [\App\Http\Controllers\AiYamlController::class, 'generateYaml'])->name('ai-yaml-generator.generate');
        Route::post('/ai-yaml-generator/improve', [\App\Http\Controllers\AiYamlController::class, 'improveYaml'])->name('ai-yaml-generator.improve');

        // Groups timeline routes (public-facing, not in settings)
        Route::get('/groups', [\App\Http\Controllers\GroupsController::class, 'index'])->name('groups.index');
        Route::get('/groups/{group}', [\App\Http\Controllers\GroupsController::class, 'show'])->name('groups.show');
        
        // Settings routes
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SettingsController::class, 'index'])->name('index');
            Route::get('/import', [\App\Http\Controllers\SettingsController::class, 'import'])->name('import');
            Route::get('/notifications', [\App\Http\Controllers\SettingsController::class, 'notifications'])->name('notifications');
            Route::get('/groups', [\App\Http\Controllers\SettingsController::class, 'groups'])->name('groups');
            Route::get('/spans', [\App\Http\Controllers\SettingsController::class, 'spans'])->name('spans');
            Route::get('/account', [\App\Http\Controllers\SettingsController::class, 'account'])->name('account');
            Route::patch('/account/profile', [\App\Http\Controllers\SettingsController::class, 'updateProfile'])->name('account.profile.update');
            Route::put('/account/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('account.password.update');
            Route::delete('/account', [\App\Http\Controllers\SettingsController::class, 'destroy'])->name('account.destroy');
            
            // Flickr Import routes
            Route::prefix('import/flickr')->name('import.flickr.')->group(function () {
                Route::get('/', [\App\Http\Controllers\FlickrImportController::class, 'index'])->name('index');
                Route::post('/store-credentials', [\App\Http\Controllers\FlickrImportController::class, 'storeCredentials'])->name('store-credentials');
                Route::post('/test-connection', [\App\Http\Controllers\FlickrImportController::class, 'testConnection'])->name('test-connection');
                Route::post('/import-photos', [\App\Http\Controllers\FlickrImportController::class, 'importPhotos'])->name('import-photos');
                Route::get('/get-imported-photos', [\App\Http\Controllers\FlickrImportController::class, 'getImportedPhotos'])->name('get-imported-photos');
                
                // OAuth routes
                Route::get('/authorize', [\App\Http\Controllers\FlickrImportController::class, 'startOAuth'])->name('authorize');
                Route::get('/callback', [\App\Http\Controllers\FlickrImportController::class, 'callback'])->name('callback');
                Route::post('/disconnect', [\App\Http\Controllers\FlickrImportController::class, 'disconnect'])->name('disconnect');
            
            // Photoset routes
            Route::get('/photosets', [\App\Http\Controllers\FlickrImportController::class, 'getPhotosets'])->name('photosets');
            Route::post('/import-photoset', [\App\Http\Controllers\FlickrImportController::class, 'importPhotoset'])->name('import-photoset');
            });
            
            // Photo Upload routes
            Route::prefix('upload/photos')->name('upload.photos.')->group(function () {
                Route::get('/', [\App\Http\Controllers\PhotoUploadController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\PhotoUploadController::class, 'store'])->name('store');
            });
            
            // LinkedIn Import routes
            Route::prefix('import/linkedin')->name('import.linkedin.')->group(function () {
                Route::get('/', [\App\Http\Controllers\LinkedInImportController::class, 'index'])->name('index');
                Route::post('/preview', [\App\Http\Controllers\LinkedInImportController::class, 'preview'])->name('preview');
                Route::post('/import', [\App\Http\Controllers\LinkedInImportController::class, 'import'])->name('import');
            });
            
            // Photo Timeline Import routes
            Route::prefix('import/photo-timeline')->name('import.photo-timeline.')->group(function () {
                Route::get('/', [\App\Http\Controllers\PhotoTimelineImportController::class, 'index'])->name('index');
                Route::post('/preview', [\App\Http\Controllers\PhotoTimelineImportController::class, 'preview'])->name('preview');
                Route::post('/import', [\App\Http\Controllers\PhotoTimelineImportController::class, 'import'])->name('import');
                Route::post('/osm-lookup', [\App\Http\Controllers\PhotoTimelineImportController::class, 'osmLookup'])->name('osm-lookup');
            });
            
            // Twitter Archive Import routes
            Route::prefix('import/twitter')->name('import.twitter.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Settings\ImportController::class, 'showTwitter'])->name('index');
                Route::post('/upload', [\App\Http\Controllers\Settings\ImportController::class, 'uploadTwitter'])->name('upload');
                Route::post('/import-tweet', [\App\Http\Controllers\Settings\ImportController::class, 'importTweet'])->name('import-tweet');
            });
        });

        // Image Proxy routes (must be at root level for public access)
        Route::prefix('images')->name('images.')->group(function () {
            Route::get('/{spanId}/{size?}', [\App\Http\Controllers\ImageProxyController::class, 'proxy'])->name('proxy');
            Route::get('/{spanId}/info', [\App\Http\Controllers\ImageProxyController::class, 'info'])->name('info');
        });

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])
                ->name('dashboard');

            // Workers (queue health & control)
            Route::prefix('workers')->name('workers.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\WorkersController::class, 'index'])->name('index');
                Route::get('/stats', [App\Http\Controllers\Admin\WorkersController::class, 'stats'])->name('stats');
                Route::post('/restart', [App\Http\Controllers\Admin\WorkersController::class, 'restart'])->name('restart');
                Route::post('/retry-all-failed', [App\Http\Controllers\Admin\WorkersController::class, 'retryAllFailed'])->name('retry-all-failed');
                Route::post('/clear-queue', [App\Http\Controllers\Admin\WorkersController::class, 'clearQueue'])->name('clear-queue');
                Route::post('/stop-queue', [App\Http\Controllers\Admin\WorkersController::class, 'stopQueue'])->name('stop-queue');
                Route::post('/start-queue', [App\Http\Controllers\Admin\WorkersController::class, 'startQueue'])->name('start-queue');
                Route::post('/flush-failed', [App\Http\Controllers\Admin\WorkersController::class, 'flushFailed'])->name('flush-failed');
                Route::post('/failed/{uuid}/retry', [App\Http\Controllers\Admin\WorkersController::class, 'retryFailedJob'])->name('retry-failed');
            });

            // Metrics routes
            Route::prefix('metrics')->name('metrics.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\MetricsController::class, 'index'])->name('index');
    Route::get('/low-completeness', [App\Http\Controllers\Admin\MetricsController::class, 'lowCompleteness'])->name('low-completeness');
    Route::get('/residence-gaps', [App\Http\Controllers\Admin\MetricsController::class, 'residenceGaps'])->name('residence-gaps');
    Route::get('/export', [App\Http\Controllers\Admin\MetricsController::class, 'export'])->name('export');
            Route::get('/calculate-all', [App\Http\Controllers\Admin\MetricsController::class, 'calculateAll'])->name('calculate-all');
        Route::get('/calculate-person-spans', [App\Http\Controllers\Admin\MetricsController::class, 'calculatePersonSpans'])->name('calculate-person-spans');
        Route::get('/force-calculate-all', [App\Http\Controllers\Admin\MetricsController::class, 'forceCalculateAll'])->name('force-calculate-all');
    Route::get('/api', [App\Http\Controllers\Admin\MetricsController::class, 'apiIndex'])->name('api.index');
    Route::get('/api/{span}', [App\Http\Controllers\Admin\MetricsController::class, 'apiShow'])->name('api.show');
    Route::get('/{span}', [App\Http\Controllers\Admin\MetricsController::class, 'show'])->name('show');
});

            // AI YAML Generator routes
            Route::get('/ai-yaml-generator', [\App\Http\Controllers\AiYamlController::class, 'show'])->name('ai-yaml-generator.show');
            Route::post('/ai-yaml-generator/generate', [\App\Http\Controllers\AiYamlController::class, 'generateYaml'])->name('ai-yaml-generator.generate');
            Route::post('/ai-yaml-generator/improve', [\App\Http\Controllers\AiYamlController::class, 'improveYaml'])->name('ai-yaml-generator.improve');
            Route::post('/ai-yaml-generator/generate-person', [\App\Http\Controllers\AiYamlController::class, 'generatePersonYaml'])->name('ai-yaml-generator.generate-person');
            Route::post('/ai-yaml-generator/improve-person', [\App\Http\Controllers\AiYamlController::class, 'improvePersonYaml'])->name('ai-yaml-generator.improve-person');
            Route::post('/ai-yaml-generator/generate-organisation', [\App\Http\Controllers\AiYamlController::class, 'generateOrganisationYaml'])->name('ai-yaml-generator.generate-organisation');
            Route::post('/ai-yaml-generator/improve-organisation', [\App\Http\Controllers\AiYamlController::class, 'improveOrganisationYaml'])->name('ai-yaml-generator.improve-organisation');
            Route::get('/ai-yaml-generator/placeholders', [\App\Http\Controllers\AiYamlController::class, 'getPlaceholderSpans'])->name('ai-yaml-generator.placeholders');

            // Collections Management (admin only)
            Route::post('/collections', [CollectionsController::class, 'store'])->name('collections.store');
            Route::post('/collections/{collection}/add-item', [CollectionsController::class, 'addItem'])->name('collections.add-item');
            Route::delete('/collections/{collection}/remove-item', [CollectionsController::class, 'removeItem'])->name('collections.remove-item');
            Route::post('/collections/{collection}/items', [CollectionsController::class, 'toggleItem'])->name('collections.toggle-item');

            // Import Management
            Route::prefix('import')->name('import.')->group(function () {
                // MusicBrainz Import
                Route::prefix('musicbrainz')->name('musicbrainz.')->group(function () {
                    Route::get('/', [MusicBrainzImportController::class, 'index'])->name('index');
                    Route::post('/search', [MusicBrainzImportController::class, 'search'])->name('search');
                    Route::post('/discography', [MusicBrainzImportController::class, 'showDiscography'])->name('show-discography');
                    Route::post('/tracks', [MusicBrainzImportController::class, 'showTracks'])->name('show-tracks');
                    Route::post('/import', [MusicBrainzImportController::class, 'import'])->name('import');
                    Route::post('/import-all', [MusicBrainzImportController::class, 'importAll'])->name('import-all');
                    Route::post('/import-by-url', [MusicBrainzImportController::class, 'importByUrl'])->name('import-by-url');
                    Route::post('/preview-by-url', [MusicBrainzImportController::class, 'previewByUrl'])->name('preview-by-url');
                });

                // Film Import
                Route::prefix('film')->name('film.')->group(function () {
                    Route::get('/', [FilmImportController::class, 'index'])->name('index');
                    Route::post('/search', [FilmImportController::class, 'search'])->name('search');
                    Route::post('/details', [FilmImportController::class, 'getDetails'])->name('details');
                    Route::post('/import', [FilmImportController::class, 'import'])->name('import');
                });

                // Book Import
                Route::prefix('book')->name('book.')->group(function () {
                    Route::get('/', [BookImportController::class, 'index'])->name('index');
                    Route::post('/search', [BookImportController::class, 'search'])->name('search');
                    Route::post('/details', [BookImportController::class, 'getDetails'])->name('details');
                    Route::post('/import', [BookImportController::class, 'import'])->name('import');
                });

                // Desert Island Discs Import (must come before legacy routes to avoid conflicts)
                Route::get('/desert-island-discs', [App\Http\Controllers\Admin\DesertIslandDiscsImportController::class, 'index'])
                    ->name('desert-island-discs.index');
                Route::post('/desert-island-discs/preview', [App\Http\Controllers\Admin\DesertIslandDiscsImportController::class, 'preview'])
                    ->name('desert-island-discs.preview');
                Route::post('/desert-island-discs/dry-run', [App\Http\Controllers\Admin\DesertIslandDiscsImportController::class, 'dryRun'])
                    ->name('desert-island-discs.dry-run');
                Route::post('/desert-island-discs/import', [App\Http\Controllers\Admin\DesertIslandDiscsImportController::class, 'import'])
                    ->name('desert-island-discs.import');
                
                // Desert Island Discs Step-by-Step Import
                Route::get('/desert-island-discs/step-import', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'index'])
                    ->name('desert-island-discs.step-import');
                Route::post('/desert-island-discs/step1', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'step1ParseCsv'])
                    ->name('desert-island-discs.step1');
                Route::post('/desert-island-discs/step2', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'step2ArtistLookup'])
                    ->name('desert-island-discs.step2');
                Route::post('/desert-island-discs/step3', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'step3ImportArtist'])
                    ->name('desert-island-discs.step3');
                Route::post('/desert-island-discs/step4', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'step4ConnectTracks'])
                    ->name('desert-island-discs.step4');
                Route::post('/desert-island-discs/step5', [App\Http\Controllers\Admin\DesertIslandDiscsStepImportController::class, 'step5FinalizeEpisode'])
                    ->name('desert-island-discs.step5');
                
                // Simple Desert Island Discs Import (placeholder-only)
                Route::get('/simple-desert-island-discs', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'index'])
                    ->name('simple-desert-island-discs.index');
                Route::post('/simple-desert-island-discs/upload', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'uploadCsv'])
                    ->name('simple-desert-island-discs.upload');
                Route::get('/simple-desert-island-discs/info', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'getCsvInfo'])
                    ->name('simple-desert-island-discs.info');
                Route::post('/simple-desert-island-discs/preview-chunk', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'previewChunk'])
                    ->name('simple-desert-island-discs.preview-chunk');
                Route::post('/simple-desert-island-discs/dry-run-chunk', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'dryRunChunk'])
                    ->name('simple-desert-island-discs.dry-run-chunk');
                Route::post('/simple-desert-island-discs/import-chunk', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'importChunk'])
                    ->name('simple-desert-island-discs.import-chunk');
                Route::post('/simple-desert-island-discs/log-error', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'logError'])
                    ->name('simple-desert-island-discs.log-error');
                // Legacy routes for backward compatibility
                Route::post('/simple-desert-island-discs/preview', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'preview'])
                    ->name('simple-desert-island-discs.preview');
                Route::post('/simple-desert-island-discs/dry-run', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'dryRun'])
                    ->name('simple-desert-island-discs.dry-run');
                Route::post('/simple-desert-island-discs/import', [App\Http\Controllers\Admin\SimpleDesertIslandDiscsImportController::class, 'import'])
                    ->name('simple-desert-island-discs.import');
                
                // Parliament Explorer (must come before legacy routes to avoid conflicts)
                Route::prefix('parliament')->name('parliament.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\ParliamentExplorerController::class, 'index'])
                        ->name('index');
                    Route::post('/search', [App\Http\Controllers\Admin\ParliamentExplorerController::class, 'search'])
                        ->name('search');
                    Route::post('/get-member', [App\Http\Controllers\Admin\ParliamentExplorerController::class, 'getMember'])
                        ->name('get-member');
                    Route::post('/sparql', [App\Http\Controllers\Admin\ParliamentExplorerController::class, 'runSparqlQuery'])
                        ->name('sparql');
                    Route::post('/import-member', [App\Http\Controllers\Admin\ParliamentExplorerController::class, 'importMember'])
                        ->name('import-member');
                });
                
                // Prime Minister Import
                Route::prefix('prime-ministers')->name('prime-ministers.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'index'])
                        ->name('index');
                    Route::post('/search', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'search'])
                        ->name('search');
                    Route::post('/get-data', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'getPrimeMinisterData'])
                        ->name('get-data');
                    Route::post('/preview', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'preview'])
                        ->name('preview');
                    Route::post('/import', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'import'])
                        ->name('import');
                    Route::get('/recent', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'recentImports'])
                        ->name('recent');
                    Route::post('/clear-cache', [App\Http\Controllers\Admin\PrimeMinisterImportController::class, 'clearCache'])
                        ->name('clear-cache');
                });
                
                // Science Museum Group Import
                Route::prefix('science-museum-group')->name('science-museum-group.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'index'])
                        ->name('index');
                    Route::post('/search', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'search'])
                        ->name('search');
                    Route::post('/get-object-data', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'getObjectData'])
                        ->name('get-object-data');
                    Route::post('/preview', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'preview'])
                        ->name('preview');
                    Route::post('/import', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'import'])
                        ->name('import');
                    Route::post('/clear-cache', [App\Http\Controllers\Admin\ScienceMuseumGroupImportController::class, 'clearCache'])
                        ->name('clear-cache');
                });
                
                // Blue Plaque Import
                Route::prefix('blue-plaques')->name('blue-plaques.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'index'])
                        ->name('index');
                    Route::post('/preview', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'preview'])
                        ->name('preview');
                    Route::post('/search-person', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'searchPerson'])
                        ->name('search-person');
                    Route::post('/validate-single', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'validateSingle'])
                        ->name('validate-single');
                    Route::post('/process-single', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'processSingle'])
                        ->name('process-single');
                    
                    // Frontend batch processing routes
                    Route::post('/process-batch', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'processBatch'])
                        ->name('process-batch');
                    Route::post('/process-all', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'processAll'])
                        ->name('process-all');
                    Route::post('/import-background', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'startBackgroundImport'])
                        ->name('import-background');
                    Route::post('/cancel-background', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'cancelBackgroundImport'])
                        ->name('cancel-background');
                    Route::get('/status', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'status'])
                        ->name('status');
                    Route::get('/stats', [App\Http\Controllers\Admin\BluePlaqueImportController::class, 'stats'])
                        ->name('stats');
                });
                
                // Wikimedia Commons Import
                Route::prefix('wikimedia-commons')->name('wikimedia-commons.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'index'])
                        ->name('index');
                    Route::post('/search', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'search'])
                        ->name('search');
                    Route::post('/search-by-year', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'searchByYear'])
                        ->name('search-by-year');
                    Route::post('/get-image-data', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'getImageData'])
                        ->name('get-image-data');
                });
                
                // Wikipedia Import
                Route::prefix('wikipedia')->name('wikipedia.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Admin\WikipediaImportController::class, 'index'])
                        ->name('index');
                    Route::post('/process-person', [App\Http\Controllers\Admin\WikipediaImportController::class, 'processPerson'])
                        ->name('process-person');
                    Route::post('/skip-person', [App\Http\Controllers\Admin\WikipediaImportController::class, 'skipPerson'])
                        ->name('skip-person');
                    Route::get('/stats', [App\Http\Controllers\Admin\WikipediaImportController::class, 'getStats'])
                        ->name('stats');
                    Route::post('/import-background', [App\Http\Controllers\Admin\WikipediaImportController::class, 'startBackgroundImport'])
                        ->name('import-background');
                    Route::post('/cancel-background', [App\Http\Controllers\Admin\WikipediaImportController::class, 'cancelBackgroundImport'])
                        ->name('cancel-background');
                    Route::get('/background-status', [App\Http\Controllers\Admin\WikipediaImportController::class, 'getBackgroundStatus'])
                        ->name('background-status');
                });
                
                // Additional Wikimedia Commons routes
                Route::post('/wikimedia-commons/preview-import', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'previewImport'])
                    ->name('wikimedia-commons.preview-import');
                Route::post('/wikimedia-commons/import-image', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'importImage'])
                    ->name('wikimedia-commons.import-image');
                Route::post('/wikimedia-commons/clear-cache', [App\Http\Controllers\Admin\WikimediaCommonsImportController::class, 'clearCache'])
                    ->name('wikimedia-commons.clear-cache');
                
                // Legacy YAML Import (must come last to avoid catching other routes)
                Route::get('/', [ImportController::class, 'index'])
                    ->name('index');
                Route::get('/{id}', [ImportController::class, 'show'])
                    ->name('show');
                Route::post('/{id}/import', [ImportController::class, 'import'])
                    ->name('import');
                Route::get('/progress/{importId}', [ImportController::class, 'progress'])
                    ->name('progress');
            });

            // OSM place data import (configurable region; default config is London)
            Route::get('/osmdata', [App\Http\Controllers\Admin\OsmDataController::class, 'index'])
                ->name('osmdata.index');
            Route::post('/osmdata/preview', [App\Http\Controllers\Admin\OsmDataController::class, 'preview'])
                ->name('osmdata.preview');
            Route::post('/osmdata/import', [App\Http\Controllers\Admin\OsmDataController::class, 'import'])
                ->name('osmdata.import');
            Route::post('/osmdata/generate-json', [App\Http\Controllers\Admin\OsmDataController::class, 'generateJson'])
                ->name('osmdata.generate-json');
            Route::post('/osmdata/find-span', [App\Http\Controllers\Admin\OsmDataController::class, 'findSpan'])
                ->name('osmdata.find-span');
            Route::post('/osmdata/search-json', [App\Http\Controllers\Admin\OsmDataController::class, 'searchJsonFeatures'])
                ->name('osmdata.search-json');
            Route::post('/osmdata/update-span-from-json', [App\Http\Controllers\Admin\OsmDataController::class, 'updateSpanFromJson'])
                ->name('osmdata.update-span-from-json');

            // Span Types Management
            Route::resource('span-types', SpanTypeController::class);

            // Connection Types Management
            Route::resource('connection-types', ConnectionTypeController::class);

            // Span Management
            Route::get('/spans', [AdminSpanController::class, 'index'])
                ->name('spans.index');
            
                            // Images Management
                Route::get('/images', [\App\Http\Controllers\Admin\ImagesController::class, 'index'])
                    ->name('images.index');

                Route::post('/images/get-nearest-place', [\App\Http\Controllers\Admin\ImagesController::class, 'getNearestPlace'])
                    ->name('images.get-nearest-place');
            
            // Person Subtype Management (must come before parameterized routes)
            Route::get('/spans/person-subtypes', [AdminSpanController::class, 'managePersonSubtypes'])
                ->name('spans.manage-person-subtypes');
            Route::post('/spans/person-subtypes', [AdminSpanController::class, 'updatePersonSubtypes'])
                ->name('spans.update-person-subtypes');
            
            // Parameterized span routes (must come after specific routes)
            Route::get('/spans/{span}', [AdminSpanController::class, 'show'])
                ->name('spans.show');
            Route::get('/spans/{span}/edit', [AdminSpanController::class, 'edit'])
                ->name('spans.edit');
            Route::put('/spans/{span}', [AdminSpanController::class, 'update'])
                ->name('spans.update');
            Route::delete('/spans/{span}', [AdminSpanController::class, 'destroy'])
                ->name('spans.destroy');
            
            // Public Figure Connection Fixer
            Route::get('/tools/fix-public-figure-connections', [\App\Http\Controllers\Admin\ToolsController::class, 'fixPublicFigureConnections'])
                ->name('tools.fix-public-figure-connections');
            Route::post('/tools/fix-public-figure-connections', [\App\Http\Controllers\Admin\ToolsController::class, 'fixPublicFigureConnectionsAction'])
                ->name('tools.fix-public-figure-connections-action');
            
            // Batch processing for public figure connections
            Route::post('/tools/fix-public-figure-connections/batch/start', [\App\Http\Controllers\Admin\ToolsController::class, 'startBatchFixPublicFigureConnections'])
                ->name('tools.fix-public-figure-connections-batch-start');
            Route::post('/tools/fix-public-figure-connections/batch/process', [\App\Http\Controllers\Admin\ToolsController::class, 'processBatchFixPublicFigureConnections'])
                ->name('tools.fix-public-figure-connections-batch-process');
            Route::get('/tools/fix-public-figure-connections/batch/status', [\App\Http\Controllers\Admin\ToolsController::class, 'getBatchFixPublicFigureConnectionsStatus'])
                ->name('tools.fix-public-figure-connections-batch-status');
            
            // Private Individual Connection Fixer
            Route::get('/tools/fix-private-individual-connections', [\App\Http\Controllers\Admin\ToolsController::class, 'fixPrivateIndividualConnections'])
                ->name('tools.fix-private-individual-connections');
            Route::post('/tools/fix-private-individual-connections', [\App\Http\Controllers\Admin\ToolsController::class, 'fixPrivateIndividualConnectionsAction'])
                ->name('tools.fix-private-individual-connections-action');
            
            // Data Fixer Tool
            Route::get('/tools/fixer', [\App\Http\Controllers\Admin\DataFixerController::class, 'index'])
                ->name('tools.fixer');
            Route::get('/tools/fixer/invalid-date-ranges', [\App\Http\Controllers\Admin\DataFixerController::class, 'findInvalidDateRanges'])
                ->name('tools.fixer.invalid-date-ranges');
            Route::post('/tools/fixer/fix-date-range', [\App\Http\Controllers\Admin\DataFixerController::class, 'fixSpanDateRange'])
                ->name('tools.fixer.fix-date-range');
            Route::get('/tools/fixer/stats', [\App\Http\Controllers\Admin\DataFixerController::class, 'getStats'])
                ->name('tools.fixer.stats');
            Route::get('/tools/fixer/parents-died-before-children', [\App\Http\Controllers\Admin\DataFixerController::class, 'findParentsDiedBeforeChildren'])
                ->name('tools.fixer.parents-died-before-children');
            
            // Span Permissions (Legacy - removed in favor of new group-based system)
                
            // Span Access
            Route::get('/spans/{span}/access', [SpanAccessController::class, 'edit'])
                ->name('spans.access.edit');
            Route::put('/spans/{span}/access', [SpanAccessController::class, 'update'])
                ->name('spans.access.update');
            Route::put('/spans/{span}/visibility', [SpanAccessController::class, 'updateVisibility'])
                ->name('spans.visibility.update');
            
            // Centralized Span Access Management
            Route::get('/span-access', [SpanAccessManagerController::class, 'index'])
                ->name('span-access.index');
            Route::post('/span-access/{spanId}/make-public', [SpanAccessManagerController::class, 'makePublic'])
                ->name('span-access.make-public');
            Route::post('/span-access/{spanId}/make-private', [SpanAccessManagerController::class, 'makePrivate'])
                ->name('span-access.make-private');
            Route::post('/span-access/make-public-bulk', [SpanAccessManagerController::class, 'makePublicBulk'])
                ->name('span-access.make-public-bulk');
            Route::post('/span-access/make-private-bulk', [SpanAccessManagerController::class, 'makePrivateBulk'])
                ->name('span-access.make-private-bulk');
            Route::post('/span-access/share-with-groups-bulk', [SpanAccessManagerController::class, 'shareWithGroupsBulk'])
                ->name('span-access.share-with-groups-bulk');
            
            // Access level update API
            Route::put('/spans/{span}/access-level', function (Request $request, $span) {
                $request->validate([
                    'access_level' => 'required|in:public,private,shared'
                ]);
                
                $span->update(['access_level' => $request->access_level]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Access level updated successfully',
                    'access_level' => $span->access_level
                ]);
            })->name('spans.access-level.update');

            // Group permissions GET/PUT API
            Route::get('/spans/{span}/group-permissions', function (Request $request, $span) {
                $user = auth()->user();
                if (!$user || !$span->isEditableBy($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }

                // Get all groups the user is a member of
                $userGroups = $user->groups()->with('spanPermissions')->get();
                
                $groups = $userGroups->map(function ($group) use ($span) {
                    $hasPermission = $group->spanPermissions()
                        ->where('span_id', $span->id)
                        ->where('permission_type', 'view')
                        ->exists();
                    
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'user_count' => $group->users()->count(),
                        'has_permission' => $hasPermission
                    ];
                });

                return response()->json([
                    'success' => true,
                    'groups' => $groups
                ]);
            })->name('spans.group-permissions.get');

            Route::put('/spans/{span}/group-permissions', function (Request $request, $span) {
                $user = auth()->user();
                if (!$user || !$span->isEditableBy($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }

                $request->validate([
                    'groups' => 'required|array',
                    'groups.*' => 'uuid|exists:groups,id'
                ]);

                // Get the groups the user is a member of
                $userGroupIds = $user->groups()->pluck('id')->toArray();
                
                // Only allow updating permissions for groups the user is a member of
                $validGroupIds = array_intersect($request->groups, $userGroupIds);

                // Remove all existing permissions for this span from user's groups
                \App\Models\SpanPermission::where('span_id', $span->id)
                    ->whereIn('group_id', $userGroupIds)
                    ->delete();

                // Add new permissions for selected groups
                foreach ($validGroupIds as $groupId) {
                    \App\Models\SpanPermission::firstOrCreate([
                        'span_id' => $span->id,
                        'group_id' => $groupId,
                    ], [
                        'permission_type' => 'view'
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Group permissions updated successfully'
                ]);
            })->name('spans.group-permissions.update');

            // Update span notes
            Route::put('/spans/{span}/notes', function (Request $request, \App\Models\Span $span) {
                $user = auth()->user();
                if (!$user || !($span->isEditableBy($user) || $user->is_admin || $span->owner_id === $user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }

                $request->validate([
                    'notes' => 'nullable|string'
                ]);

                $span->update(['notes' => $request->notes]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notes updated successfully',
                    'notes' => $span->notes
                ]);
            })->name('spans.notes.update');


            // Fetch OSM data for a place
            Route::post('/places/{span}/fetch-osm-data', function (Request $request, \App\Models\Span $span) {
                $user = auth()->user();
                if (!$user || !$user->getEffectiveAdminStatus()) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized - admin access required'], 403);
                }
                
                if ($span->type_id !== 'place') {
                    return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
                }
                
                try {
                    $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
                    $boundaryService = app(\App\Services\PlaceBoundaryService::class);
                    
                    // Check if we already have OSM data
                    $hasOsmData = $span->fresh()->getOsmData() !== null;
                    
                    // Fetch OSM data (this will also fetch boundary if applicable)
                    $success = $geocodingWorkflow->resolvePlace($span);
                    
                    if ($success) {
                        $span = $span->fresh();
                        $oldSlug = $request->get('old_slug'); // Get the slug from when the request was made
                        $newSlug = $span->slug;
                        $slugChanged = $oldSlug && $oldSlug !== $newSlug;
                        
                        $osmData = $span->getOsmData();
                        $hasBoundary = false;
                        
                        // Check if boundary was fetched or already exists
                        if ($osmData) {
                            $metadata = $span->metadata ?? [];
                            $hasBoundary = !!(isset($metadata['external_refs']['osm']['boundary_geojson']) || 
                                            isset($metadata['osm_data']['boundary_geojson']));
                            
                            // If we have OSM data but no boundary, and this place should have one, try to fetch it
                            if (!$hasBoundary && $osmData) {
                                $osmType = $osmData['osm_type'] ?? null;
                                $metadata = $span->metadata ?? [];
                                $subtype = $metadata['subtype'] ?? null;
                                $placeType = $osmData['place_type'] ?? '';
                                
                                // Check if this place should have a boundary
                                $shouldHaveBoundary = false;
                                
                                // Relations are most likely to have boundaries
                                if ($osmType === 'relation') {
                                    $shouldHaveBoundary = true;
                                }
                                // Ways can have boundaries for administrative areas
                                elseif ($osmType === 'way' && in_array($subtype, ['country', 'state_region', 'county_province', 'city_district', 'suburb_area'])) {
                                    $shouldHaveBoundary = true;
                                }
                                // Nodes that are administrative areas might have a boundary relation
                                elseif ($osmType === 'node') {
                                    $isAdministrative = $placeType === 'administrative' || in_array($subtype, [
                                        'country', 'state_region', 'county_province', 'city_district', 'suburb_area'
                                    ]);
                                    if ($isAdministrative) {
                                        $shouldHaveBoundary = true;
                                    }
                                }
                                
                                if ($shouldHaveBoundary) {
                                    // Try to fetch boundary (this will also try to find relation for nodes)
                                    $boundary = $boundaryService->getBoundaryGeoJson($span);
                                    $hasBoundary = $boundary !== null;
                                    $span = $span->fresh(); // Refresh to get updated metadata
                                }
                            }
                        }
                        
                        $response = [
                            'success' => true, 
                            'message' => $hasOsmData ? 'Map data updated successfully' : 'OSM data fetched successfully',
                            'has_osm_data' => $span->getOsmData() !== null,
                            'has_coordinates' => $span->getCoordinates() !== null,
                            'has_boundary' => $hasBoundary
                        ];
                        
                        // If slug changed, include redirect information
                        if ($slugChanged) {
                            $response['slug_changed'] = true;
                            $response['new_slug'] = $newSlug;
                            $response['span_id'] = $span->id;
                            // Prefer UUID for redirect to avoid future slug changes
                            $response['redirect_url'] = route('places.show', $span->id);
                        }
                        
                        return response()->json($response);
                    } else {
                        return response()->json([
                            'success' => false, 
                            'message' => 'Could not fetch OSM data for this place'
                        ], 422);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error fetching OSM data', [
                        'span_id' => $span->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'An error occurred while fetching OSM data: ' . $e->getMessage()
                    ], 500);
                }
            })->name('places.fetch-osm-data');

            // Update place from a chosen Nominatim result (re-geocode disambiguation)
            Route::post('/places/{span}/update-from-nominatim', function (Request $request, \App\Models\Span $span) {
                $user = auth()->user();
                if (!$user || !$user->getEffectiveAdminStatus()) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized - admin access required'], 403);
                }
                if ($span->type_id !== 'place') {
                    return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
                }
                $request->validate([
                    'lat' => 'required|numeric',
                    'lng' => 'required|numeric',
                    'osm_type' => 'required|string',
                    'osm_id' => 'required|string',
                    'display_name' => 'required|string',
                    'place_type' => 'nullable|string',
                ]);
                try {
                    $osmService = app(\App\Services\OSMGeocodingService::class);
                    $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
                    $lat = (float) $request->get('lat');
                    $lng = (float) $request->get('lng');
                    $osmType = $request->get('osm_type');
                    $osmId = $request->get('osm_id');
                    $nominatimResult = $osmService->lookupByOsmId($osmType, (int) $osmId, true);
                    if (!$nominatimResult) {
                        $reverseResult = \Illuminate\Support\Facades\Http::withHeaders([
                            'User-Agent' => config('app.user_agent'),
                            'Accept-Language' => 'en'
                        ])->get('https://nominatim.openstreetmap.org/reverse', [
                            'lat' => $lat, 'lon' => $lng, 'format' => 'json',
                            'addressdetails' => 1, 'extratags' => 1, 'namedetails' => 1
                        ]);
                        if ($reverseResult->successful()) {
                            $reverseData = $reverseResult->json();
                            if (($reverseData['osm_type'] ?? '') === $osmType && (string) ($reverseData['osm_id'] ?? '') === (string) $osmId) {
                                $nominatimResult = $reverseData;
                            }
                        }
                    }
                    if (!$nominatimResult) {
                        $nominatimResult = [
                            'place_id' => null, 'osm_type' => $osmType, 'osm_id' => (int) $osmId,
                            'lat' => (string) $lat, 'lon' => (string) $lng,
                            'display_name' => $request->get('display_name'),
                            'type' => $request->get('place_type', ''),
                            'name' => explode(',', $request->get('display_name'))[0] ?? '',
                            'address' => [],
                        ];
                    }
                    $reflection = new \ReflectionClass($osmService);
                    $formatMethod = $reflection->getMethod('formatOsmData');
                    $formatMethod->setAccessible(true);
                    $osmData = $formatMethod->invoke($osmService, $nominatimResult);
                    $success = $geocodingWorkflow->resolveWithMatch($span, $osmData);
                    if ($success) {
                        $span = $span->fresh();
                        return response()->json([
                            'success' => true,
                            'message' => 'Place updated from selected result.',
                            'redirect_url' => route('places.show', $span->id),
                        ]);
                    }
                    return response()->json(['success' => false, 'message' => 'Failed to update span metadata'], 500);
                } catch (\Exception $e) {
                    Log::error('Error updating span from Nominatim', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                    return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
                }
            })->name('places.update-from-nominatim');

            // User Management
            Route::get('/users', [UserController::class, 'index'])
                ->name('users.index');
            
            // Create User from Span (must come before parameterized routes)
            Route::get('/users/create-from-span', [UserController::class, 'createFromSpan'])
                ->name('users.create-from-span');
            Route::post('/users/create-from-span', [UserController::class, 'storeFromSpan'])
                ->name('users.store-from-span');
            
            Route::post('/users/generate-invitation-codes', [UserController::class, 'generateInvitationCodes'])
                ->name('users.generate-invitation-codes');
            Route::delete('/users/invitation-codes', [UserController::class, 'deleteAllInvitationCodes'])
                ->name('users.delete-all-invitation-codes');
            
            // Parameterized user routes (must come after specific routes)
            Route::get('/users/{user}', [UserController::class, 'show'])
                ->name('users.show');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                ->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])
                ->name('users.update');
            Route::post('/users/{user}/approve', [UserController::class, 'approve'])
                ->name('users.approve');
            Route::post('/users/{user}/unapprove', [UserController::class, 'unapprove'])
                ->name('users.unapprove');
            Route::post('/users/{user}/verify', [UserController::class, 'verify'])
                ->name('users.verify');
            Route::post('/users/{user}/unverify', [UserController::class, 'unverify'])
                ->name('users.unverify');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])
                ->name('users.destroy');

            // Group Management
            Route::resource('groups', \App\Http\Controllers\Admin\GroupController::class);
            Route::post('/groups/{group}/members', [\App\Http\Controllers\Admin\GroupController::class, 'addMember'])
                ->name('groups.add-member');
            Route::delete('/groups/{group}/members/{user}', [\App\Http\Controllers\Admin\GroupController::class, 'removeMember'])
                ->name('groups.remove-member');

            // Span Permissions Management
            Route::get('/spans/{span}/permissions', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'show'])
                ->name('spans.permissions.show');
            Route::post('/spans/{span}/permissions/user', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'grantUserPermission'])
                ->name('spans.permissions.grant-user');
            Route::post('/spans/{span}/permissions/group', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'grantGroupPermission'])
                ->name('spans.permissions.grant-group');
            Route::delete('/spans/{span}/permissions/user/{user}/{permissionType}', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'revokeUserPermission'])
                ->name('spans.permissions.revoke-user');
            Route::delete('/spans/{span}/permissions/group/{group}/{permissionType}', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'revokeGroupPermission'])
                ->name('spans.permissions.revoke-group');
            Route::put('/spans/{span}/permissions/bulk', [\App\Http\Controllers\Admin\SpanPermissionController::class, 'bulkUpdate'])
                ->name('spans.permissions.bulk-update');

            // Admin Connection Management (with different prefix to avoid conflicts)
            Route::prefix('admin-connections')->name('connections.')->group(function () {
                Route::get('/', [ConnectionController::class, 'index'])
                    ->name('index');
                Route::post('/', [ConnectionController::class, 'store'])
                    ->name('store');
                Route::get('/{connection}', [ConnectionController::class, 'show'])
                    ->name('show');
                Route::get('/{connection}/edit', [ConnectionController::class, 'edit'])
                    ->name('edit');
                Route::put('/{connection}', [ConnectionController::class, 'update'])
                    ->name('update');
                Route::delete('/{connection}', [ConnectionController::class, 'destroy'])
                    ->name('destroy');
            });

            // Development routes (only in local environment)
            if (app()->environment('local')) {
                Route::get('/dev/components', [App\Http\Controllers\Dev\ComponentShowcaseController::class, 'index'])
                    ->name('dev.components');
            }

            // Visualizers
            Route::get('/visualizer', [VisualizerController::class, 'index'])
                ->name('visualizer.index');
            Route::get('/visualizer/temporal', [VisualizerController::class, 'temporal'])
                ->name('visualizer.temporal');

            // Admin Tools
            Route::get('/tools', [App\Http\Controllers\Admin\ToolsController::class, 'index'])
                ->name('tools.index');
            
            // Span Merge Tool
            Route::prefix('merge')->name('merge.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\MergeController::class, 'index'])
                    ->name('index');
                Route::get('/find-similar-spans', [App\Http\Controllers\Admin\MergeController::class, 'findSimilarSpans'])
                    ->name('find-similar-spans');
                Route::post('/merge-spans', [App\Http\Controllers\Admin\MergeController::class, 'mergeSpans'])
                    ->name('merge-spans');
                Route::post('/bulk-delete-zero-connection-duplicates', [App\Http\Controllers\Admin\MergeController::class, 'bulkDeleteZeroConnectionDuplicates'])
                    ->name('bulk-delete-zero-connection-duplicates');
                Route::get('/bulk-delete-zero-connection-progress', [App\Http\Controllers\Admin\MergeController::class, 'bulkDeleteZeroConnectionProgress'])
                    ->name('bulk-delete-zero-connection-progress');
                Route::get('/span-details', [App\Http\Controllers\Admin\MergeController::class, 'getSpanDetails'])
                    ->name('span-details');
            });
            
            // Family Connection Date Sync Tool
            Route::get('/tools/family-connection-date-sync', [App\Http\Controllers\Admin\ToolsController::class, 'familyConnectionDateSync'])
                ->name('tools.family-connection-date-sync');
            Route::post('/tools/family-connection-date-sync', [App\Http\Controllers\Admin\ToolsController::class, 'familyConnectionDateSyncAction'])
                ->name('tools.family-connection-date-sync-action');

            // Fix Connection Slugs Tool
            Route::get('/tools/fix-connection-slugs', [App\Http\Controllers\Admin\ToolsController::class, 'fixConnectionSlugs'])
                ->name('tools.fix-connection-slugs');
            Route::post('/tools/fix-connection-slugs', [App\Http\Controllers\Admin\ToolsController::class, 'fixConnectionSlugsAction'])
                ->name('tools.fix-connection-slugs-action');

            Route::get('/tools/find-similar-spans', [App\Http\Controllers\Admin\ToolsController::class, 'findSimilarSpans'])
                ->name('tools.find-similar-spans');
            Route::post('/tools/merge-spans', [App\Http\Controllers\Admin\ToolsController::class, 'mergeSpans'])
                ->name('tools.merge-spans');
            Route::get('/tools/span-details', [App\Http\Controllers\Admin\ToolsController::class, 'getSpanDetails'])
                ->name('tools.span-details');
            Route::post('/tools/create-desert-island-discs', [App\Http\Controllers\Admin\ToolsController::class, 'createDesertIslandDiscs'])
                ->name('tools.create-desert-island-discs');
            Route::get('/tools/make-things-public', [App\Http\Controllers\Admin\ToolsController::class, 'showMakeThingsPublic'])
                ->name('tools.make-things-public');
            Route::post('/tools/execute-make-things-public', [App\Http\Controllers\Admin\ToolsController::class, 'executeMakeThingsPublic'])
                ->name('tools.execute-make-things-public');
            // New route for prewarming Wikipedia cache
            Route::match(['GET', 'POST'], '/tools/prewarm-wikipedia-cache', [App\Http\Controllers\Admin\ToolsController::class, 'prewarmWikipediaCache'])
                ->name('tools.prewarm-wikipedia-cache');
            
            // Person Subtype Management
            Route::get('/tools/manage-person-subtypes', [App\Http\Controllers\Admin\ToolsController::class, 'managePersonSubtypes'])
                ->name('tools.manage-person-subtypes');

            // Place Management
            Route::prefix('places')->name('places.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\PlaceController::class, 'index'])
                    ->name('index');
                Route::get('/hierarchy', [\App\Http\Controllers\Admin\PlaceController::class, 'hierarchy'])
                    ->name('hierarchy');
                Route::get('/placeholders', [\App\Http\Controllers\Admin\PlaceController::class, 'placeholders'])
                    ->name('placeholders');
                Route::get('/needs-geocoding', [\App\Http\Controllers\Admin\PlaceController::class, 'needsGeocoding'])
                    ->name('needs-geocoding');
                Route::get('/{span}/disambiguate', [\App\Http\Controllers\Admin\PlaceController::class, 'disambiguate'])
                    ->name('disambiguate');
                Route::post('/{span}/resolve', [\App\Http\Controllers\Admin\PlaceController::class, 'resolve'])
                    ->name('resolve');
                Route::post('/batch-geocode', [\App\Http\Controllers\Admin\PlaceController::class, 'batchGeocode'])
                    ->name('batch-geocode');
                Route::post('/{span}/import', [\App\Http\Controllers\Admin\PlaceController::class, 'import'])
                    ->name('import');
                Route::post('/{span}/auto-geocode', [\App\Http\Controllers\Admin\PlaceController::class, 'autoGeocode'])
                    ->name('auto-geocode');
                Route::get('/{span}/search-matches', [\App\Http\Controllers\Admin\PlaceController::class, 'searchMatches'])
                    ->name('search-matches');
                Route::get('/stats', [\App\Http\Controllers\Admin\PlaceController::class, 'stats'])
                    ->name('stats');
                Route::post('/clear-import-log', [\App\Http\Controllers\Admin\PlaceController::class, 'clearImportLog'])
                    ->name('clear-import-log');
            });
            Route::post('/tools/manage-person-subtypes', [App\Http\Controllers\Admin\ToolsController::class, 'updatePersonSubtypes'])
                ->name('tools.update-person-subtypes');
            Route::post('/tools/manage-person-subtypes/ajax', [App\Http\Controllers\Admin\ToolsController::class, 'updatePersonSubtypesAjax'])
                ->name('tools.update-person-subtypes-ajax');

            // Data Export
            Route::prefix('data-export')->name('data-export.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\DataExportController::class, 'index'])
                    ->name('index');
                Route::get('/export-all', [App\Http\Controllers\Admin\DataExportController::class, 'exportAll'])
                    ->name('export-all');
                Route::post('/export-selected', [App\Http\Controllers\Admin\DataExportController::class, 'exportSelected'])
                    ->name('export-selected');
                Route::get('/stats', [App\Http\Controllers\Admin\DataExportController::class, 'getStats'])
                    ->name('get-stats');
            });

            // Data Import
            Route::prefix('data-import')->name('data-import.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\DataImportController::class, 'index'])
                    ->name('index');
                Route::post('/import', [App\Http\Controllers\Admin\DataImportController::class, 'import'])
                    ->name('import');
                Route::post('/preview', [App\Http\Controllers\Admin\DataImportController::class, 'preview'])
                    ->name('preview');
            });

            // System History
            Route::prefix('system-history')->name('system-history.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\SystemHistoryController::class, 'index'])
                    ->name('index');
                Route::get('/stats', [App\Http\Controllers\Admin\SystemHistoryController::class, 'stats'])
                    ->name('stats');
            });

            // Slack Notifications
            Route::prefix('slack-notifications')->name('slack-notifications.')->group(function () {
                Route::get('/', [App\Http\Controllers\Admin\SlackNotificationController::class, 'index'])
                    ->name('index');
                Route::post('/test', [App\Http\Controllers\Admin\SlackNotificationController::class, 'test'])
                    ->name('test');
                Route::get('/status', [App\Http\Controllers\Admin\SlackNotificationController::class, 'status'])
                    ->name('status');
            });


        });
        
        // User Switcher - moved outside admin middleware but still under auth
        Route::middleware(['user.switcher'])->prefix('admin')->name('admin.')->group(function () {
            Route::get('user-switcher/users', [App\Http\Controllers\Admin\UserSwitcherController::class, 'getUserList'])
                ->name('user-switcher.users');
            Route::post('user-switcher/switch/{userId}', [App\Http\Controllers\Admin\UserSwitcherController::class, 'switchToUser'])
                ->name('user-switcher.switch');
            Route::post('user-switcher/switch-back', [App\Http\Controllers\Admin\UserSwitcherController::class, 'switchBack'])
                ->name('user-switcher.switch-back');
        });

        // User's groups and note creation
        Route::get('/user/groups', function (Request $request) {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated'
                ], 401);
            }
            
            $groups = $user->groups()->get(['id', 'name']);
            
            return response()->json([
                'success' => true,
                'groups' => $groups
            ]);
        });

        Route::post('/notes/create', function (Request $request) {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in to create notes'
                ], 401);
            }

            $request->validate([
                'span_id' => 'required|uuid|exists:spans,id',
                'description' => 'required|string',
                'tags' => 'nullable|string',
                'state' => 'required|in:draft,complete',
                'access_level' => 'required|in:private,shared',
                'groups' => 'nullable|array',
                'groups.*' => 'uuid|exists:groups,id',
                'note_date' => 'required|date',
                'note_date_end' => 'nullable|date'
            ]);

            try {
                $today = now();
                
                // Parse dates from request
                $noteDate = \Carbon\Carbon::parse($request->note_date);
                $noteDateEnd = $request->note_date_end ? \Carbon\Carbon::parse($request->note_date_end) : $noteDate;
                
                // Get target span for naming
                $targetSpan = \App\Models\Span::find($request->span_id);
                $shortUuid = substr(\Illuminate\Support\Str::uuid(), 0, 8);
                
                // Create the note span with new naming convention
                $note = new \App\Models\Span([
                    'name' => $user->personalSpan->name . ' note ' . $shortUuid,
                    'type_id' => 'note',
                    'description' => $request->description,
                    'notes' => $request->tags,
                    'state' => $request->state,
                    'start_year' => $noteDate->year,
                    'start_month' => $noteDate->month,
                    'start_day' => $noteDate->day,
                    'end_year' => $noteDateEnd->year,
                    'end_month' => $noteDateEnd->month,
                    'end_day' => $noteDateEnd->day,
                    'start_precision' => 'day',
                    'end_precision' => 'day',
                    'access_level' => $request->access_level,
                    'owner_id' => $user->id,
                    'updater_id' => $user->id
                ]);
                $note->save();
                
                // If shared, add group permissions
                if ($request->access_level === 'shared' && $request->groups) {
                    foreach ($request->groups as $groupId) {
                        // Get the group and verify user is a member
                        $group = \App\Models\Group::find($groupId);
                        if ($group && $group->hasMember($user)) {
                            \App\Models\SpanPermission::create([
                                'span_id' => $note->id,
                                'group_id' => $groupId,
                                'permission_type' => 'view'
                            ]);
                        }
                    }
                }

                // Create "created" connection: user created note
                $createdConnectionSpan = new \App\Models\Span([
                    'name' => $user->personalSpan->name . ' created ' . $note->name,
                    'type_id' => 'connection',
                    'state' => 'complete',
                    'access_level' => 'private',
                    'start_year' => $noteDate->year,
                    'start_month' => $noteDate->month,
                    'start_day' => $noteDate->day,
                    'end_year' => $noteDateEnd->year,
                    'end_month' => $noteDateEnd->month,
                    'end_day' => $noteDateEnd->day,
                    'start_precision' => 'day',
                    'end_precision' => 'day',
                    'metadata' => [
                        'connection_type' => 'created',
                        'source' => 'note_modal'
                    ],
                    'owner_id' => $user->id,
                    'updater_id' => $user->id
                ]);
                $createdConnectionSpan->save();

                $createdConnection = new \App\Models\Connection([
                    'parent_id' => $user->personalSpan->id,
                    'child_id' => $note->id,
                    'type_id' => 'created',
                    'connection_span_id' => $createdConnectionSpan->id
                ]);
                $createdConnection->save();

                // Create "annotates" connection: note annotates span
                $annotatesSpan = \App\Models\Span::find($request->span_id);
                if (!$annotatesSpan || !$annotatesSpan->isAccessibleBy($user)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot access the span to annotate'
                    ], 403);
                }

                $annotatesConnectionSpan = new \App\Models\Span([
                    'name' => $note->name . ' annotates ' . $annotatesSpan->name,
                    'type_id' => 'connection',
                    'state' => 'complete',
                    'access_level' => 'private',
                    'start_year' => $noteDate->year,
                    'start_month' => $noteDate->month,
                    'start_day' => $noteDate->day,
                    'end_year' => $noteDateEnd->year,
                    'end_month' => $noteDateEnd->month,
                    'end_day' => $noteDateEnd->day,
                    'start_precision' => 'day',
                    'end_precision' => 'day',
                    'metadata' => [
                        'connection_type' => 'annotates',
                        'source' => 'note_modal'
                    ],
                    'owner_id' => $user->id,
                    'updater_id' => $user->id
                ]);
                $annotatesConnectionSpan->save();

                $annotatesConnection = new \App\Models\Connection([
                    'parent_id' => $note->id,
                    'child_id' => $annotatesSpan->id,
                    'type_id' => 'annotates',
                    'connection_span_id' => $annotatesConnectionSpan->id
                ]);
                $annotatesConnection->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Note created successfully',
                    'note_id' => $note->id
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Error creating note', [
                    'user_id' => $user->id,
                    'span_id' => $request->span_id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create note: ' . $e->getMessage()
                ], 500);
            }
        })->name('notes.create');

        // User Switcher - moved outside admin middleware but still under auth
    });

    // Auth routes - Email First Flow
    Route::middleware('guest')->group(function () {
        Route::get('signin', [EmailFirstAuthController::class, 'showEmailForm'])
            ->name('login');
        Route::get('login', function() {
            return redirect()->route('login');
        });
        Route::get('auth/email', function() {
            return redirect()->route('login');
        });
        Route::post('auth/email', [EmailFirstAuthController::class, 'processEmail'])
            ->name('auth.email');
        Route::get('auth/password', [EmailFirstAuthController::class, 'showPasswordForm'])
            ->name('auth.password');
        Route::post('auth/password', [EmailFirstAuthController::class, 'login'])
            ->name('auth.password.submit');
        Route::get('auth/clear-remembered-email', [EmailFirstAuthController::class, 'clearRememberedEmail'])
            ->name('auth.clear-remembered-email');
        Route::post('auth/verification/resend', [EmailFirstAuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        // Registration routes
        Route::get('register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])
            ->name('register');
        Route::post('register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
            ->middleware('throttle:registration')
            ->name('register.store');
        Route::get('register/pending', [App\Http\Controllers\Auth\RegisteredUserController::class, 'pending'])
            ->name('register.pending');

        // Password reset routes
        Route::get('forgot-password', [App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])
            ->name('password.request');
        Route::post('forgot-password', [App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
            ->name('password.email');
        Route::get('reset-password/{token}', [App\Http\Controllers\Auth\NewPasswordController::class, 'create'])
            ->name('password.reset');
        Route::post('reset-password', [App\Http\Controllers\Auth\NewPasswordController::class, 'store'])
            ->name('password.store');
    });

    // Session Bridge endpoints - for handling redeploy session recovery
    Route::post('api/session-bridge/restore', [SessionBridgeController::class, 'restoreSession'])
        ->name('session-bridge.restore');
    Route::post('api/session-bridge/check', [SessionBridgeController::class, 'checkSession'])
        ->name('session-bridge.check');
    Route::post('api/session-bridge/refresh', [SessionBridgeController::class, 'refreshBridgeToken'])
        ->middleware('auth')
        ->name('session-bridge.refresh');

    // Email verification routes
    // Verification link works without being logged in - we authenticate via the signed URL
    Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
        try {
            // Get the user from the signed URL
            $user = User::find($id);
            
            if (!$user) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Invalid or expired verification link. Please request a new verification email.']);
            }
            
            // Verify the hash matches (Laravel's verification hash)
            // The hash is sha1 of the user's email
            $expectedHash = sha1($user->getEmailForVerification());
            if (!hash_equals((string) $hash, $expectedHash)) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Invalid or expired verification link. Please request a new verification email.']);
            }
        } catch (\Exception $e) {
            Log::error('Email verification error', [
                'error' => $e->getMessage(),
                'id' => $id,
                'hash' => $hash
            ]);
            
            return redirect()->route('login')
                ->withErrors(['email' => 'Invalid or expired verification link. Please request a new verification email.']);
        }
        
        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            // Already verified - check if we should log them in
            if ($user->approved_at) {
                // Log them in automatically
                Auth::login($user);
                return redirect()->intended(RouteServiceProvider::HOME)
                    ->with('status', 'Your email is already verified! You are now logged in.');
            } else {
                return redirect()->route('login')
                    ->with('status', 'Your email is already verified! Your account is pending admin approval.');
            }
        }
        
        // Verify the email
        $user->markEmailAsVerified();
        event(new \Illuminate\Auth\Events\Verified($user));
        
        // Check if user is also approved - if so, log them in automatically
        if ($user->approved_at) {
            // Log them in automatically
            Auth::login($user);
            return redirect()->intended(RouteServiceProvider::HOME)
                ->with('status', 'Your email has been verified! You are now logged in.');
        }
        
        // Email verified but not approved yet
        return redirect()->route('login')
            ->with('status', 'Your email has been verified! Your account is now pending admin approval. You will receive an email once approved.');
    })->middleware('signed')->name('verification.verify');
    
    // Profile completion routes (must be authenticated and verified, but before profile.complete middleware)
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('profile/complete', [App\Http\Controllers\Auth\CompleteProfileController::class, 'show'])
            ->name('profile.complete');
        Route::post('profile/complete', [App\Http\Controllers\Auth\CompleteProfileController::class, 'store'])
            ->name('profile.complete.store');
    });

    // Routes that require authentication (email verification routes don't need profile completion)
    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', function () {
            return view('auth.verify-email');
        })->name('verification.notice');

        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('message', 'Verification link sent!');
        })->middleware('throttle:6,1')->name('verification.send');
    });

    // Timeline Viewer (requires profile completion)
    Route::middleware(['auth', 'verified', 'profile.complete'])->group(function () {
        Route::get('/viewer', [App\Http\Controllers\TimelineViewerController::class, 'index'])->name('viewer.index');
        Route::get('/viewer/spans', [App\Http\Controllers\TimelineViewerController::class, 'getSpansInViewport'])->name('viewer.spans');
    });

    // Family routes
});

// Remove the test-log route if not needed for production

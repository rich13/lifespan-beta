<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\Auth\EmailFirstAuthController;
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
use App\Http\Controllers\FriendsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Core routes for the Lifespan application. We start with just the basics
| and will add more sophisticated routing as we build out the system.
|
*/

// Health check endpoint for Render
Route::get('/health', function () {
    try {
        // Basic app check
        if (!app()->isDownForMaintenance()) {
            // Database connection check with more detailed info
            $dbInfo = [];
            
            try {
                $pdo = DB::connection()->getPdo();
                $dbConfig = config('database.connections.' . config('database.default'));
                
                // Get sanitized config (don't show password)
                $dbInfo = [
                    'connection' => config('database.default'),
                    'host' => $dbConfig['host'] ?? 'unknown',
                    'port' => $dbConfig['port'] ?? 'unknown',
                    'database' => $dbConfig['database'] ?? 'unknown',
                    'username' => $dbConfig['username'] ?? 'unknown',
                    'status' => 'connected'
                ];
                
                // Test a simple query
                $tables = DB::select("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'public'");
                $dbInfo['tables_count'] = $tables[0]->count ?? 0;
                
                // Try to get the user count as a more meaningful test
                try {
                    $userCount = DB::table('users')->count();
                    $dbInfo['users_count'] = $userCount;
                    $dbInfo['query_test'] = 'passed';
                } catch (\Exception $e) {
                    $dbInfo['query_test'] = 'failed';
                    $dbInfo['query_error'] = $e->getMessage();
                }
                
            } catch (\Exception $e) {
                $dbInfo = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'driver' => config('database.default')
                ];
                
                // Get the database config we're trying to use
                try {
                    $dbConfig = config('database.connections.' . config('database.default'));
                    $dbInfo['connection_info'] = [
                        'driver' => config('database.default'),
                        'host' => $dbConfig['host'] ?? 'unknown',
                        'port' => $dbConfig['port'] ?? 'unknown',
                        'database' => $dbConfig['database'] ?? 'unknown',
                        'username' => $dbConfig['username'] ?? 'unknown',
                    ];
                } catch (\Exception $ex) {
                    $dbInfo['config_error'] = $ex->getMessage();
                }
                
                // Return 500 for database errors
                return response()->json([
                    'status' => 'unhealthy',
                    'reason' => 'database_connection_failed',
                    'database' => $dbInfo,
                    'timestamp' => now()->toIso8601String(),
                    'environment' => app()->environment()
                ], 500);
            }
            
            // Log successful health check
            Log::info('Health check successful', [
                'environment' => app()->environment(),
                'database' => $dbInfo,
                'timestamp' => now()->toIso8601String()
            ]);
            
            return response()->json([
                'status' => 'healthy',
                'database' => $dbInfo,
                'timestamp' => now()->toIso8601String(),
                'environment' => app()->environment()
            ]);
        }
        
        return response()->json([
            'status' => 'maintenance',
            'timestamp' => now()->toIso8601String()
        ], 503);
        
    } catch (\Exception $e) {
        Log::error('Health check failed', [
            'error' => $e->getMessage(),
            'error_class' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'timestamp' => now()->toIso8601String()
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
    // Public routes
    Route::get('/', function () {
        return view('home');
    })->name('home');

    // Desert Island Discs route
    Route::get('/desert-island-discs', [SpanController::class, 'desertIslandDiscs'])->name('desert-island-discs.index');

    // Date exploration route - supports YYYY, YYYY-MM, and YYYY-MM-DD formats
    Route::get('/date/{date}', [SpanController::class, 'exploreDate'])
        ->where('date', '[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?')
        ->name('date.explore');

    // Span routes
    Route::prefix('spans')->group(function () {
        // Search route (works with session auth)
        Route::get('/search', [SpanController::class, 'search'])->name('spans.search');
        
        // Timeline routes moved to /api/spans/{span} for better separation of HTML and JSON endpoints
        
        // Types route (public)
            Route::get('/types', [SpanController::class, 'types'])->name('spans.types');
    Route::get('/types/{type}', [SpanController::class, 'showType'])->name('spans.types.show');
    Route::get('/types/{type}/subtypes', [SpanController::class, 'showSubtypes'])->name('spans.types.subtypes');
    Route::get('/types/{type}/subtypes/{subtype}', [SpanController::class, 'showTypeSubtype'])->name('spans.types.subtypes.show');
        
        // Protected routes
        Route::middleware('auth')->group(function () {
            Route::get('/shared-with-me', [SpanController::class, 'sharedWithMe'])->name('spans.shared-with-me');
            Route::get('/create', [SpanController::class, 'create'])->name('spans.create');
            Route::post('/', [SpanController::class, 'store'])->name('spans.store');
            Route::get('/{span}/edit', [SpanController::class, 'edit'])->name('spans.edit');
            Route::get('/{span}/yaml', [SpanController::class, 'getYaml'])->name('spans.yaml');
            Route::get('/{span}/editor', [SpanController::class, 'yamlEditor'])->name('spans.yaml-editor');
            Route::post('/{span}/editor/validate', [SpanController::class, 'validateYaml'])->name('spans.yaml-validate');
            Route::post('/{span}/editor/apply', [SpanController::class, 'applyYaml'])->name('spans.yaml-apply');
            Route::post('/{span}/improve/preview', [SpanController::class, 'previewImprovement'])->name('spans.improve.preview');
            Route::post('/{span}/improve', [SpanController::class, 'improveWithAi'])->name('spans.improve');
            Route::put('/{span}', [SpanController::class, 'update'])->name('spans.update');
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
                    'child_id' => 'required|uuid|exists:spans,id',
                    'type_id' => 'required|string|exists:connection_types,type',
                    'age' => 'nullable|integer|min:0',
                    'start_year' => 'nullable|integer|min:1000|max:2100',
                    'start_month' => 'nullable|integer|min:1|max:12',
                    'start_day' => 'nullable|integer|min:1|max:31'
                ]);
                
                // Get the parent span to calculate the connection date
                $parentSpan = \App\Models\Span::find($validated['parent_id']);
                $connectionYear = null;
                
                // Use provided date if available, otherwise calculate from age
                if (isset($validated['start_year'])) {
                    $connectionYear = $validated['start_year'];
                } elseif ($parentSpan && $parentSpan->start_year && isset($validated['age'])) {
                    $connectionYear = $parentSpan->start_year + $validated['age'];
                }
                
                // Create a connection span with temporal information
                $connectionSpanData = [
                    'name' => "Connection between spans",
                    'type_id' => 'connection',
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'private',
                    'state' => $validated['is_placeholder'] ? 'placeholder' : 'normal'
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
                
                // Create the connection
                $connection = \App\Models\Connection::create([
                    'parent_id' => $validated['subject_id'],
                    'child_id' => $validated['object_id'],
                    'type_id' => $validated['predicate'],
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
                    ->where('name', 'like', "%{$query}%")
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
                
                return response()->json($spans);
            });

            // New connection creation endpoint
            Route::post('/api/connections/create', function (Request $request) {
                $validated = $request->validate([
                    'subject_id' => 'required|uuid|exists:spans,id',
                    'object_id' => 'required|uuid|exists:spans,id',
                    'predicate' => 'required|string|exists:connection_types,type',
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

                    // Check if user can access both spans
                    if (!$subject->isAccessibleBy(auth()->user()) || !$object->isAccessibleBy(auth()->user())) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You do not have permission to create connections between these spans.'
                        ], 403);
                    }

                    // Validate span types
                    if (!$connectionType->isSpanTypeAllowed($subject->type_id, 'parent')) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid subject span type. Expected one of: " . 
                                        implode(', ', $connectionType->getAllowedSpanTypes('parent'))
                        ], 422);
                    }

                    if (!$connectionType->isSpanTypeAllowed($object->type_id, 'child')) {
                        return response()->json([
                            'success' => false,
                            'message' => "Invalid object span type. Expected one of: " . 
                                        implode(', ', $connectionType->getAllowedSpanTypes('child'))
                        ], 422);
                    }

                    // Check for existing connection
                    $existingConnection = \App\Models\Connection::where(function($query) use ($subject, $object) {
                        $query->where('parent_id', $subject->id)
                              ->where('child_id', $object->id);
                    })->orWhere(function($query) use ($subject, $object) {
                        $query->where('parent_id', $object->id)
                              ->where('child_id', $subject->id);
                    })->where('type_id', $validated['predicate'])
                    ->first();

                    if ($existingConnection) {
                        return response()->json([
                            'success' => false,
                            'message' => 'A connection of this type already exists between these spans'
                        ], 422);
                    }

                    // Create connection span
                    $connectionSpanData = [
                        'name' => "{$subject->name} - {$object->name} {$connectionType->forward_predicate}",
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

                    // Create the connection
                    $connection = \App\Models\Connection::create([
                        'parent_id' => $subject->id,
                        'child_id' => $object->id,
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
        });

        // Public routes with span access control
        Route::middleware('span.access')->group(function () {
            // Primary route structure - handle both span show and connections
            Route::get('/', [SpanController::class, 'index'])->name('spans.index');
            
            // Family route (must come before general span route)
            Route::get('/{span}/family', [FamilyController::class, 'show'])->name('family.show');
            
            Route::get('/{subject}', [SpanController::class, 'show'])->name('spans.show');
            
            // Specific span routes (must come before connection routes to avoid conflicts)
            Route::get('/{span}/story', [SpanController::class, 'story'])->name('spans.story');
            
            // Connection routes
            Route::get('/{subject}/{predicate}', [SpanController::class, 'listConnections'])->name('spans.connections');
            Route::get('/{subject}/{predicate}/{object}', [SpanController::class, 'showConnection'])->name('spans.connection');
            
            // Legacy connection type routes
            Route::get('/{span}/connection_types', [SpanController::class, 'connectionTypes'])->name('spans.connection-types.index');
            Route::get('/{span}/connection_types/{connectionType}', [SpanController::class, 'connectionsByType'])->name('spans.connection-types.show');
        });

        // New POST route for creating a new span from YAML
        Route::post('/yaml-create', [\App\Http\Controllers\SpanController::class, 'createFromYaml'])->name('spans.yaml-create');
    });

    // Span version history (using /history/:span to avoid conflicts with connection routes)
    Route::middleware('span.access')->group(function () {
        Route::get('/history/{span}', [\App\Http\Controllers\SpanController::class, 'history'])->name('spans.history');
        Route::get('/history/{span}/{version}', [\App\Http\Controllers\SpanController::class, 'showVersion'])->name('spans.history.version');
    });

    // Sets routes with access control
    Route::middleware('sets.access')->group(function () {
        Route::get('/sets', [\App\Http\Controllers\SetsController::class, 'index'])->name('sets.index');
        Route::get('/sets/modal-data', [\App\Http\Controllers\SetsController::class, 'getModalData'])->name('sets.modal-data');
        Route::get('/sets/{set}', [\App\Http\Controllers\SetsController::class, 'show'])->name('sets.show');
        Route::get('/api/sets/containing/{item}', [\App\Http\Controllers\SetsController::class, 'getContainingSets'])->name('sets.containing');
        Route::get('/api/sets/{set}/membership/{item}', [\App\Http\Controllers\SetsController::class, 'checkMembership'])->name('sets.membership');
    });

    // Protected routes
    Route::middleware('auth')->group(function () {
        // Connection Management (for regular users) - must be before wildcard routes
        Route::delete('/connections/{connection}', [\App\Http\Controllers\ConnectionController::class, 'destroy'])->name('connections.destroy');
        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('logout', [EmailFirstAuthController::class, 'destroy'])->name('logout');

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
        
        // AI YAML Generator for authenticated users (for modal use)
        Route::post('/ai-yaml-generator/generate', [\App\Http\Controllers\AiYamlController::class, 'generatePersonYaml'])->name('ai-yaml-generator.generate');
        Route::post('/ai-yaml-generator/improve', [\App\Http\Controllers\AiYamlController::class, 'improvePersonYaml'])->name('ai-yaml-generator.improve');

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
            });
            
            // LinkedIn Import routes
            Route::prefix('import/linkedin')->name('import.linkedin.')->group(function () {
                Route::get('/', [\App\Http\Controllers\LinkedInImportController::class, 'index'])->name('index');
                Route::post('/preview', [\App\Http\Controllers\LinkedInImportController::class, 'preview'])->name('preview');
                Route::post('/import', [\App\Http\Controllers\LinkedInImportController::class, 'import'])->name('import');
            });
        });

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])
                ->name('dashboard');

            // AI YAML Generator routes
            Route::get('/ai-yaml-generator', [\App\Http\Controllers\AiYamlController::class, 'show'])->name('ai-yaml-generator.show');
            Route::post('/ai-yaml-generator/generate', [\App\Http\Controllers\AiYamlController::class, 'generatePersonYaml'])->name('ai-yaml-generator.generate');
            Route::post('/ai-yaml-generator/improve', [\App\Http\Controllers\AiYamlController::class, 'improvePersonYaml'])->name('ai-yaml-generator.improve');
            Route::get('/ai-yaml-generator/placeholders', [\App\Http\Controllers\AiYamlController::class, 'getPlaceholderSpans'])->name('ai-yaml-generator.placeholders');

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

            // Span Types Management
            Route::resource('span-types', SpanTypeController::class);

            // Connection Types Management
            Route::resource('connection-types', ConnectionTypeController::class);

            // Span Management
            Route::get('/spans', [AdminSpanController::class, 'index'])
                ->name('spans.index');
            Route::get('/spans/{span}', [AdminSpanController::class, 'show'])
                ->name('spans.show');
            Route::get('/spans/{span}/edit', [AdminSpanController::class, 'edit'])
                ->name('spans.edit');
            Route::put('/spans/{span}', [AdminSpanController::class, 'update'])
                ->name('spans.update');
            Route::delete('/spans/{span}', [AdminSpanController::class, 'destroy'])
                ->name('spans.destroy');
            
            // Person Subtype Management
            Route::get('/spans/person-subtypes', [AdminSpanController::class, 'managePersonSubtypes'])
                ->name('spans.manage-person-subtypes');
            Route::post('/spans/person-subtypes', [AdminSpanController::class, 'updatePersonSubtypes'])
                ->name('spans.update-person-subtypes');
            
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

            // User Management
            Route::get('/users', [UserController::class, 'index'])
                ->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])
                ->name('users.show');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                ->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])
                ->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])
                ->name('users.destroy');
            Route::post('/users/generate-invitation-codes', [UserController::class, 'generateInvitationCodes'])
                ->name('users.generate-invitation-codes');
            Route::delete('/users/invitation-codes', [UserController::class, 'deleteAllInvitationCodes'])
                ->name('users.delete-all-invitation-codes');

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
            
            // Family Connection Date Sync Tool
            Route::get('/tools/family-connection-date-sync', [App\Http\Controllers\Admin\ToolsController::class, 'familyConnectionDateSync'])
                ->name('tools.family-connection-date-sync');
            Route::post('/tools/family-connection-date-sync', [App\Http\Controllers\Admin\ToolsController::class, 'familyConnectionDateSyncAction'])
                ->name('tools.family-connection-date-sync-action');
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
    });

    // Auth routes - Email First Flow
    Route::middleware('guest')->group(function () {
        Route::get('login', [EmailFirstAuthController::class, 'showEmailForm'])
            ->name('login');
        Route::get('auth/email', function() {
            return redirect()->route('login');
        });
        Route::post('auth/email', [EmailFirstAuthController::class, 'processEmail'])
            ->name('auth.email');
        Route::get('auth/password', [EmailFirstAuthController::class, 'showPasswordForm'])
            ->name('auth.password');
        Route::post('auth/password', [EmailFirstAuthController::class, 'login'])
            ->name('auth.password.submit');

        // Registration routes
        Route::get('register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])
            ->name('register');
        Route::post('register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
            ->name('register.store');
    });

    // Email verification routes
    Route::middleware('auth')->group(function () {
        Route::get('/email/verify', function () {
            return view('auth.verify-email');
        })->name('verification.notice');

        Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();
            return redirect('/home');
        })->middleware('signed')->name('verification.verify');

        Route::post('/email/verification-notification', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('message', 'Verification link sent!');
        })->middleware('throttle:6,1')->name('verification.send');
    });
});

// Remove the test-log route if not needed for production

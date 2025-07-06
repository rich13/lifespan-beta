<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\Auth\EmailFirstAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpanController as AdminSpanController;
use App\Http\Controllers\Admin\SpanPermissionsController;
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

Route::middleware('web')->group(function () {
    // Public routes
    Route::get('/', function () {
        return view('home');
    })->name('home');

    // Date exploration route - supports YYYY, YYYY-MM, and YYYY-MM-DD formats
    Route::get('/date/{date}', [SpanController::class, 'exploreDate'])
        ->where('date', '[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?')
        ->name('date.explore');

    // Span routes
    Route::prefix('spans')->group(function () {
        // Search route (works with session auth)
        Route::get('/search', [SpanController::class, 'search'])->name('spans.search');
        
        // Timeline route (works with session auth)
        Route::get('/{span}/timeline', [App\Http\Controllers\Api\SpanSearchController::class, 'timeline'])->name('spans.timeline');
        
        // Object connections timeline route (works with session auth)
        Route::get('/{span}/timeline-object-connections', [App\Http\Controllers\Api\SpanSearchController::class, 'timelineObjectConnections'])->name('spans.timeline-object-connections');
        
        // Types route (public)
            Route::get('/types', [SpanController::class, 'types'])->name('spans.types');
    Route::get('/types/{type}', [SpanController::class, 'showType'])->name('spans.types.show');
    Route::get('/types/{type}/subtypes', [SpanController::class, 'showSubtypes'])->name('spans.types.subtypes');
    Route::get('/types/{type}/subtypes/{subtype}', [SpanController::class, 'showTypeSubtype'])->name('spans.types.subtypes.show');
        
        // Protected routes
        Route::middleware('auth')->group(function () {
            Route::get('/create', [SpanController::class, 'create'])->name('spans.create');
            Route::post('/', [SpanController::class, 'store'])->name('spans.store');
            Route::get('/{span}/edit', [SpanController::class, 'edit'])->name('spans.edit');
            Route::get('/{span}/editor', [SpanController::class, 'yamlEditor'])->name('spans.yaml-editor');
            Route::post('/{span}/editor/validate', [SpanController::class, 'validateYaml'])->name('spans.yaml-validate');
            Route::post('/{span}/editor/apply', [SpanController::class, 'applyYaml'])->name('spans.yaml-apply');
            Route::put('/{span}', [SpanController::class, 'update'])->name('spans.update');
            Route::delete('/{span}', [SpanController::class, 'destroy'])->name('spans.destroy');
            Route::get('/{span}/compare', [SpanController::class, 'compare'])->name('spans.compare');
            
            // New route for opening YAML editor with content (for new spans)
            Route::post('/editor/new', [SpanController::class, 'yamlEditorNew'])->name('spans.yaml-editor-new');
            Route::get('/editor/new', [SpanController::class, 'yamlEditorNewFromSession'])->name('spans.yaml-editor-new-session');
            Route::post('/editor/new/validate', [SpanController::class, 'validateYamlNew'])->name('spans.yaml-validate-new');
            Route::post('/editor/new/apply', [SpanController::class, 'applyYamlNew'])->name('spans.yaml-apply-new');
            
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
                
                // Create a placeholder connection span with temporal information
                $connectionSpanData = [
                    'name' => "Connection between spans",
                    'type_id' => 'connection',
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'private',
                    'state' => 'placeholder'  // Still marked as placeholder for editing
                ];
                
                // Add date fields if provided
                if ($connectionYear) $connectionSpanData['start_year'] = $connectionYear;
                if (isset($validated['start_month'])) $connectionSpanData['start_month'] = $validated['start_month'];
                if (isset($validated['start_day'])) $connectionSpanData['start_day'] = $validated['start_day'];
                
                $connectionSpan = \App\Models\Span::create($connectionSpanData);
                
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
        });

        // Public routes with span access control
        Route::middleware('span.access')->group(function () {
            Route::get('/', [SpanController::class, 'index'])->name('spans.index');
            Route::get('/{span}', [SpanController::class, 'show'])->name('spans.show');
            Route::get('/{span}/story', [SpanController::class, 'story'])->name('spans.story');
        });

        // New POST route for creating a new span from YAML
        Route::post('/yaml-create', [\App\Http\Controllers\SpanController::class, 'createFromYaml'])->name('spans.yaml-create');

        // Span version history
        Route::get('/{span}/history', [\App\Http\Controllers\SpanController::class, 'history'])->name('spans.history');
        Route::get('/{span}/history/{version}', [\App\Http\Controllers\SpanController::class, 'showVersion'])->name('spans.history.version');
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
        Route::get('/family', [FamilyController::class, 'index'])->name('family.index');
        Route::get('/family/data', [FamilyController::class, 'data'])->name('family.data');
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

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])
                ->name('dashboard');

            // AI YAML Generator routes
            Route::get('/ai-yaml-generator', [\App\Http\Controllers\AiYamlController::class, 'show'])->name('ai-yaml-generator.show');
            Route::post('/ai-yaml-generator/generate', [\App\Http\Controllers\AiYamlController::class, 'generatePersonYaml'])->name('ai-yaml-generator.generate');

            // Import Management
            Route::prefix('import')->name('import.')->group(function () {
                // MusicBrainz Import
                Route::prefix('musicbrainz')->name('musicbrainz.')->group(function () {
                    Route::get('/', [MusicBrainzImportController::class, 'index'])->name('index');
                    Route::post('/search', [MusicBrainzImportController::class, 'search'])->name('search');
                    Route::post('/discography', [MusicBrainzImportController::class, 'showDiscography'])->name('show-discography');
                    Route::post('/tracks', [MusicBrainzImportController::class, 'showTracks'])->name('show-tracks');
                    Route::post('/import', [MusicBrainzImportController::class, 'import'])->name('import');
                });

                // Legacy YAML Import
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
            
            // Span Permissions
            Route::get('/spans/{span}/permissions', [SpanPermissionsController::class, 'edit'])
                ->name('spans.permissions.edit');
            Route::put('/spans/{span}/permissions', [SpanPermissionsController::class, 'update'])
                ->name('spans.permissions.update');
            Route::put('/spans/{span}/permissions/mode', [SpanPermissionsController::class, 'updateMode'])
                ->name('spans.permissions.mode');
                
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
            Route::post('/span-access/make-public-bulk', [SpanAccessManagerController::class, 'makePublicBulk'])
                ->name('span-access.make-public-bulk');
            
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

            // Import routes
            Route::get('/import', [ImportController::class, 'index'])
                ->name('import.index');
            Route::get('/import/musicbrainz', [MusicbrainzImportController::class, 'index'])
                ->name('import.musicbrainz.index');
            Route::post('/import/musicbrainz/search', [MusicbrainzImportController::class, 'search'])
                ->name('import.musicbrainz.search');
            Route::post('/import/musicbrainz/import', [MusicbrainzImportController::class, 'import'])
                ->name('import.musicbrainz.import');
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

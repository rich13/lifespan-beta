<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
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

Route::middleware('web')->group(function () {
    // Public routes
    Route::get('/', function () {
        return view('home');
    })->name('home');

    // Date exploration route
    Route::get('/date/{date}', [SpanController::class, 'exploreDate'])
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('date.explore');

    // Span routes
    Route::prefix('spans')->group(function () {
        // Search route (works with session auth)
        Route::get('/search', [SpanController::class, 'search'])->name('spans.search');
        
        // Protected routes
        Route::middleware('auth')->group(function () {
            Route::get('/create', [SpanController::class, 'create'])->name('spans.create');
            Route::post('/', [SpanController::class, 'store'])->name('spans.store');
            Route::get('/{span}/edit', [SpanController::class, 'edit'])->name('spans.edit');
            Route::put('/{span}', [SpanController::class, 'update'])->name('spans.update');
            Route::delete('/{span}', [SpanController::class, 'destroy'])->name('spans.destroy');
            Route::get('/{span}/compare', [SpanController::class, 'compare'])->name('spans.compare');
        });

        // Public routes
        Route::middleware('span.access')->group(function () {
            Route::get('/', [SpanController::class, 'index'])->name('spans.index');
            Route::get('/{span}', [SpanController::class, 'show'])->name('spans.show');
        });
    });

    // Protected routes
    Route::middleware('auth')->group(function () {
        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('logout', [EmailFirstAuthController::class, 'destroy'])->name('logout');

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])
                ->name('dashboard');

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

            // Connection Management
            Route::get('/connections', [ConnectionController::class, 'index'])
                ->name('connections.index');
            Route::post('/connections', [ConnectionController::class, 'store'])
                ->name('connections.store');
            Route::get('/connections/{connection}', [ConnectionController::class, 'show'])
                ->name('connections.show');
            Route::get('/connections/{connection}/edit', [ConnectionController::class, 'edit'])
                ->name('connections.edit');
            Route::put('/connections/{connection}', [ConnectionController::class, 'update'])
                ->name('connections.update');
            Route::delete('/connections/{connection}', [ConnectionController::class, 'destroy'])
                ->name('connections.destroy');

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

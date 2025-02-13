<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use App\Http\Controllers\Auth\EmailFirstAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpanController as AdminSpanController;
use App\Http\Controllers\Admin\SpanPermissionsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Core routes for the Lifespan application. We start with just the basics
| and will add more sophisticated routing as we build out the system.
|
*/

Route::middleware('web')->group(function () {
    // Public routes
    Route::get('/', function () {
        return view('home');
    })->name('home');

    // Span routes
    Route::prefix('spans')->group(function () {
        // Public routes
        Route::get('/', [SpanController::class, 'index'])->name('spans.index');

        // Protected routes
        Route::middleware('auth')->group(function () {
            Route::get('/create', [SpanController::class, 'create'])->name('spans.create');
            Route::post('/', [SpanController::class, 'store'])->name('spans.store');
            Route::get('/{span}/edit', [SpanController::class, 'edit'])->name('spans.edit');
            Route::put('/{span}', [SpanController::class, 'update'])->name('spans.update');
            Route::delete('/{span}', [SpanController::class, 'destroy'])->name('spans.destroy');
        });

        // Show route (with access middleware)
        Route::get('/{span}', [SpanController::class, 'show'])->name('spans.show')->middleware('span.access');
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

            // User Management
            Route::get('/users', [UserController::class, 'index'])
                ->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])
                ->name('users.show');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                ->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])
                ->name('users.update');
        });
    });

    // Auth routes - Email First Flow
    Route::middleware('guest')->group(function () {
        Route::get('login', [EmailFirstAuthController::class, 'showEmailForm'])
            ->name('login');
        Route::post('auth/email', [EmailFirstAuthController::class, 'handleEmail'])
            ->name('auth.email');
        Route::post('auth/login', [EmailFirstAuthController::class, 'login'])
            ->name('auth.login');
        Route::post('auth/register', [EmailFirstAuthController::class, 'register'])
            ->name('auth.register');
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

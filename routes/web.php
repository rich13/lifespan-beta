<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use App\Http\Controllers\Auth\EmailFirstAuthController;
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

    // Protected routes
    Route::middleware('auth')->group(function () {
        // Span routes
        Route::resource('spans', SpanController::class);

        // Profile routes
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('logout', [EmailFirstAuthController::class, 'destroy'])->name('logout');
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

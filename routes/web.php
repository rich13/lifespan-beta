<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpanController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Core routes for the Lifespan application. We start with just the basics
| and will add more sophisticated routing as we build out the system.
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Span routes
Route::get('/spans', [SpanController::class, 'index'])->name('spans.index');
Route::get('/spans/{span}', [SpanController::class, 'show'])->name('spans.show');

// Auth routes from Breeze
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-log', function() {
    Log::error('This is a test error message');
    return 'Check Telescope now!';
});

require __DIR__.'/auth.php';

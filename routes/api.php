<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SpanSearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Note: Span search endpoint moved to web routes (/spans/search) for better session auth support

Route::middleware('auth:sanctum')->group(function () {
    // Other API endpoints that need Sanctum auth can go here
});

// Span search API
Route::get('/spans/search', [SpanSearchController::class, 'search']);

// Timeline APIs - moved to cleaner structure
Route::middleware(['auth'])->group(function () {
    Route::get('/spans/{span}', [SpanSearchController::class, 'timeline']);
    Route::get('/spans/{span}/object-connections', [SpanSearchController::class, 'timelineObjectConnections']);
    Route::get('/spans/{span}/during-connections', [SpanSearchController::class, 'timelineDuringConnections']);
});

// Residence timeline API
Route::get('/spans/{span}/residence-timeline', [SpanSearchController::class, 'residenceTimeline']);

// Wikipedia On This Day API
Route::get('/wikipedia/on-this-day/{month}/{day}', function ($month, $day) {
    $service = new \App\Services\WikipediaOnThisDayService();
    $data = $service->getOnThisDay((int)$month, (int)$day);
    
    return response()->json([
        'success' => true,
        'data' => $data
    ]);
});

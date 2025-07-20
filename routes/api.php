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

// Timeline APIs - allow unauthenticated access, let the controller handle access control
Route::get('/spans/{span}', [SpanSearchController::class, 'timeline']);
Route::get('/spans/{span}/object-connections', [SpanSearchController::class, 'timelineObjectConnections']);
Route::get('/spans/{span}/during-connections', [SpanSearchController::class, 'timelineDuringConnections']);

// Temporal relationship API
Route::get('/spans/{span}/temporal', [SpanSearchController::class, 'temporal']);

// Residence timeline API


// Wikipedia On This Day API
Route::get('/wikipedia/on-this-day/{month}/{day}', function ($month, $day) {
    $service = new \App\Services\WikipediaOnThisDayService();
    $data = $service->getOnThisDay((int)$month, (int)$day);
    
    return response()->json([
        'success' => true,
        'data' => $data
    ]);
});

// Connection Types API
Route::get('/connection-types', function (Request $request) {
    $spanType = $request->query('span_type');
    
    if ($spanType) {
        // Filter connection types based on the span type
        $types = \App\Models\ConnectionType::whereJsonContains('allowed_span_types->parent', $spanType)->get();
    } else {
        // Return all connection types if no span type specified
        $types = \App\Models\ConnectionType::all();
    }
    
    return response()->json($types);
});

// Connections API
Route::post('/connections/create', [\App\Http\Controllers\ConnectionController::class, 'store']);

// Create placeholder span API
Route::middleware('auth')->post('/spans/create', [\App\Http\Controllers\Api\SpanSearchController::class, 'store']);

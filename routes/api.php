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

// Admin-only API endpoints
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Fetch OSM data for a place
    Route::post('/places/{span}/fetch-osm-data', function (Request $request, \App\Models\Span $span) {
        if ($span->type_id !== 'place') {
            return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
        }
        
        try {
            $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
            $success = $geocodingWorkflow->resolvePlace($span);
            
            if ($success) {
                return response()->json([
                    'success' => true, 
                    'message' => 'OSM data fetched successfully',
                    'has_osm_data' => $span->fresh()->getOsmData() !== null,
                    'has_coordinates' => $span->fresh()->getCoordinates() !== null
                ]);
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
                'message' => 'An error occurred while fetching OSM data'
            ], 500);
        }
    });
});

// Span search API
Route::get('/spans/search', [SpanSearchController::class, 'search']);

// Timeline APIs - allow unauthenticated access, let the controller handle access control
Route::get('/spans/{span}', [SpanSearchController::class, 'timeline'])->middleware('timeout.prevention');
Route::get('/spans/{span}/object-connections', [SpanSearchController::class, 'timelineObjectConnections'])->middleware('timeout.prevention');
Route::get('/spans/{span}/during-connections', [SpanSearchController::class, 'timelineDuringConnections'])->middleware('timeout.prevention');

// Temporal relationship API
Route::get('/spans/{span}/temporal', [SpanSearchController::class, 'temporal']);

// Residence timeline API


// Wikipedia On This Day API
Route::get('/wikipedia/on-this-day/{month}/{day}', function ($month, $day) {
    try {
        $service = new \App\Services\WikipediaOnThisDayService();
        $data = $service->getOnThisDay((int)$month, (int)$day);
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        \Log::error('Wikipedia API error', [
            'month' => $month,
            'day' => $day,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to load Wikipedia data',
            'message' => 'The request timed out or failed. Please try again later.'
        ], 408); // Request Timeout
    }
})->middleware('timeout.prevention');

// Connection Types API
Route::get('/connection-types', function (Request $request) {
    $spanType = $request->query('span_type');
    $mode = $request->query('mode');
    
    if ($spanType) {
        if ($mode === 'reverse') {
            // In reverse mode, filter connection types where the span type is in the 'child' array
            // This means the current span will be the object, and we need connection types
            // that allow the current span type as a child
            $types = \App\Models\ConnectionType::whereJsonContains('allowed_span_types->child', $spanType)->get();
        } else {
            // In forward mode, filter connection types where the span type is in the 'parent' array
            $types = \App\Models\ConnectionType::whereJsonContains('allowed_span_types->parent', $spanType)->get();
        }
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

// Wikidata description API (admin only)
Route::middleware('auth')->post('/spans/{span}/fetch-wikimedia-description', function (Request $request, \App\Models\Span $span) {
    // Helper function to check if a date should be improved
    $shouldImproveDate = function(\App\Models\Span $span, string $dateType, array $newDates) {
        $prefix = $dateType === 'start' ? 'start' : 'end';
        $currentYear = $span->{$prefix . '_year'};
        $currentMonth = $span->{$prefix . '_month'};
        $currentDay = $span->{$prefix . '_day'};
        $currentPrecision = $span->{$prefix . '_precision'};
        
        $newYear = $newDates[$prefix . '_year'];
        $newMonth = $newDates[$prefix . '_month'];
        $newDay = $newDates[$prefix . '_day'];
        $newPrecision = $newDates[$prefix . '_precision'];

        if ($currentYear !== $newYear) {
            return false;
        }
        
        $has01_01Problem = ($currentMonth === 1 && $currentDay === 1);
        $currentPrecisionLevel = $currentPrecision === 'year' ? 1 : ($currentPrecision === 'month' ? 2 : 3);
        $newPrecisionLevel = $newPrecision === 'year' ? 1 : ($newPrecision === 'month' ? 2 : 3);
        
        return $has01_01Problem || $newPrecisionLevel > $currentPrecisionLevel;
    };
    // Check if user is admin
    if (!auth()->user()->is_admin) {
        return response()->json([
            'success' => false,
            'message' => 'Only administrators can fetch Wikidata descriptions.'
        ], 403);
    }
    
    try {
        $wikimediaService = new \App\Services\WikimediaService();
        $result = $wikimediaService->getDescriptionForSpan($span);
        
        if ($result) {
            $description = $result['description'];
            $wikipediaUrl = $result['wikipedia_url'] ?? null;
            $dates = $result['dates'] ?? null;
            
            // Update the span with the new description
            $span->update(['description' => $description]);
            
            // Handle dates if available
            $dateNoteAdded = false;
            if ($dates) {
                $updateData = [];
                
                // Handle start date (birth)
                if ($dates['start_year']) {
                    if (!$span->start_year || $shouldImproveDate($span, 'start', $dates)) {
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                    }
                }
                
                // Handle end date (death)
                if ($dates['end_year']) {
                    if (!$span->end_year || $shouldImproveDate($span, 'end', $dates)) {
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                    }
                }
                
                // Update dates if we have any
                if (!empty($updateData)) {
                    $span->update($updateData);
                }
                
                // Check if we have limited precision dates that we can't improve further
                if (($dates['start_precision'] === 'year' || $dates['start_precision'] === 'month') ||
                    ($dates['end_precision'] === 'year' || $dates['end_precision'] === 'month')) {
                    $dateNoteAdded = true;
                    $currentNotes = $span->notes ?? '';
                    $dateNote = "\n\n[Wikipedia import complete - dates available with limited precision]";
                    $span->update(['notes' => $currentNotes . $dateNote]);
                }
            }
            
            // Add Wikipedia URL to sources if it exists and isn't already there
            if ($wikipediaUrl) {
                $currentSources = $span->sources ?? [];
                
                // Check if the Wikipedia URL is already in sources
                $wikipediaUrlExists = false;
                foreach ($currentSources as $source) {
                    if (is_string($source) && strpos($source, 'wikipedia.org') !== false) {
                        $wikipediaUrlExists = true;
                        break;
                    } elseif (is_array($source) && isset($source['url']) && strpos($source['url'], 'wikipedia.org') !== false) {
                        $wikipediaUrlExists = true;
                        break;
                    }
                }
                
                // Add the Wikipedia URL if it's not already there
                if (!$wikipediaUrlExists) {
                    $currentSources[] = [
                        'title' => 'Wikipedia',
                        'url' => $wikipediaUrl,
                        'type' => 'web',
                        'added_by' => 'wikidata_fetch'
                    ];
                    $span->update(['sources' => $currentSources]);
                }
                
                $message = 'Description fetched and Wikipedia source added successfully.';
                if ($dateNoteAdded) {
                    $message .= ' Dates added with limited precision - marked as complete.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'description' => $description,
                    'wikipedia_url' => $wikipediaUrl
                ]);
            } else {
                // We got a description but no Wikipedia URL - add skip note
                $currentNotes = $span->notes ?? '';
                $skipNote = "\n\n[Skipped Wikipedia import - no Wikipedia page found]";
                $span->update(['notes' => $currentNotes . $skipNote]);
                
                $message = 'Description fetched successfully, but no Wikipedia page found.';
                if ($dateNoteAdded) {
                    $message .= ' Dates added with limited precision - marked as complete.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'description' => $description,
                    'wikipedia_url' => null
                ]);
            }
        } else {
            // Add a note to the span that no description was found
            $currentNotes = $span->notes ?? '';
            $skipNote = "\n\n[Skipped Wikipedia import - not found on Wikipedia]";
            $span->update(['notes' => $currentNotes . $skipNote]);
            
            return response()->json([
                'success' => false,
                'message' => 'No suitable description found on Wikidata for this span.'
            ], 404);
        }
    } catch (\Exception $e) {
        \Log::error('Wikidata description fetch failed', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch description from Wikidata. Please try again later.'
        ], 500);
    }
});

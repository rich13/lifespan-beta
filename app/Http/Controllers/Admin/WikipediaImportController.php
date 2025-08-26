<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Services\WikimediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WikipediaImportController extends Controller
{
    protected WikimediaService $wikimediaService;

    public function __construct(WikimediaService $wikimediaService)
    {
        $this->middleware(['auth', 'admin']);
        $this->wikimediaService = $wikimediaService;
    }

    /**
     * Show the Wikipedia import interface
     */
    public function index()
    {
        // Get public figures that need improvement (no description, no Wikipedia source, no dates, or 01-01 problem)
        // Exclude people who have been skipped
        $publicFigures = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->where(function($query) {
                $query->whereNull('description')
                    ->orWhere(function($subQuery) {
                        // No Wikipedia sources (only if not skipped)
                        $subQuery->where(function($sourceQuery) {
                            $sourceQuery->whereNull('sources')
                                ->orWhereJsonLength('sources', 0);
                        })->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                    })
                    ->orWhere(function($subQuery) {
                        // No dates (only if not skipped)
                        $subQuery->whereNull('start_year')
                            ->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                    })
                    ->orWhere(function($subQuery) {
                        // 01-01 problem (only if not skipped)
                        $subQuery->where('start_month', 1)
                            ->where('start_day', 1)
                            ->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                    })
                    ->orWhere(function($subQuery) {
                        // End date 01-01 problem (only if not skipped)
                        $subQuery->whereNotNull('end_year')
                            ->where('end_month', 1)
                            ->where('end_day', 1)
                            ->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                    });
            })
            ->where(function($query) {
                $query->whereNull('notes')
                      ->orWhere(function($subQuery) {
                          $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'")
                                  ->whereRaw("notes NOT LIKE '%[Wikipedia import complete%'");
                      });
            })
            ->orderBy('name')
            ->paginate(50);

        $totalPublicFigures = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->count();

        $publicFiguresWithDescriptions = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('description')
            ->count();

        // For now, we'll use a simpler approach for Wikipedia sources count
        $publicFiguresWithWikipediaSources = 0; // We'll calculate this differently if needed

        return view('admin.import.wikipedia.index', compact(
            'publicFigures',
            'totalPublicFigures',
            'publicFiguresWithDescriptions',
            'publicFiguresWithWikipediaSources'
        ));
    }

    /**
     * Process a single person for Wikipedia import
     */
    public function processPerson(Request $request)
    {
        $request->validate([
            'span_id' => 'required|uuid|exists:spans,id'
        ]);

        $span = Span::findOrFail($request->span_id);
        
        // Check if this is a public figure
        if ($span->type_id !== 'person' || 
            !isset($span->metadata['subtype']) || 
            $span->metadata['subtype'] !== 'public_figure') {
            return response()->json([
                'success' => false,
                'message' => 'This span is not a public figure.'
            ], 400);
        }

        try {
            // Get description and Wikipedia URL
            $result = $this->wikimediaService->getDescriptionForSpan($span);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No suitable description found on Wikipedia for this person.'
                ], 404);
            }

            $description = $result['description'];
            $wikipediaUrl = $result['wikipedia_url'] ?? null;
            $dates = $result['dates'] ?? null;
            
            // Prepare update data
            $updateData = ['description' => $description];
            
            // Add or improve dates if available
            if ($dates) {
                // Check if we can improve start date
                $startDateImproved = false;
                if ($dates['start_year']) {
                    if (!$span->start_year) {
                        // No start date exists, add it
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                        $startDateImproved = true;
                    } elseif ($this->shouldImproveDate($span, 'start', $dates)) {
                        // Existing date can be improved
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                        $startDateImproved = true;
                    }
                }
                
                // Check if we can improve end date
                $endDateImproved = false;
                if ($dates['end_year']) {
                    if (!$span->end_year) {
                        // No end date exists, add it
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                        $endDateImproved = true;
                    } elseif ($this->shouldImproveDate($span, 'end', $dates)) {
                        // Existing date can be improved
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                        $endDateImproved = true;
                    }
                }
            }
            
            // Update the span
            $span->update($updateData);
            
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
                        'added_by' => 'wikipedia_bulk_import'
                    ];
                    $span->update(['sources' => $currentSources]);
                }
            } else {
                // We got a description but no Wikipedia URL - add skip note
                $currentNotes = $span->notes ?? '';
                $skipNote = "\n\n[Skipped Wikipedia import - no Wikipedia page found]";
                $span->update(['notes' => $currentNotes . $skipNote]);
            }
            
            // Check what was added or improved
            $datesAdded = false;
            $datesImproved = false;
            $dateNoteAdded = false;
            if ($dates) {
                $datesAdded = (!$span->start_year && $dates['start_year']) || (!$span->end_year && $dates['end_year']);
                $datesImproved = $startDateImproved ?? false || $endDateImproved ?? false;
                
                // Check if we have limited precision dates that we can't improve further
                if (($dates['start_precision'] === 'year' || $dates['start_precision'] === 'month') ||
                    ($dates['end_precision'] === 'year' || $dates['end_precision'] === 'month')) {
                    $dateNoteAdded = true;
                    $currentNotes = $span->notes ?? '';
                    $dateNote = "\n\n[Wikipedia import complete - dates available with limited precision]";
                    $span->update(['notes' => $currentNotes . $dateNote]);
                }
            }

            Log::info('Wikipedia bulk import processed person', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'description_added' => !empty($description),
                'wikipedia_source_added' => !empty($wikipediaUrl),
                'dates_added' => $datesAdded,
                'dates_improved' => $datesImproved
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Person processed successfully.',
                'data' => [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'description' => $description,
                    'wikipedia_url' => $wikipediaUrl,
                    'description_added' => !empty($description),
                    'wikipedia_source_added' => !empty($wikipediaUrl),
                    'dates_added' => $datesAdded,
                    'dates_improved' => $datesImproved
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Wikipedia bulk import failed for person', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process person: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a date should be improved based on existing data
     */
    private function shouldImproveDate(\App\Models\Span $span, string $dateType, array $newDates): bool
    {
        $prefix = $dateType === 'start' ? 'start' : 'end';
        $currentYear = $span->{$prefix . '_year'};
        $currentMonth = $span->{$prefix . '_month'};
        $currentDay = $span->{$prefix . '_day'};
        $currentPrecision = $span->{$prefix . '_precision'};
        
        $newYear = $newDates[$prefix . '_year'];
        $newMonth = $newDates[$prefix . '_month'];
        $newDay = $newDates[$prefix . '_day'];
        $newPrecision = $newDates[$prefix . '_precision'];

        // If years don't match, don't improve (different person or data)
        if ($currentYear !== $newYear) {
            return false;
        }

        // Check for the 01-01 problem: if current date has month=1 and day=1, it's likely wrong
        $has01_01Problem = ($currentMonth === 1 && $currentDay === 1);
        
        // Check if new data has better precision
        $currentPrecisionLevel = $this->getPrecisionLevel($currentPrecision);
        $newPrecisionLevel = $this->getPrecisionLevel($newPrecision);
        
        // Improve if:
        // 1. Current date has 01-01 problem, OR
        // 2. New data has better precision (more specific)
        return $has01_01Problem || $newPrecisionLevel > $currentPrecisionLevel;
    }

    /**
     * Get precision level as integer for comparison
     */
    private function getPrecisionLevel(string $precision): int
    {
        switch ($precision) {
            case 'year': return 1;
            case 'month': return 2;
            case 'day': return 3;
            default: return 0;
        }
    }

    /**
     * Skip a person (not found on Wikipedia)
     */
    public function skipPerson(Request $request)
    {
        $request->validate([
            'span_id' => 'required|uuid|exists:spans,id'
        ]);

        $span = Span::findOrFail($request->span_id);
        
        // Check if this is a public figure
        if ($span->type_id !== 'person' || 
            !isset($span->metadata['subtype']) || 
            $span->metadata['subtype'] !== 'public_figure') {
            return response()->json([
                'success' => false,
                'message' => 'This span is not a public figure.'
            ], 400);
        }

        // Add a note to the span that it was skipped
        $currentNotes = $span->notes ?? '';
        $skipNote = "\n\n[Skipped Wikipedia import - not found on Wikipedia]";
        $span->update(['notes' => $currentNotes . $skipNote]);

        Log::info('Wikipedia bulk import skipped person', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'reason' => 'not_found_on_wikipedia'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Person skipped successfully.',
            'data' => [
                'span_id' => $span->id,
                'span_name' => $span->name
            ]
        ]);
    }

    /**
     * Get updated stats for the interface
     */
    public function getStats()
    {
        $totalPublicFigures = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->count();

        $publicFiguresWithDescriptions = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('description')
            ->count();

        $publicFiguresWithWikipediaSources = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereJsonContains('sources', ['url' => ['like' => '%wikipedia.org%']])
            ->count();

        // For now, we'll use a simpler approach for Wikipedia sources count
        $publicFiguresWithWikipediaSources = 0; // We'll calculate this differently if needed

        return response()->json([
            'success' => true,
            'stats' => [
                'total_public_figures' => $totalPublicFigures,
                'with_descriptions' => $publicFiguresWithDescriptions,
                'with_wikipedia_sources' => $publicFiguresWithWikipediaSources,
                'without_descriptions' => $totalPublicFigures - $publicFiguresWithDescriptions,
                'without_wikipedia_sources' => $totalPublicFigures - $publicFiguresWithWikipediaSources
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportWikipediaPublicFiguresJob;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\WikipediaImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WikipediaImportController extends Controller
{
    public function __construct(
        private readonly WikipediaImportService $wikipediaImportService
    ) {
        $this->middleware(['auth', 'admin']);
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
                        // No Wikipedia source (have sources from elsewhere but not Wikipedia)
                        $subQuery->whereRaw("sources IS NULL OR sources::text NOT ILIKE '%wikipedia.org%'")
                            ->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
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

        $publicFiguresWithWikipediaSources = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('sources')
            ->whereRaw("sources::text ILIKE '%wikipedia.org%'")
            ->count();

        // With description but missing Wikipedia source – these need the source added
        $withDescriptionMissingWikiSource = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('description')
            ->where(function ($q) {
                $q->whereNull('sources')
                    ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
            })
            ->count();

        return view('admin.import.wikipedia.index', compact(
            'publicFigures',
            'totalPublicFigures',
            'publicFiguresWithDescriptions',
            'publicFiguresWithWikipediaSources',
            'withDescriptionMissingWikiSource'
        ));
    }

    /**
     * Process a single person for Wikipedia import
     */
    public function processPerson(Request $request)
    {
        $request->validate([
            'span_id' => 'required|uuid|exists:spans,id',
        ]);

        $span = Span::findOrFail($request->span_id);

        if ($span->type_id !== 'person' ||
            !isset($span->metadata['subtype']) ||
            $span->metadata['subtype'] !== 'public_figure') {
            return response()->json([
                'success' => false,
                'message' => 'This span is not a public figure.',
            ], 400);
        }

        $result = $this->wikipediaImportService->processSpan($span);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? [],
            ]);
        }

        $status = str_contains($result['message'] ?? '', 'No suitable description') ? 404 : 500;

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], $status);
    }

    /**
     * Skip a person (not found on Wikipedia)
     */
    public function skipPerson(Request $request)
    {
        $request->validate([
            'span_id' => 'required|uuid|exists:spans,id',
        ]);

        $span = Span::findOrFail($request->span_id);

        if ($span->type_id !== 'person' ||
            !isset($span->metadata['subtype']) ||
            $span->metadata['subtype'] !== 'public_figure') {
            return response()->json([
                'success' => false,
                'message' => 'This span is not a public figure.',
            ], 400);
        }

        $this->wikipediaImportService->skipSpan($span);

        Log::info('Wikipedia bulk import skipped person', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'reason' => 'not_found_on_wikipedia',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Person skipped successfully.',
            'data' => [
                'span_id' => $span->id,
                'span_name' => $span->name,
            ],
        ]);
    }

    /**
     * Start Wikipedia public figures import as a background job
     */
    public function startBackgroundImport(Request $request)
    {
        $retrySkipped = filter_var($request->input('retry_skipped', false), FILTER_VALIDATE_BOOLEAN);

        ImportProgress::where('import_type', 'wikipedia_public_figures')
            ->where('user_id', auth()->id())
            ->delete();

        ImportWikipediaPublicFiguresJob::dispatch((string) auth()->id(), $retrySkipped);

        return response()->json([
            'success' => true,
            'message' => $retrySkipped
                ? 'Import started (retrying previously skipped). Progress will update as public figures are processed.'
                : 'Import started in background. Progress will update as public figures are processed.',
        ]);
    }

    /**
     * Cancel the background Wikipedia import
     */
    public function cancelBackgroundImport(Request $request)
    {
        $progress = ImportProgress::forWikipediaPublicFigures((string) auth()->id());
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import cancelled. If it was running, it will stop after the current person.',
        ]);
    }

    /**
     * Get background import status
     */
    public function getBackgroundStatus(Request $request)
    {
        $progress = ImportProgress::forWikipediaPublicFigures((string) auth()->id());
        if ($progress && in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'])) {
            $jobProgress = $progress->toJobProgressArray();

            $withDescriptionMissingWikiSource = Span::where('type_id', 'person')
                ->whereJsonContains('metadata->subtype', 'public_figure')
                ->whereNotNull('description')
                ->where(function ($q) {
                    $q->whereNull('sources')
                        ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
                })
                ->count();

            $previouslySkippedNeedWikiSource = Span::where('type_id', 'person')
                ->whereJsonContains('metadata->subtype', 'public_figure')
                ->whereNotNull('description')
                ->where(function ($q) {
                    $q->whereNull('sources')
                        ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
                })
                ->whereRaw("notes LIKE '%[Skipped Wikipedia import%'")
                ->count();

            return response()->json([
                'success' => true,
                'background_job' => true,
                'job_status' => $progress->status,
                'job_progress' => $jobProgress,
                'stats' => [
                    'total_need_wiki_source' => $withDescriptionMissingWikiSource,
                    'previously_skipped' => $previouslySkippedNeedWikiSource,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'background_job' => false,
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
            ->whereNotNull('sources')
            ->whereRaw("sources::text ILIKE '%wikipedia.org%'")
            ->count();

        $withDescriptionMissingWikiSource = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('description')
            ->where(function ($q) {
                $q->whereNull('sources')
                    ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
            })
            ->count();

        $previouslySkippedNeedWikiSource = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->whereNotNull('description')
            ->where(function ($q) {
                $q->whereNull('sources')
                    ->orWhereRaw("sources::text NOT ILIKE '%wikipedia.org%'");
            })
            ->whereRaw("notes LIKE '%[Skipped Wikipedia import%'")
            ->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'total_public_figures' => $totalPublicFigures,
                'with_descriptions' => $publicFiguresWithDescriptions,
                'with_wikipedia_sources' => $publicFiguresWithWikipediaSources,
                'without_descriptions' => $totalPublicFigures - $publicFiguresWithDescriptions,
                'with_description_missing_wiki_source' => $withDescriptionMissingWikiSource,
                'previously_skipped_need_wiki_source' => $previouslySkippedNeedWikiSource,
            ]
        ]);
    }
}

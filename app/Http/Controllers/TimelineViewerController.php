<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Span;
use App\Models\User;
use Carbon\Carbon;

class TimelineViewerController extends Controller
{
    /**
     * Show the timeline viewer interface
     */
    public function index(): View
    {
        $user = Auth::user();
        $initialViewport = $this->calculateInitialViewport($user);
        
        // Debug logging
        \Log::info('Initial viewport calculated', [
            'user' => $user ? $user->name : 'not logged in',
            'has_personal_span' => $user && $user->personalSpan ? 'yes' : 'no',
            'initial_viewport' => $initialViewport
        ]);
        
        return view('viewer.index', compact('initialViewport'));
    }

    /**
     * Get spans that intersect with the given viewport
     */
    public function getSpansInViewport(Request $request): JsonResponse
    {
        $request->validate([
            'start_year' => 'required|integer|min:1000|max:3000',
            'end_year' => 'required|integer|min:1000|max:3000',
            'start_month' => 'nullable|integer|min:1|max:12',
            'end_month' => 'nullable|integer|min:1|max:12',
            'start_day' => 'nullable|integer|min:1|max:31',
            'end_day' => 'nullable|integer|min:1|max:31',
            'span_types' => 'nullable|array',
            'span_types.*' => 'string|in:person,organisation,place,event,band,thing'
        ]);

        $user = Auth::user();
        
        // Build the viewport date range
        $viewportStart = Carbon::createFromDate(
            $request->start_year,
            $request->start_month ?? 1,
            $request->start_day ?? 1
        );
        
        $viewportEnd = Carbon::createFromDate(
            $request->end_year,
            $request->end_month ?? 12,
            $request->end_day ?? 31
        );

        // Determine limit (fetch fewer by default)
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) { $limit = 1; }
        if ($limit > 200) { $limit = 200; }

        // Query builder base (access + types)
        $query = Span::query();

        // Apply access control
        if (!$user) {
            $query->where('access_level', 'public');
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', $user->id)
                  ->orWhere('access_level', 'shared');
            });
        }

        // Filter by span types if specified
        if ($request->has('span_types') && !empty($request->span_types)) {
            $query->whereIn('type_id', $request->span_types);
        } else {
            // Default to person spans for now
            $query->where('type_id', 'person');
        }

        // Only get spans with valid start dates
        $query->whereNotNull('start_year');

        // Balanced selection strategy: fetch a mix of starts-in, ends-in, and spanning
        // Default to false for determinism unless explicitly requested
        $balanced = (bool) $request->boolean('balanced', false);
        if ($balanced) {
            $perBucket = max(1, (int) floor($limit / 3));
            $extra = $limit - ($perBucket * 3);

            $base = clone $query;

            // Starts in viewport
            $startsIn = (clone $base)
                ->whereBetween('start_year', [$viewportStart->year, $viewportEnd->year])
                ->orderBy('start_year')
                ->orderBy('start_month')
                ->orderBy('start_day')
                ->limit($perBucket + ($extra > 0 ? 1 : 0))
                ->get();

            // Ends in viewport
            $endsIn = (clone $base)
                ->whereNotNull('end_year')
                ->whereBetween('end_year', [$viewportStart->year, $viewportEnd->year])
                ->orderBy('end_year')
                ->orderBy('end_month')
                ->orderBy('end_day')
                ->limit($perBucket + ($extra > 1 ? 1 : 0))
                ->get();

            // Spans across viewport (start before start, end after end or null)
            $spanning = (clone $base)
                ->where('start_year', '<', $viewportStart->year)
                ->where(function ($q2) use ($viewportEnd) {
                    $q2->whereNull('end_year')
                       ->orWhere('end_year', '>', $viewportEnd->year);
                })
                ->orderBy('start_year', 'desc')
                ->limit($perBucket)
                ->get();

            // Merge and dedupe initial results
            $spans = $startsIn->concat($endsIn)->concat($spanning)
                ->unique('id')
                ->take($limit)
                ->values();

            // Re-query to enforce deterministic ordering and eager load relations
            if ($spans->isNotEmpty()) {
                $ids = $spans->pluck('id');
                $spans = Span::with('type')
                    ->whereIn('id', $ids)
                    ->orderBy('start_year')
                    ->orderByRaw('COALESCE(start_month, 1)')
                    ->orderByRaw('COALESCE(start_day, 1)')
                    ->orderBy('id')
                    ->limit($limit)
                    ->get();
            }
        } else {
            // Multi-tier approach: prioritize modern/recent people
            $spans = collect();
            
            // Tier 1: People who start in viewport (1970-2025)
            $startsInViewport = $query->whereBetween('start_year', [$viewportStart->year, $viewportEnd->year])
                ->with('type')
                ->orderBy('start_year', 'desc') // Most recent first
                ->orderBy('start_month')
                ->orderBy('start_day')
                ->orderBy('id')
                ->limit($limit)
                ->get();
            
            $spans = $spans->concat($startsInViewport);
            
            // Tier 2: Ongoing people who started recently (1950-2025)
            if ($spans->count() < $limit) {
                $remaining = $limit - $spans->count();
                $recentOngoing = (clone $query)
                    ->whereNull('end_year')
                    ->where('start_year', '>=', 1950) // Started in modern era
                    ->where('start_year', '<', $viewportStart->year) // But before viewport start
                    ->with('type')
                    ->orderBy('start_year', 'desc') // Most recent first
                    ->orderBy('start_month')
                    ->orderBy('start_day')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get();
                
                $spans = $spans->concat($recentOngoing);
            }
            
            // Tier 3: People who ended recently (1970-2025)
            if ($spans->count() < $limit) {
                $remaining = $limit - $spans->count();
                $recentEnded = (clone $query)
                    ->whereNotNull('end_year')
                    ->whereBetween('end_year', [1970, $viewportEnd->year]) // Ended in recent era
                    ->where('start_year', '<', $viewportStart->year) // Started before viewport
                    ->with('type')
                    ->orderBy('end_year', 'desc') // Most recent deaths first
                    ->orderBy('end_month')
                    ->orderBy('end_day')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get();
                
                $spans = $spans->concat($recentEnded);
            }
            
            // Tier 4: Fill with any remaining ongoing people
            if ($spans->count() < $limit) {
                $remaining = $limit - $spans->count();
                $otherOngoing = (clone $query)
                    ->whereNull('end_year')
                    ->where('start_year', '<', 1950) // Older ongoing people
                    ->with('type')
                    ->orderBy('start_year', 'desc') // Most recent first
                    ->orderBy('start_month')
                    ->orderBy('start_day')
                    ->orderBy('id')
                    ->limit($remaining)
                    ->get();
                
                $spans = $spans->concat($otherOngoing);
            }
        }

        // Transform the data for the frontend
        $transformedSpans = $spans->map(function ($span) {
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type_id' => $span->type_id,
                'type_name' => $span->type->name ?? 'Unknown',
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'is_ongoing' => $span->end_year === null,
                'description' => $span->description,
                'metadata' => $span->metadata,
                'access_level' => $span->access_level,
                'owner_id' => $span->owner_id,
                'is_personal_span' => $span->is_personal_span ?? false,
            ];
        });

        // Debug logging
        \Log::info('TimelineViewer query', [
            'viewport' => [$request->start_year, $request->end_year],
            'limit' => $limit,
            'balanced' => $balanced,
            'spans_count' => $spans->count(),
            'spans_sample' => $spans->take(5)->map(fn($s) => "{$s->name} ({$s->start_year}-{$s->end_year})")->toArray(),
            'date_range' => $spans->count() > 0 ? [
                'min_start' => $spans->min('start_year'),
                'max_start' => $spans->max('start_year'),
                'min_end' => $spans->whereNotNull('end_year')->min('end_year'),
                'max_end' => $spans->whereNotNull('end_year')->max('end_year'),
            ] : null
        ]);

        return response()->json([
            'spans' => $transformedSpans,
            'viewport' => [
                'start_year' => $request->start_year,
                'end_year' => $request->end_year,
                'start_month' => $request->start_month,
                'end_month' => $request->end_month,
                'start_day' => $request->start_day,
                'end_day' => $request->end_day,
            ],
            'total_count' => $spans->count(),
        ]);
    }

    /**
     * Calculate the initial viewport based on the user's personal span
     */
    private function calculateInitialViewport(?User $user): array
    {
        if (!$user || !$user->personalSpan) {
            // Default viewport for users without personal spans
            // Use a range that covers modern person spans (1970-2025)
            return [
                'start_year' => 1970,
                'end_year' => 2025,
                'start_month' => 1,
                'end_month' => 12,
                'start_day' => 1,
                'end_day' => 31,
            ];
        }

        $personalSpan = $user->personalSpan;
        $birthYear = $personalSpan->start_year;
        $currentYear = Carbon::now()->year;

        // For modern people (born after 1950), focus on 1970-2025 range
        // For historical people, use the original logic
        if ($birthYear >= 1950) {
            return [
                'start_year' => 1970,
                'end_year' => 2025,
                'start_month' => 1,
                'end_month' => 12,
                'start_day' => 1,
                'end_day' => 31,
            ];
        } else {
            // Show from 10 years before birth to current year + 5 years
            return [
                'start_year' => $birthYear - 10,
                'end_year' => $currentYear + 5,
                'start_month' => 1,
                'end_month' => 12,
                'start_day' => 1,
                'end_day' => 31,
            ];
        }
    }
}

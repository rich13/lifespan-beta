<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhotoController extends Controller
{
    private const PHOTOS_PER_PAGE = 24;

    /** Max photos to load via infinite scroll before showing "Load more" (keeps DOM manageable) */
    private const INFINITE_SCROLL_MAX = 300;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('span.access');
    }

    /**
     * Display a listing of photo spans.
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = Span::where('type_id', 'thing')
            // Use direct JSON equality; subtype is stored as a string, not an array
            ->where('metadata->subtype', 'photo');

        // Force empty results when span doesn't exist (graceful 404 handling)
        if ($request->has('_no_results')) {
            $query->whereRaw('1 = 0');
        }

        // Exclude photos of plaques by default (photos that feature a plaque span)
        if (!$request->boolean('include_plaques')) {
            $query->whereDoesntHave('connectionsAsSubject', function ($q) {
                $q->where('type_id', 'features')
                    ->whereHas('child', function ($childQ) {
                        $childQ->where('type_id', 'thing')
                            ->where('metadata->subtype', 'plaque');
                    });
            });
        }

        // Apply access control (mirror Span::applyAccessControl logic for listing)
        $user = auth()->user();
        if (!$user) {
            // Guests: only public
            $query->where('access_level', 'public');
        } elseif (!$user->is_admin) {
            // Non-admin: public, own, user or group permissions
            $query->where(function ($q) use ($user) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', $user->id)
                  ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                      $permQ->where('user_id', $user->id)
                            ->whereIn('permission_type', ['view', 'edit']);
                  })
                  ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                      $permQ->whereNotNull('group_id')
                            ->whereIn('permission_type', ['view', 'edit'])
                            ->whereHas('group', function ($groupQ) use ($user) {
                                $groupQ->whereHas('users', function ($userQ) use ($user) {
                                    $userQ->where('user_id', $user->id);
                                });
                            });
                  });
            });
        }

        // Apply "photos_filter" - show my photos, public (not my) photos, or all accessible photos
        // Default to "all" (show all accessible photos)
        $photosFilter = $request->filled('photos_filter') ? $request->photos_filter : 'all';
        
        if ($photosFilter === 'my' && $user && $user->personalSpan) {
            // Show only photos created by current user
            $query->whereHas('connectionsAsObject', function ($q) use ($user) {
                $q->where('type_id', 'created')
                  ->where('parent_id', $user->personalSpan->id);
            });
        } elseif ($photosFilter === 'public' && $user && $user->personalSpan) {
            // Show photos NOT created by current user (but still accessible)
            $query->whereDoesntHave('connectionsAsObject', function ($q) use ($user) {
                $q->where('type_id', 'created')
                  ->where('parent_id', $user->personalSpan->id);
            });
        }
        // If 'all', use the access control filters already applied above

        // Apply search filter: photo title or names of spans the photo features or is located in
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('connectionsAsSubject', function ($connQ) use ($search) {
                        $connQ->whereIn('type_id', ['features', 'located'])
                            ->whereHas('child', function ($childQ) use ($search) {
                                $childQ->where('name', 'ilike', "%{$search}%");
                            });
                    });
            });
        }

        // Apply access level filter
        if ($request->filled('access_level')) {
            $query->where('access_level', $request->access_level);
        }

        // Apply state filter
        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        // Apply "of" filter (photos featuring a specific span – connection type "features" only)
        if ($request->filled('features')) {
            $featuresSpanId = $request->features;
            $query->whereHas('connectionsAsSubject', function ($q) use ($featuresSpanId) {
                $q->where('type_id', 'features')
                  ->where('child_id', $featuresSpanId);
            });
        }

        // Apply "in" filter (photos located in a place – connection type "located" only)
        if ($request->filled('location')) {
            $locationSpanId = $request->location;
            $query->whereHas('connectionsAsSubject', function ($q) use ($locationSpanId) {
                $q->where('type_id', 'located')
                  ->where('child_id', $locationSpanId);
            });
        }

        // Apply from_date / to_date filter (YYYY or YYYY-MM or YYYY-MM-DD)
        if ($request->filled('from_date') && $request->filled('to_date')) {
            // Date range: photo start date within [from_date start of period, to_date end of period]
            $fromStart = $this->parseDateStartOfPeriod($request->from_date);
            $toEnd = $this->parseDateEndOfPeriod($request->to_date);
            if ($fromStart && $toEnd) {
                $query->whereRaw(
                    '(start_year, COALESCE(start_month, 1), COALESCE(start_day, 1)) >= (?, ?, ?)',
                    [$fromStart->year, $fromStart->month, $fromStart->day]
                )->whereRaw(
                    '(start_year, COALESCE(start_month, 1), COALESCE(start_day, 1)) <= (?, ?, ?)',
                    [$toEnd->year, $toEnd->month, $toEnd->day]
                );
            }
        } elseif ($request->filled('from_date')) {
            // Single from_date: exact match (photos starting on that date)
            $fromDate = $request->from_date;
            $parts = explode('-', $fromDate);
            $query->where('start_year', (int) $parts[0]);
            if (isset($parts[1])) {
                $query->where('start_month', (int) $parts[1]);
            }
            if (isset($parts[2])) {
                $query->where('start_day', (int) $parts[2]);
            }
        }

        $photos = $query
            ->with(['connectionsAsSubject' => function ($q) {
                $q->whereHas('type', function ($t) {
                    $t->whereIn('type', ['features', 'located']);
                })->with('child');
            }])
            ->with(['connectionsAsObject' => function ($q) {
                $q->where('type_id', 'created')->with('parent');
            }])
            ->orderBy('start_year', 'desc')
            ->orderBy('start_month', 'desc')
            ->orderBy('start_day', 'desc')
            ->orderBy('name') // Secondary sort by name for photos without dates
            ->paginate(self::PHOTOS_PER_PAGE);

        // Determine if user can see my photos tab
        $showMyPhotosTab = $user && $user->personalSpan;

        // Return partial HTML for infinite scroll AJAX requests
        if ($request->boolean('partial') && $request->ajax()) {
            $loadedCount = $photos->currentPage() * self::PHOTOS_PER_PAGE;
            $hitMax = $loadedCount >= self::INFINITE_SCROLL_MAX;
            $paginatorHasMore = $photos->hasMorePages();
            $hasMore = $paginatorHasMore && !$hitMax;
            $nextPageUrl = $paginatorHasMore
                ? $photos->appends($request->query())->nextPageUrl() . '&partial=1'
                : null;

            $html = view('photos.partials.photo-cards', ['photos' => $photos])->render();

            return response()->json([
                'html' => $html,
                'hasMorePages' => $hasMore,
                'nextPageUrl' => $nextPageUrl,
                'hitMax' => $hitMax,
                'total' => $photos->total(),
            ]);
        }

        // Resolve filter spans for view (breadcrumb, clear buttons)
        $filterOfSpan = $request->filled('features')
            ? Span::find($request->features)
            : null;
        $filterInSpan = $request->filled('location')
            ? Span::find($request->location)
            : null;

        return view('photos.index', compact('photos', 'showMyPhotosTab', 'photosFilter', 'filterOfSpan', 'filterInSpan'));
    }

    /**
     * Display photos in a date range (pretty URL: /photos/from/:from/to/:to).
     * Dates may be YYYY, YYYY-MM, or YYYY-MM-DD.
     */
    public function indexFromTo(string $fromDate, string $toDate): View|JsonResponse
    {
        request()->merge([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return $this->index(request());
    }

    /**
     * Display photos from a specific date (pretty URL: /photos/from/:date).
     * Date may be YYYY, YYYY-MM, or YYYY-MM-DD.
     */
    public function indexFrom(string $date): View|JsonResponse
    {
        request()->merge(['from_date' => $date]);

        return $this->index(request());
    }

    /**
     * Display photos during a span's date range (pretty URL: /photos/during/:slug).
     * Uses the span's start date as from_date and end date as to_date (if present).
     * If the span doesn't exist, shows empty results instead of 404.
     */
    public function indexDuring(string $slug): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();

        // If span doesn't exist, just show empty results
        if (!$span) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        if (!$span->start_year) {
            return redirect()->route('photos.index')
                ->with('status', 'That span has no start date, so "during" filtering is not possible.');
        }

        $merge = ['from_date' => $span->start_date_link];
        $merge['to_date'] = $span->end_year
            ? $span->end_date_link
            : Carbon::today()->format('Y-m-d');
        request()->merge($merge);

        return $this->index(request());
    }

    /**
     * Display photos featuring the given span in a date range (pretty URL: /photos/of/:slug/from/:from/to/:to).
     * Dates may be YYYY, YYYY-MM, or YYYY-MM-DD.
     * If the span doesn't exist, shows empty results instead of 404.
     */
    public function indexOfFromTo(string $slug, string $fromDate, string $toDate): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();

        // If span doesn't exist, just show empty results
        if (!$span) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        request()->merge([
            'features' => $span->id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return $this->index(request());
    }

    /**
     * Display photos featuring the given span from a specific date (pretty URL: /photos/of/:slug/from/:date).
     * Date may be YYYY, YYYY-MM, or YYYY-MM-DD.
     * If the span doesn't exist, shows empty results instead of 404.
     */
    public function indexOfFrom(string $slug, string $date): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();

        // If span doesn't exist, just show empty results
        if (!$span) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        request()->merge([
            'features' => $span->id,
            'from_date' => $date,
        ]);

        return $this->index(request());
    }

    /**
     * Display photos featuring the given span (pretty URL: /photos/of/:slug).
     * If the span doesn't exist, shows empty results instead of 404.
     */
    public function indexOf(string $slug): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();

        // If span doesn't exist, just show empty results
        if (!$span) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        request()->merge(['features' => $span->id]);

        return $this->index(request());
    }

    /**
     * Display photos located in the given span (pretty URL: /photos/in/:slug).
     * If the span doesn't exist, shows empty results instead of 404.
     */
    public function indexIn(string $slug): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();

        if (!$span) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        request()->merge(['location' => $span->id]);

        return $this->index(request());
    }

    /**
     * Display photos featuring one span and located in another (pretty URL: /photos/of/:slug/in/:locationSlug).
     * If either span doesn't exist, shows empty results instead of 404.
     */
    public function indexOfIn(string $slug, string $locationSlug): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();
        $locationSpan = Span::where('slug', $locationSlug)->first();

        if (!$span || !$locationSpan) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        request()->merge([
            'features' => $span->id,
            'location' => $locationSpan->id,
        ]);

        return $this->index(request());
    }

    /**
     * Display photos featuring the given span during another span's date range
     * (pretty URL: /photos/of/:slug/during/:duringSlug).
     * Combines the "features" filter with the "during" span's start/end dates.
     * If either span doesn't exist, shows empty results instead of 404.
     */
    public function indexOfDuring(string $slug, string $duringSlug): View|JsonResponse
    {
        $span = Span::where('slug', $slug)->first();
        $duringSpan = Span::where('slug', $duringSlug)->first();

        // If either span doesn't exist, just show empty results
        if (!$span || !$duringSpan) {
            request()->merge(['_no_results' => true]);
            return $this->index(request());
        }

        if (!$duringSpan->start_year) {
            return redirect()->route('photos.of', $span->slug)
                ->with('status', 'That span has no start date, so "during" filtering is not possible.');
        }

        $merge = [
            'features' => $span->id,
            'from_date' => $duringSpan->start_date_link,
        ];
        $merge['to_date'] = $duringSpan->end_year
            ? $duringSpan->end_date_link
            : Carbon::today()->format('Y-m-d');
        request()->merge($merge);

        return $this->index(request());
    }

    /**
     * Display the specified photo span.
     */
    public function show(Span $photo): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Use dedicated photo view
        return view('photos.show', compact('photo'));
    }

    /**
     * Show the form for editing the specified photo span.
     */
    public function edit(Span $photo): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can edit the photo
        if (!$photo->isEditableBy(auth()->user())) {
            abort(403, 'You do not have permission to edit this photo.');
        }

        // Use dedicated photo edit view
        return view('photos.edit', compact('photo'));
    }

    /**
     * Update the specified photo span.
     */
    public function update(Request $request, Span $photo)
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can edit the photo
        if (!$photo->isEditableBy(auth()->user())) {
            abort(403, 'You do not have permission to edit this photo.');
        }

        // Delegate to SpanController for the actual update logic
        $spanController = new SpanController();
        $result = $spanController->update($request, $photo);

        // If it's a redirect response, redirect to the photo route instead of span route
        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return redirect()->route('photos.show', $photo);
        }

        return $result;
    }

    /**
     * Remove the specified photo span.
     */
    public function destroy(Span $photo)
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can delete the photo
        if (!$photo->isEditableBy(auth()->user())) {
            abort(403, 'You do not have permission to delete this photo.');
        }

        // Delegate to SpanController for the actual deletion logic
        $spanController = new SpanController();
        $result = $spanController->destroy($photo);

        // If it's a redirect response, redirect to the photos index instead of spans index
        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            return redirect()->route('photos.index')->with('status', 'Photo deleted successfully');
        }

        return $result;
    }

    /**
     * Show photo connections.
     */
    public function connections(Span $photo, string $predicate = null): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual connections logic
        $spanController = new SpanController();
        
        if ($predicate) {
            return $spanController->listConnections($photo, $predicate);
        } else {
            return $spanController->allConnections($photo);
        }
    }


    /**
     * Show photo story.
     */
    public function story(Span $photo): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Use dedicated photo story view
        $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($photo);

        return view('photos.story', compact('photo', 'story'));
    }

    /**
     * Show photo comparison.
     */
    public function compare(Span $photo): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual comparison logic
        $spanController = new SpanController();
        return $spanController->compare($photo);
    }

    /**
     * Show all connections for a photo.
     */
    public function allConnections(Span $photo): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual connections logic
        $spanController = new SpanController();
        return $spanController->allConnections($photo);
    }

    /**
     * Show connections of a specific type for a photo.
     */
    public function listConnections(Span $photo, string $predicate): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual connections logic
        $spanController = new SpanController();
        return $spanController->listConnections($photo, $predicate);
    }

    /**
     * Show a specific connection for a photo.
     */
    public function showConnection(Span $photo, string $predicate, Span $object): View
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual connection logic
        $spanController = new SpanController();
        return $spanController->showConnection($photo, $predicate, $object);
    }

    /**
     * Show photo at a specific date.
     */
    public function showAtDate(Request $request, Span $photo, string $date): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        // Ensure this is actually a photo span
        if ($photo->type_id !== 'thing' || ($photo->metadata['subtype'] ?? null) !== 'photo') {
            abort(404, 'Photo not found');
        }

        // Check if user can access the photo
        if (!$photo->isAccessibleBy(auth()->user())) {
            abort(403, 'You do not have permission to view this photo.');
        }

        // Delegate to SpanController for the actual time travel logic
        $spanController = new SpanController();
        return $spanController->showAtDate($request, $photo, $date);
    }

    /**
     * Parse a date string (YYYY, YYYY-MM, or YYYY-MM-DD) to the start of that period.
     */
    private function parseDateStartOfPeriod(string $date): ?Carbon
    {
        try {
            $parts = explode('-', $date);
            $year = (int) $parts[0];
            $month = isset($parts[1]) ? (int) $parts[1] : 1;
            $day = isset($parts[2]) ? (int) $parts[2] : 1;

            return Carbon::createFromDate($year, $month, $day)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse a date string (YYYY, YYYY-MM, or YYYY-MM-DD) to the end of that period.
     */
    private function parseDateEndOfPeriod(string $date): ?Carbon
    {
        try {
            $parts = explode('-', $date);
            $year = (int) $parts[0];
            if (!isset($parts[1])) {
                return Carbon::createFromDate($year, 12, 31)->endOfDay();
            }
            $month = (int) $parts[1];
            if (!isset($parts[2])) {
                return Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
            }
            $day = (int) $parts[2];

            return Carbon::createFromDate($year, $month, $day)->endOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }
}

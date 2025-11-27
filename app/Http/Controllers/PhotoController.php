<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhotoController extends Controller
{
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
    public function index(Request $request): View
    {
        $query = Span::where('type_id', 'thing')
            // Use direct JSON equality; subtype is stored as a string, not an array
            ->where('metadata->subtype', 'photo');

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
        // Default to "my" if user is authenticated, otherwise "public"
        $photosFilter = $request->filled('photos_filter') ? $request->photos_filter : ($user ? 'my' : 'public');
        
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

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Apply access level filter
        if ($request->filled('access_level')) {
            $query->where('access_level', $request->access_level);
        }

        // Apply state filter
        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        // Apply features filter (photos featuring a specific person/span)
        if ($request->filled('features')) {
            $featuresSpanId = $request->features;
            $query->whereHas('connectionsAsSubject', function ($q) use ($featuresSpanId) {
                $q->where('type_id', 'features')
                  ->where('child_id', $featuresSpanId);
            });
        }

        $photos = $query
            ->with(['connectionsAsSubject' => function ($q) {
                $q->whereHas('type', function ($t) {
                    $t->where('type', 'features');
                })->with('child');
            }])
            ->with(['connectionsAsObject' => function ($q) {
                $q->where('type_id', 'created')->with('parent');
            }])
            ->orderBy('start_year', 'desc')
            ->orderBy('start_month', 'desc')
            ->orderBy('start_day', 'desc')
            ->orderBy('name') // Secondary sort by name for photos without dates
            ->paginate(24);

        // Determine if user can see my photos tab
        $showMyPhotosTab = $user && $user->personalSpan;

        return view('photos.index', compact('photos', 'showMyPhotosTab', 'photosFilter'));
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
}

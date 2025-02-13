<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Ray;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // Require auth for all routes except show and index
        $this->middleware('auth')->except(['show', 'index']);
    }

    /**
     * Display a listing of spans.
     */
    public function index(Request $request): View|Response
    {
        $query = Span::query();

        if (Auth::check()) {
            $user = Auth::user();
            
            // Admin can see all spans
            if ($user->is_admin) {
                $spans = $query->paginate(20);
                return view('spans.index', compact('spans'));
            }

            // Regular users can see:
            // 1. Public spans
            // 2. Their own spans
            // 3. Shared spans they have permission for
            $query->where(function ($query) use ($user) {
                $query->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhere(function ($query) use ($user) {
                        $query->where('access_level', 'shared')
                            ->whereHas('permissions', function ($query) use ($user) {
                                $query->where('user_id', $user->id);
                            });
                    });
            });
        } else {
            // Unauthenticated users can only see public spans
            $query->where('access_level', 'public');
        }

        $spans = $query->paginate(20);
        return view('spans.index', compact('spans'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Span::class);
        return view('spans.create');
    }

    /**
     * Store a newly created span.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Span::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'required|string|exists:span_types,type_id',
            'start_year' => 'required|integer',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        $span = new Span($validated);
        $span->owner_id = $user->id;
        $span->updater_id = $user->id;
        $span->access_level = 'private'; // Default to private
        $span->save();

        return redirect()->route('spans.show', $span);
    }

    /**
     * Display the specified span.
     */
    public function show(Request $request, Span $span): View|\Illuminate\Http\RedirectResponse
    {
        // Basic debug info
        ray('=== Span Debug Info ===');
        
        // Log the span model
        ray($span->toArray());
        
        // If we're accessing via UUID and a slug exists, redirect to the slug URL
        $routeParam = $request->segment(2); // Get the actual URL segment
        
        // Route info
        ray([
            'route_param' => $routeParam,
            'is_uuid' => Str::isUuid($routeParam),
            'slug' => $span->slug,
            'span_id' => $span->id
        ]);
        
        if (Str::isUuid($routeParam) && $span->slug) {
            ray('Redirecting to slug URL', [
                'from' => $routeParam,
                'to' => $span->slug
            ]);
            return redirect()->route('spans.show', ['span' => $span->slug], 301);
        }

        return view('spans.show', compact('span'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Span $span)
    {
        $this->authorize('update', $span);
        return view('spans.edit', compact('span'));
    }

    /**
     * Update the specified span.
     */
    public function update(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type_id' => 'sometimes|required|string|exists:span_types,type_id',
            'start_year' => 'sometimes|required|integer',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
            'metadata' => 'nullable|array',
        ]);

        $span->updater_id = Auth::id();
        $span->update($validated);

        return redirect()->route('spans.show', $span);
    }

    /**
     * Remove the specified span.
     */
    public function destroy(Span $span)
    {
        $this->authorize('delete', $span);
        $span->delete();
        return redirect()->route('spans.index');
    }
}
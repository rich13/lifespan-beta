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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\SpanType;
use App\Models\ConnectionType;

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
        $query = Span::query()
            ->where('type_id', '!=', 'connection')  // Exclude connection spans
            ->orderByRaw('COALESCE(start_year, 9999)')  // Order by start_year, putting nulls last
            ->orderByRaw('COALESCE(start_month, 12)')   // Then by month
            ->orderByRaw('COALESCE(start_day, 31)');    // Then by day

        // Apply type filters if provided
        if ($request->has('types') && !empty($request->types)) {
            $query->whereIn('type_id', $request->types);
        }
        
        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $searchTerms = preg_split('/\s+/', strtolower(trim($request->search)));
            
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $term = '%' . $term . '%';
                    $q->where(function($subQuery) use ($term) {
                        $subQuery->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
                    });
                }
            });
        }

        // For unauthenticated users, only show public spans
        if (!Auth::check()) {
            $query->where('access_level', 'public');
        } else {
            // For authenticated users
            $user = Auth::user();
            if (!$user->is_admin) {
                // Show:
                // 1. Public spans
                // 2. User's own spans
                // 3. Shared spans where user has permission
                $query->where(function ($query) use ($user) {
                    $query->where('access_level', 'public')
                        ->orWhere('owner_id', $user->id)
                        ->orWhere(function ($query) use ($user) {
                            $query->where('access_level', 'shared')
                                ->whereExists(function ($subquery) use ($user) {
                                    $subquery->select('id')
                                        ->from('span_permissions')
                                        ->whereColumn('span_permissions.span_id', 'spans.id')
                                        ->where('span_permissions.user_id', $user->id);
                                });
                        });
                });
            }
        }

        $spans = $query->paginate(50);

        // For debugging
        ray('=== Span Index Debug ===');
        ray([
            'is_authenticated' => Auth::check(),
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings(),
            'spans_count' => $spans->count(),
            'spans' => $spans->items()
        ]);

        return view('spans.index', compact('spans'));
    }

    /**
     * Show the form for creating a new span.
     */
    public function create()
    {
        $this->authorize('create', Span::class);
        
        $user = Auth::user();
        $spanTypes = DB::table('span_types')->get();
        
        return view('spans.create', compact('user', 'spanTypes'));
    }

    /**
     * Store a newly created span.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Span::class);
        $validated = $request->validate([
            'id' => 'nullable|uuid|unique:spans,id',  // Allow UUID to be provided
            'name' => 'required|string|max:255',
            'type_id' => 'required|string|exists:span_types,type_id',
            'state' => 'required|in:draft,placeholder,complete',
            'start_year' => 'required_unless:state,placeholder|nullable|integer',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
            'metadata' => 'nullable|array',
        ]);

        $user = Auth::user();
        
        $span = new Span($validated);
        if ($request->has('id')) {
            $span->id = $request->id;
        }
        $span->owner_id = $user->id;
        $span->updater_id = $user->id;
        $span->access_level = 'private'; // Default to private
        $span->save();

        // If this is a programmatic call (e.g. from ImportService), return JSON response
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($span);
        }

        // Otherwise return the redirect for web requests
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
            return redirect()
                ->route('spans.show', ['span' => $span->slug], 301)
                ->with('status', session('status')); // Preserve flash message
        }

        // Check if the span is private and the user is not authenticated
        if ($span->access_level !== 'public' && !Auth::check()) {
            return redirect()->route('login');
        }

        return view('spans.show', compact('span'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Span $span)
    {
        $this->authorize('update', $span);

        $spanTypes = SpanType::all();
        $connectionTypes = ConnectionType::orderBy('forward_predicate')->get();
        $availableSpans = Span::where('id', '!=', $span->id)
            ->with('type')
            ->orderBy('name')
            ->get();

        return view('spans.edit', compact('span', 'spanTypes', 'connectionTypes', 'availableSpans'));
    }

    /**
     * Update the specified span.
     */
    public function update(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        try {
            // Log the incoming request data
            Log::channel('spans')->info('Updating span', [
                'span_id' => $span->id,
                'input' => $request->all()
            ]);
            
            // Custom validation for date patterns
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
                'state' => 'required|in:draft,placeholder,complete',
                'start_year' => 'required_unless:state,placeholder|nullable|integer',
                'start_month' => 'nullable|integer|between:1,12',
                'start_day' => 'nullable|integer|between:1,31',
                'end_year' => 'nullable|integer',
                'end_month' => 'nullable|integer|between:1,12',
                'end_day' => 'nullable|integer|between:1,31',
                'metadata' => 'nullable|array',
                'metadata.*' => 'nullable',
                'sources' => 'nullable|array',
                'sources.*' => 'nullable|url',
            ]);

            $validator->after(function ($validator) use ($request) {
                // Validate start date pattern
                if ($request->start_day && !$request->start_month) {
                    $validator->errors()->add('start_day', 'Cannot specify day without month');
                }
                if ($request->start_month && !$request->start_year) {
                    $validator->errors()->add('start_month', 'Cannot specify month without year');
                }

                // Validate end date pattern
                if ($request->end_day && !$request->end_month) {
                    $validator->errors()->add('end_day', 'Cannot specify day without month');
                }
                if ($request->end_month && !$request->end_year) {
                    $validator->errors()->add('end_month', 'Cannot specify month without year');
                }
            });

            if ($validator->fails()) {
                return back()
                    ->withInput()
                    ->withErrors($validator);
            }

            $validated = $validator->validated();

            // Infer precision levels
            $span->start_precision = $span->inferPrecisionLevel(
                $validated['start_year'] ?? null,
                $validated['start_month'] ?? null,
                $validated['start_day'] ?? null
            );

            // Only infer end precision if end_year is present in the validated data
            if (isset($validated['end_year'])) {
                $span->end_precision = $span->inferPrecisionLevel(
                    $validated['end_year'],
                    $validated['end_month'] ?? null,
                    $validated['end_day'] ?? null
                );
            }

            // Log the validated data
            Log::channel('spans')->info('Validated span data', [
                'span_id' => $span->id,
                'validated' => $validated
            ]);

            // Update the span
            $span->updater_id = Auth::id();
            $span->update($validated);

            // Log the successful update
            Log::channel('spans')->info('Span updated successfully', [
                'span_id' => $span->id,
                'changes' => $span->getChanges()
            ]);

            // If this is a programmatic call (e.g. from ImportService), return JSON response
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json($span);
            }

            return redirect()->route('spans.show', $span)
                ->with('status', 'Span updated successfully');

        } catch (\Exception $e) {
            // Log any errors that occur
            Log::channel('spans')->error('Error updating span', [
                'span_id' => $span->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while saving the span. Please try again.']);
        }
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
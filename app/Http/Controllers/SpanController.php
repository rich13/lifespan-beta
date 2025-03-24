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
use App\Services\Comparison\SpanComparisonService;
use InvalidArgumentException;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    protected $comparisonService;

    /**
     * Create a new controller instance.
     */
    public function __construct(SpanComparisonService $comparisonService)
    {
        // Require auth for all routes except show and index
        $this->middleware('auth')->except(['show', 'index']);
        $this->comparisonService = $comparisonService;
    }

    /**
     * Display a listing of spans.
     */
    public function index(Request $request): View|Response
    {
        $query = Span::query()
            ->whereNot('type_id', 'connection')
            ->orderByRaw('COALESCE(start_year, 9999)')  // Order by start_year, putting nulls last
            ->orderByRaw('COALESCE(start_month, 12)')   // Then by month
            ->orderByRaw('COALESCE(start_day, 31)');    // Then by day

        // Handle type filtering
        if ($request->has('types')) {
            $types = is_array($request->types) ? $request->types : explode(',', $request->types);
            $query->whereIn('type_id', $types);

            // Handle subtype filtering
            foreach ($types as $typeId) {
                $subtypeParam = $request->input($typeId . '_subtype');
                if ($subtypeParam) {
                    $subtypes = explode(',', $subtypeParam);
                    $query->where(function($q) use ($typeId, $subtypes) {
                        foreach ($subtypes as $subtype) {
                            $q->orWhereJsonContains('metadata->subtype', $subtype);
                        }
                    });
                }
            }
        }

        // Handle search
        if ($request->has('search')) {
            $searchTerms = preg_split('/\s+/', trim($request->search));
            $query->where(function($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->where(function($subq) use ($term) {
                        $subq->where('name', 'ilike', "%{$term}%")
                             ->orWhere('description', 'ilike', "%{$term}%");
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

        $spans = $query->paginate(20);

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
     * Show the full-page comparison view for a span
     */
    public function compare(Span $span)
    {
        if ($span->is_private && !auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $personalSpan = $user->personalSpan;

        if (!$personalSpan) {
            return redirect()->back()->with('error', 'Please set your personal span first.');
        }

        if ($personalSpan->id === $span->id) {
            return redirect()->back()->with('error', 'Cannot compare a span with itself.');
        }

        try {
            // Get all comparisons from the service
            $comparisons = $this->comparisonService->compare($personalSpan, $span);
            $yearRange = $this->comparisonService->getComparisonYearRange($personalSpan, $span);
            
            // Group comparisons by type for the view
            $groupedComparisons = $comparisons->groupBy('type')->map(function($group) {
                return $group->sortBy('year');
            });
            
            return view('spans.compare', [
                'span' => $span,
                'personalSpan' => $personalSpan,
                'comparisons' => $comparisons,
                'groupedComparisons' => $groupedComparisons,
                'minYear' => $yearRange['min'],
                'maxYear' => $yearRange['max']
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
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
        
        // Get the current span type, or if type_id is provided in the query, get that type
        $spanType = request('type_id') 
            ? SpanType::where('type_id', request('type_id'))->firstOrFail() 
            : $span->type;

        return view('spans.edit', compact('span', 'spanTypes', 'connectionTypes', 'availableSpans', 'spanType'));
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
                'input' => $request->all(),
                'current_type' => $span->type_id,
                'requested_type' => $request->type_id,
                'has_type_change' => $request->has('type_id') && $request->type_id !== $span->type_id
            ]);
            
            // Custom validation for date patterns
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'type_id' => 'sometimes|required|string|exists:span_types,type_id',
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

            if ($validator->fails()) {
                Log::channel('spans')->error('Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();

            // Handle type transition if type is changing
            if ($request->has('type_id') && $request->type_id !== $span->type_id) {
                Log::channel('spans')->info('Starting type transition', [
                    'span_id' => $span->id,
                    'old_type' => $span->type_id,
                    'new_type' => $request->type_id
                ]);

                $result = $span->transitionToType($request->type_id, $request->metadata);
                
                Log::channel('spans')->info('Type transition result', [
                    'success' => $result['success'],
                    'messages' => $result['messages'],
                    'warnings' => $result['warnings']
                ]);
                
                if (!$result['success']) {
                    return back()
                        ->withErrors(['type_id' => $result['messages']])
                        ->withInput();
                }
                
                // If there are warnings about lost fields, show them to the user
                if (!empty($result['warnings'])) {
                    session()->flash('warnings', $result['warnings']);
                }
                
                // Update other fields
                $span->fill($request->except(['type_id', 'metadata']));
                $span->save();
                
                Log::channel('spans')->info('Span type transition completed', [
                    'span_id' => $span->id,
                    'old_type' => $span->type_id,
                    'new_type' => $request->type_id,
                    'warnings' => $result['warnings']
                ]);
                
                return redirect()->route('spans.edit', $span)
                    ->with('status', $result['messages'][0]);
            }

            // If this is a connection span and the connection type is being updated
            if ($span->type_id === 'connection') {
                // Find the associated connection
                $connection = DB::table('connections')
                    ->join('connection_types', 'connections.type_id', '=', 'connection_types.type')
                    ->join('spans as subject', 'connections.parent_id', '=', 'subject.id')
                    ->join('spans as object', 'connections.child_id', '=', 'object.id')
                    ->where('connections.connection_span_id', $span->id)
                    ->select([
                        'connections.id',
                        'subject.name as subject_name',
                        'connection_types.forward_predicate',
                        'object.name as object_name'
                    ])
                    ->first();

                if ($connection) {
                    // Update the connection's type if it's being changed
                    if (isset($validated['metadata']['connection_type'])) {
                        DB::table('connections')
                            ->where('id', $connection->id)
                            ->update(['type_id' => $validated['metadata']['connection_type']]);
                    }

                    // Update the span name in SPO format
                    $newName = "{$connection->subject_name} {$connection->forward_predicate} {$connection->object_name}";
                    $validated['name'] = $newName;
                }
            }

            // Regular update without type change
            $span->update($validated);

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
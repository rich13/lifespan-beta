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
use App\Services\YamlSpanService;
use InvalidArgumentException;
use App\Models\Connection;
use App\Models\ConnectionType as ConnectionTypeModel;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    protected $yamlService;

    /**
     * Create a new controller instance.
     */
    public function __construct(YamlSpanService $yamlService)
    {
        // Require auth for all routes except show, index, and search
        $this->middleware('auth')->except(['show', 'index', 'search']);
        $this->yamlService = $yamlService;
    }



    /**
     * Display a listing of spans.
     */
    public function index(Request $request): View|Response
    {
        try {
            $query = Span::query()
                ->whereNot('type_id', 'connection')
                ->orderByRaw('COALESCE(start_year, 9999)')  // Order by start_year, putting nulls last
                ->orderByRaw('COALESCE(start_month, 12)')   // Then by month
                ->orderByRaw('COALESCE(start_day, 31)');    // Then by day

            // Basic debug info - only if we're in development
            if (app()->environment('local', 'development')) {
                try {
                    \Illuminate\Support\Facades\Log::info('Span Index Query', [
                        'is_authenticated' => Auth::check(),
                        'request_data' => $request->all()
                    ]);
                } catch (\Exception $loggingError) {
                    // Silently ignore logging errors in development
                }
            }

            // Handle type filtering
            if ($request->has('types')) {
                $types = is_array($request->types) ? $request->types : explode(',', $request->types);
                $query->whereIn('type_id', $types);

                // Handle person category filtering
                if (in_array('person', $types) && $request->has('person_category') && Auth::check()) {
                    $personCategories = is_array($request->person_category) ? $request->person_category : explode(',', $request->person_category);
                    
                    if (!empty($personCategories)) {
                        $userSpan = Auth::user()->personalSpan;
                        if ($userSpan) {
                            $relationshipService = app(\App\Services\PersonRelationshipService::class);
                            
                            // Get all people in the requested categories
                            $categoryPeople = collect();
                            foreach ($personCategories as $category) {
                                $people = $relationshipService->getPeopleByCategory($category, $userSpan);
                                $categoryPeople = $categoryPeople->merge($people);
                            }
                            
                            // Filter to only show people in the selected categories
                            if ($categoryPeople->isNotEmpty()) {
                                $query->whereIn('id', $categoryPeople->pluck('id'));
                            } else {
                                // If no people found in categories, return empty result
                                $query->whereRaw('1 = 0');
                            }
                        }
                    }
                }

                // Handle subtype filtering for non-person types
                foreach ($types as $typeId) {
                    if ($typeId !== 'person') {
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

            // Log the query for debugging
            \Illuminate\Support\Facades\Log::info('Span Index Results', [
                'is_authenticated' => Auth::check(),
                'query_sql' => $query->toSql(),
                'query_bindings' => $query->getBindings(),
                'spans_count' => $spans->count()
            ]);

            return view('spans.index', compact('spans'));
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error in spans index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return error page
            if (app()->environment('production')) {
                return response()->view('errors.500', [], 500);
            } else {
                return response()->view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        }
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
        // Get validation rules with special handling for timeless span types
        $timelessSpanTypes = ['place', 'role'];
        $typeId = $request->input('type_id');
        $state = $request->input('state', 'draft');
        
        $startYearRule = 'nullable|integer';
        if (!in_array($typeId, $timelessSpanTypes) && $state !== 'placeholder') {
            $startYearRule = 'required|integer';
        }

        $validated = $request->validate([
            'id' => 'nullable|uuid|unique:spans,id',  // Allow UUID to be provided
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:spans,slug',
            'type_id' => 'required|string|exists:span_types,type_id',
            'state' => 'required|in:draft,placeholder,complete',
            'start_year' => $startYearRule,
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
        try {
            // Basic debug info
            \Illuminate\Support\Facades\Log::info('Span Show Request', [
                'route_param' => $request->segment(2),
                'span_id' => $span->id,
                'span_type' => $span->type_id,
                'is_uuid' => Str::isUuid($request->segment(2)),
                'slug' => $span->slug
            ]);
            
            // If we're accessing via UUID and a slug exists, redirect to the slug URL
            $routeParam = $request->segment(2); // Get the actual URL segment
            
            if (Str::isUuid($routeParam) && $span->slug) {
                \Illuminate\Support\Facades\Log::info('Redirecting to slug URL', [
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
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error in spans show', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'route_param' => $request->segment(2)
            ]);
            
            // Return error page
            if (app()->environment('production')) {
                return response()->view('errors.500', [], 500);
            } else {
                return response()->view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        }
    }

    /**
     * Show the new comparison page
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

        // Show the new comparison page directly
        return view('spans.compare', [
            'span' => $span,
            'personalSpan' => $personalSpan
        ]);
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
            
            // Get validation rules with special handling for timeless span types
            $timelessSpanTypes = ['place', 'role'];
            $typeId = $request->input('type_id', $span->type_id);
            $state = $request->input('state', $span->state);
            
            $startYearRule = 'nullable|integer';
            if (!in_array($typeId, $timelessSpanTypes) && $state !== 'placeholder') {
                $startYearRule = 'required|integer';
            }

            // Custom validation for date patterns
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'nullable|string|max:255|regex:/^[a-z0-9-]+$/|unique:spans,slug,' . $span->id,
                'type_id' => 'sometimes|required|string|exists:span_types,type_id',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
                'state' => 'required|in:draft,placeholder,complete',
                'start_year' => $startYearRule,
                'start_month' => 'nullable|integer|between:1,12',
                'start_day' => 'nullable|integer|between:1,31',
                'end_year' => 'nullable|integer',
                'end_month' => 'nullable|integer|between:1,12',
                'end_day' => 'nullable|integer|between:1,31',
                'metadata' => 'nullable|array',
                'metadata.*' => 'nullable',
                'sources' => 'nullable|array',
                'sources.*' => 'nullable|url',
                'subject_id' => 'required_if:type_id,connection|exists:spans,id',
                'object_id' => 'required_if:type_id,connection|exists:spans,id|different:subject_id',
                'connection_type' => 'required_if:type_id,connection|exists:connection_types,type'
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

            // If connection fields are provided, update the connection
            if ($request->has(['subject_id', 'object_id', 'connection_type'])) {
                // Get the connection where this span is the connection span
                $connection = Connection::where('connection_span_id', $span->id)->first();
                if ($connection) {
                    // Update the connection
                    $connection->update([
                        'parent_id' => $validated['subject_id'],
                        'child_id' => $validated['object_id'],
                        'type_id' => $validated['connection_type']
                    ]);

                    // Get the updated connection type and spans
                    $connectionType = $connection->type;
                    $subject = $connection->subject;
                    $object = $connection->object;

                    // Update the span name in SPO format
                    $validated['name'] = "{$subject->name} {$connectionType->forward_predicate} {$object->name}";
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

    /**
     * Explore spans that start or end on a specific date.
     */
    public function exploreDate(string $date): View
    {
        // Parse the date string into components
        $dateParts = explode('-', $date);
        $year = (int) $dateParts[0];
        $month = (int) $dateParts[1];
        $day = (int) $dateParts[2];

        // Build the base query for spans that start or end on this date
        $query = Span::query()
            ->where(function ($query) use ($year, $month, $day) {
                // Spans that start on this date
                $query->where(function ($q) use ($year, $month, $day) {
                    $q->where('start_year', $year)
                      ->where('start_month', $month)
                      ->where('start_day', $day);
                })
                // Spans that end on this date
                ->orWhere(function ($q) use ($year, $month, $day) {
                    $q->where('end_year', $year)
                      ->where('end_month', $month)
                      ->where('end_day', $day);
                })
                // Spans that start in this month
                ->orWhere(function ($q) use ($year, $month) {
                    $q->where('start_year', $year)
                      ->where('start_month', $month);
                })
                // Spans that end in this month
                ->orWhere(function ($q) use ($year, $month) {
                    $q->where('end_year', $year)
                      ->where('end_month', $month);
                })
                // Spans that start in this year
                ->orWhere(function ($q) use ($year) {
                    $q->where('start_year', $year);
                })
                // Spans that end in this year
                ->orWhere(function ($q) use ($year) {
                    $q->where('end_year', $year);
                });
            });

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

        // Get all spans and separate them into different lists
        $allSpans = $query->get();
        
        // Spans that start on this exact date
        $spansStartingOnDate = $allSpans->filter(function ($span) use ($year, $month, $day) {
            return $span->start_year == $year && 
                   $span->start_month == $month && 
                   $span->start_day == $day;
        });

        // Spans that end on this exact date
        $spansEndingOnDate = $allSpans->filter(function ($span) use ($year, $month, $day) {
            return $span->end_year == $year && 
                   $span->end_month == $month && 
                   $span->end_day == $day;
        });

        // Spans that start in this month (but not on this day)
        $spansStartingInMonth = $allSpans->filter(function ($span) use ($year, $month, $day) {
            return $span->start_year == $year && 
                   $span->start_month == $month && 
                   $span->start_day != $day;
        });

        // Spans that end in this month (but not on this day)
        $spansEndingInMonth = $allSpans->filter(function ($span) use ($year, $month, $day) {
            return $span->end_year == $year && 
                   $span->end_month == $month && 
                   $span->end_day != $day;
        });

        // Spans that start in this year (but not in this month)
        $spansStartingInYear = $allSpans->filter(function ($span) use ($year, $month) {
            return $span->start_year == $year && 
                   $span->start_month != $month;
        });

        // Spans that end in this year (but not in this month)
        $spansEndingInYear = $allSpans->filter(function ($span) use ($year, $month) {
            return $span->end_year == $year && 
                   $span->end_month != $month;
        });

        // Separate connections from regular spans
        $connectionSpansStartingOnDate = $spansStartingOnDate->filter(fn($span) => $span->type_id === 'connection');
        $connectionSpansEndingOnDate = $spansEndingOnDate->filter(fn($span) => $span->type_id === 'connection');
        $connectionSpansStartingInMonth = $spansStartingInMonth->filter(fn($span) => $span->type_id === 'connection');
        $connectionSpansEndingInMonth = $spansEndingInMonth->filter(fn($span) => $span->type_id === 'connection');
        $connectionSpansStartingInYear = $spansStartingInYear->filter(fn($span) => $span->type_id === 'connection');
        $connectionSpansEndingInYear = $spansEndingInYear->filter(fn($span) => $span->type_id === 'connection');

        // Get the actual Connection models
        $connectionsStartingOnDate = Connection::whereIn('connection_span_id', $connectionSpansStartingOnDate->pluck('id'))->get();
        $connectionsEndingOnDate = Connection::whereIn('connection_span_id', $connectionSpansEndingOnDate->pluck('id'))->get();
        $connectionsStartingInMonth = Connection::whereIn('connection_span_id', $connectionSpansStartingInMonth->pluck('id'))->get();
        $connectionsEndingInMonth = Connection::whereIn('connection_span_id', $connectionSpansEndingInMonth->pluck('id'))->get();
        $connectionsStartingInYear = Connection::whereIn('connection_span_id', $connectionSpansStartingInYear->pluck('id'))->get();
        $connectionsEndingInYear = Connection::whereIn('connection_span_id', $connectionSpansEndingInYear->pluck('id'))->get();

        // Remove connections from regular span collections
        $spansStartingOnDate = $spansStartingOnDate->filter(fn($span) => $span->type_id !== 'connection');
        $spansEndingOnDate = $spansEndingOnDate->filter(fn($span) => $span->type_id !== 'connection');
        $spansStartingInMonth = $spansStartingInMonth->filter(fn($span) => $span->type_id !== 'connection');
        $spansEndingInMonth = $spansEndingInMonth->filter(fn($span) => $span->type_id !== 'connection');
        $spansStartingInYear = $spansStartingInYear->filter(fn($span) => $span->type_id !== 'connection');
        $spansEndingInYear = $spansEndingInYear->filter(fn($span) => $span->type_id !== 'connection');

        // Remove any spans that appear in more specific sections from the year sections
        $spansStartingInYear = $spansStartingInYear->filter(function ($span) use ($spansStartingOnDate, $spansStartingInMonth) {
            return !$spansStartingOnDate->contains('id', $span->id) && 
                   !$spansStartingInMonth->contains('id', $span->id);
        });

        $spansEndingInYear = $spansEndingInYear->filter(function ($span) use ($spansEndingOnDate, $spansEndingInMonth) {
            return !$spansEndingOnDate->contains('id', $span->id) && 
                   !$spansEndingInMonth->contains('id', $span->id);
        });

        // Do the same for connections
        $connectionsStartingInYear = $connectionsStartingInYear->filter(function ($connection) use ($connectionsStartingOnDate, $connectionsStartingInMonth) {
            return !$connectionsStartingOnDate->contains('id', $connection->id) && 
                   !$connectionsStartingInMonth->contains('id', $connection->id);
        });

        $connectionsEndingInYear = $connectionsEndingInYear->filter(function ($connection) use ($connectionsEndingOnDate, $connectionsEndingInMonth) {
            return !$connectionsEndingOnDate->contains('id', $connection->id) && 
                   !$connectionsEndingInMonth->contains('id', $connection->id);
        });

        return view('spans.date-explore', compact(
            'spansStartingOnDate',
            'spansEndingOnDate',
            'spansStartingInMonth',
            'spansEndingInMonth',
            'spansStartingInYear',
            'spansEndingInYear',
            'connectionsStartingOnDate',
            'connectionsEndingOnDate',
            'connectionsStartingInMonth',
            'connectionsEndingInMonth',
            'connectionsStartingInYear',
            'connectionsEndingInYear',
            'date'
        ));
    }

    /**
     * Search for spans (JSON API for autocomplete)
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $type = $request->get('type');
        $user = Auth::user();

        // Start with spans the user can see, excluding connections
        $spans = Span::query()->whereNot('type_id', 'connection');
        
        if ($user) {
            // Authenticated user - can see public, owned, and shared spans
            $spans->where(function ($q) use ($user) {
                $q->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('access_level', 'shared')
                            ->whereHas('permissions', function ($q) use ($user) {
                                $q->where('user_id', $user->id);
                            });
                    });
            });
        } else {
            // Unauthenticated user - can only see public spans
            $spans->where('access_level', 'public');
        }

        // Add type restriction if specified
        if ($type) {
            $spans->where('type_id', $type);
        }
        
        // Support multiple types (comma-separated)
        $types = $request->get('types');
        if ($types) {
            $typeArray = explode(',', $types);
            $spans->whereIn('type_id', $typeArray);
        }

        // Search by name
        if ($query) {
            $spans->where('name', 'ilike', "%{$query}%");
        }

        // Get results with type information
        $results = $spans->with('type')
            ->limit(10)
            ->get()
            ->map(function ($span) {
                return [
                    'id' => $span->id,
                    'name' => $span->name,
                    'type_id' => $span->type_id,
                    'type_name' => $span->type->name,
                    'state' => $span->state
                ];
            });

        return response()->json([
            'spans' => $results
        ]);
    }

    /**
     * Show the YAML editor for a span
     */
    public function yamlEditor(Span $span)
    {
        $this->authorize('update', $span);
        
        // Eager load connections for proper comparison in the changes summary
        $span->load(['connectionsAsSubject.type', 'connectionsAsObject.type']);
        
        // Convert span to YAML
        $yamlContent = $this->yamlService->spanToYaml($span);
        
        // Get all connection types and span types for help text
        $connectionTypes = ConnectionTypeModel::orderBy('type')->get();
        $spanTypes = SpanType::orderBy('type_id')->get();
        
        return view('spans.yaml-editor', compact('span', 'yamlContent', 'connectionTypes', 'spanTypes'));
    }

    /**
     * Store YAML content in session and redirect to editor
     */
    public function yamlEditorNew(Request $request)
    {
        \Log::info('yamlEditorNew called', ['request' => $request->all()]);
        
        $this->authorize('create', Span::class);
        
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        
        \Log::info('yamlEditorNew validation passed', ['content_length' => strlen($validated['yaml_content'])]);
        
        // Store YAML content in session
        session(['yaml_content' => $validated['yaml_content']]);
        
        // Return success response for AJAX
        return response()->json(['success' => true]);
    }

    /**
     * Show the YAML editor for a new span with content from session
     */
    public function yamlEditorNewFromSession(Request $request)
    {
        $this->authorize('create', Span::class);
        
        $yamlContent = session('yaml_content');
        if (!$yamlContent) {
            return redirect()->route('spans.index')
                ->with('error', 'No YAML content found in session');
        }
        
        // Clear the session data
        session()->forget('yaml_content');
        
        // Get all connection types and span types for help text
        $connectionTypes = ConnectionTypeModel::orderBy('type')->get();
        $spanTypes = SpanType::orderBy('type_id')->get();
        
        // Pass null for span to indicate this is a new span
        return view('spans.yaml-editor', [
            'span' => null,
            'yamlContent' => $yamlContent,
            'connectionTypes' => $connectionTypes,
            'spanTypes' => $spanTypes
        ]);
    }

    /**
     * Validate YAML content without applying changes
     */
    public function validateYaml(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        
        $result = $this->yamlService->yamlToSpanData($validated['yaml_content'], $span->slug, $span);
        
        // Add visual translation if validation was successful
        if ($result['success']) {
            $result['visual'] = $this->yamlService->translateToVisual($result['data']);
        }
        
        return response()->json($result);
    }

    /**
     * Validate YAML content for a new span without applying changes
     */
    public function validateYamlNew(Request $request)
    {
        $this->authorize('create', Span::class);
        
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        
        $result = $this->yamlService->yamlToSpanData($validated['yaml_content']);
        
        // Add visual translation if validation was successful
        if ($result['success']) {
            $result['visual'] = $this->yamlService->translateToVisual($result['data']);
        }
        
        return response()->json($result);
    }

    /**
     * Apply validated YAML to span
     */
    public function applyYaml(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        
        // First validate the YAML
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content'], $span->slug, $span);
        
        if (!$validationResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors']
            ], 422);
        }
        
        // Apply the changes to the database
        $applyResult = $this->yamlService->applyYamlToSpan($span, $validationResult['data']);
        
        if ($applyResult['success']) {
            return response()->json([
                'success' => true,
                'message' => $applyResult['message'],
                'redirect' => route('spans.yaml-editor', $span)
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $applyResult['message']
            ], 500);
        }
    }

    /**
     * Apply validated YAML to create a new span
     */
    public function applyYamlNew(Request $request)
    {
        $this->authorize('create', Span::class);
        
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        
        // First validate the YAML
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content']);
        
        if (!$validationResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors']
            ], 422);
        }
        
        // Create the new span
        $createResult = $this->yamlService->createSpanFromYaml($validationResult['data']);
        
        if ($createResult['success']) {
            $span = $createResult['span'];
            return response()->json([
                'success' => true,
                'message' => $createResult['message'],
                'redirect' => route('spans.yaml-editor', $span)
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $createResult['message']
            ], 500);
        }
    }

    /**
     * Display a listing of span types with example spans.
     */
    public function types(Request $request): View
    {
        try {
            // Get all span types (excluding connection type)
            $spanTypes = SpanType::where('type_id', '!=', 'connection')
                ->orderBy('name')
                ->get();

            // For each span type, get up to 5 example spans
            foreach ($spanTypes as $spanType) {
                $query = Span::query()
                    ->where('type_id', $spanType->type_id)
                    ->orderByRaw('COALESCE(start_year, 9999)')
                    ->orderByRaw('COALESCE(start_month, 12)')
                    ->orderByRaw('COALESCE(start_day, 31)');

                // Apply the same access filtering logic as the main spans index
                if (!Auth::check()) {
                    // For unauthenticated users, only show public spans
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

                $spanType->exampleSpans = $query->limit(5)->get();
            }

            return view('spans.types', compact('spanTypes'));
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error in spans types', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return error page
            if (app()->environment('production')) {
                return response()->view('errors.500', [], 500);
            } else {
                return response()->view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        }
    }

    /**
     * Create a new span from YAML
     */
    public function createFromYaml(Request $request)
    {
        $this->authorize('create', Span::class);

        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);

        // Validate the YAML
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content']);

        if (!$validationResult['success']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'YAML validation failed',
                    'errors' => $validationResult['errors']
                ], 422);
            } else {
                return back()->withErrors($validationResult['errors'])->withInput();
            }
        }

        // Create the new span
        $createResult = $this->yamlService->createSpanFromYaml($validationResult['data']);

        if ($createResult['success']) {
            $span = $createResult['span'];
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $createResult['message'],
                    'redirect' => route('spans.yaml-editor', $span)
                ]);
            } else {
                return redirect()->route('spans.yaml-editor', $span)
                    ->with('success', $createResult['message']);
            }
        } else {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $createResult['message']
                ], 500);
            } else {
                return back()->withErrors([$createResult['message']])->withInput();
            }
        }
    }
}
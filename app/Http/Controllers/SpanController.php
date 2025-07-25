<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
use App\Services\ConfigurableStoryGeneratorService;
use App\Services\RouteReservationService;
use InvalidArgumentException;
use App\Models\Connection;
use App\Models\ConnectionType as ConnectionTypeModel;
use App\Services\WikipediaOnThisDayService;
use App\Models\ConnectionVersion;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    protected $yamlService;
    protected $routeReservationService;

    /**
     * Create a new controller instance.
     */
    public function __construct(YamlSpanService $yamlService, RouteReservationService $routeReservationService)
    {
        // Require auth for all routes except show, index, search, desertIslandDiscs, connectionTypes, connectionsByType, showConnection, and listConnections
        $this->middleware('auth')->except(['show', 'index', 'search', 'desertIslandDiscs', 'connectionTypes', 'connectionsByType', 'showConnection', 'listConnections']);
        $this->yamlService = $yamlService;
        $this->routeReservationService = $routeReservationService;
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
            if ($request->filled('types')) {
                $types = is_array($request->types) ? $request->types : explode(',', $request->types);
                $query->whereIn('type_id', $types);

                // Handle person category filtering
                if (in_array('person', $types) && $request->has('person_category') && Auth::check()) {
                    $personCategories = is_array($request->person_category) ? $request->person_category : explode(',', $request->person_category);
                    
                    if (!empty($personCategories)) {
                        $userSpan = Auth::user()->personalSpan;
                        if ($userSpan) {
                            $relationshipService = app(\App\Services\PersonRelationshipService::class);
                            
                            // Separate relationship-based categories from subtype-based categories
                            $relationshipCategories = array_intersect($personCategories, ['musicians']);
                            $subtypeCategories = array_intersect($personCategories, ['public_figure', 'private_individual']);
                            
                            $categoryPeople = collect();
                            
                            // Handle relationship-based categories (like musicians)
                            foreach ($relationshipCategories as $category) {
                                $people = $relationshipService->getPeopleByCategory($category, $userSpan);
                                $categoryPeople = $categoryPeople->merge($people);
                            }
                            
                            // Handle subtype-based categories
                            if (!empty($subtypeCategories)) {
                                $subtypeQuery = Span::where('type_id', 'person');
                                $subtypeQuery->where(function($q) use ($subtypeCategories) {
                                    foreach ($subtypeCategories as $subtype) {
                                        $q->orWhereRaw("metadata->>'subtype' = ?", [$subtype]);
                                    }
                                });
                                $subtypePeople = $subtypeQuery->get();
                                $categoryPeople = $categoryPeople->merge($subtypePeople);
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

            // Apply visibility filter if specified
            if ($request->filled('visibility')) {
                switch ($request->visibility) {
                    case 'public':
                        $query->where('access_level', 'public');
                        break;
                    case 'private':
                        $query->where('access_level', 'private');
                        break;
                    case 'group':
                        $query->where('access_level', 'shared');
                        break;
                }
            } else {
                // Default access filtering when no visibility filter is applied
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
                return view('errors.500');
            } else {
                return view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
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
        
        // Check if this is an AI-generated span
        if ($request->has('ai_yaml')) {
            return $this->storeWithAiYaml($request);
        }
        
        // Get validation rules with special handling for timeless span types
        $typeId = $request->input('type_id');
        $state = $request->input('state', 'draft');
        
        // Debug: Log what we received
        \Illuminate\Support\Facades\Log::info('Span store validation debug', [
            'received_state' => $request->input('state'),
            'defaulted_state' => $state,
            'all_inputs' => $request->all()
        ]);
        
        // Check if this span type is marked as timeless
        $spanType = SpanType::find($typeId);
        $isTimeless = $spanType && ($spanType->metadata['timeless'] ?? false);
        
        $startYearRule = 'nullable|integer';
        if (!$isTimeless && $state !== 'placeholder') {
            $startYearRule = 'required|integer';
        }
        
        $validated = $request->validate([
            'id' => 'nullable|uuid|unique:spans,id',  // Allow UUID to be provided
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                'unique:spans,slug',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !$this->validateSlugNotReserved($value)) {
                        $reservedNames = $this->routeReservationService->getReservedNamesForDisplay();
                        $fail("The slug '{$value}' conflicts with a reserved route name. Reserved names include: " . implode(', ', $reservedNames));
                    }
                }
            ],
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
            return response()->json([
                'span_id' => $span->id,
                'span' => $span
            ]);
        }

        // Otherwise return the redirect for web requests
        return redirect()->route('spans.show', $span);
    }

    /**
     * Store a span with AI-generated YAML data
     */
    private function storeWithAiYaml(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'required|string|exists:span_types,type_id',
            'state' => 'required|in:draft,placeholder,complete',
            'ai_yaml' => 'required|string',
        ]);

        $confirmMerge = $request->boolean('confirm_merge', false);

        try {
            // Get the system user for AI-generated spans
            $systemUser = User::where('email', 'system@lifespan.app')->first();
            if (!$systemUser) {
                throw new \Exception('System user not found');
            }
            
            $yamlService = app(YamlSpanService::class);

            // Parse and validate the AI YAML data
            Log::info('AI YAML content for validation', [
                'name' => $validated['name'],
                'yaml_length' => strlen($validated['ai_yaml']),
                'yaml_sample' => substr($validated['ai_yaml'], 0, 500)
            ]);
            $validationResult = $yamlService->yamlToSpanData($validated['ai_yaml']);
            Log::info('YAML validation result', [
                'name' => $validated['name'],
                'success' => $validationResult['success'],
                'errors' => $validationResult['errors'] ?? []
            ]);
            if (!$validationResult['success']) {
                $errorMessage = 'Failed to validate AI data';
                if (!empty($validationResult['errors'])) {
                    $errorMessage .= ': ' . implode(', ', $validationResult['errors']);
                }
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage
                ], 422);
            }

            // Check for existing span
            $existingSpan = $yamlService->findExistingSpan($validated['name'], $validated['type_id']);
            if ($existingSpan) {
                $mergedData = $yamlService->mergeYamlWithExistingSpan($existingSpan, $validationResult['data']);
                if (!$confirmMerge) {
                    // Return merge preview, do not apply yet
                    return response()->json([
                        'success' => false,
                        'merge_available' => true,
                        'existing_span' => $existingSpan,
                        'merge_data' => $mergedData,
                        'message' => 'A span with this name and type already exists. Confirm to merge AI data.'
                    ]);
                } else {
                    // Apply the merge
                    $applyResult = $yamlService->applyMergedYamlToSpan($existingSpan, $mergedData);
                    if ($applyResult['success']) {
                        // Clear AI cache for this person so future generations include the new data
                        $this->clearAiCacheForPerson($validated['name']);
                        
                        // Explicitly clear timeline caches for the updated span
                        $existingSpan->clearTimelineCaches();
                        
                        return response()->json([
                            'success' => true,
                            'span_id' => $existingSpan->id,
                            'span' => $existingSpan,
                            'message' => 'Existing span updated with AI data.'
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'error' => $applyResult['message']
                        ], 500);
                    }
                }
            }

            // No existing span, create new
            $span = new Span([
                'name' => $validated['name'],
                'type_id' => $validated['type_id'],
                'state' => $validated['state'],
                'owner_id' => $systemUser->id,
                'updater_id' => $systemUser->id,
                'access_level' => 'private',
            ]);
            $span->save();

            $result = $yamlService->applyYamlToSpan($span, $validationResult['data']);
            if (!$result['success']) {
                $span->delete();
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to apply AI data: ' . $result['message']
                ], 422);
            }

            // Clear timeline caches for the new span
            $span->clearTimelineCaches();

            return response()->json([
                'success' => true,
                'span_id' => $span->id,
                'span' => $span,
                'message' => 'New span created with AI data.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create span with AI YAML', [
                'error' => $e->getMessage(),
                'name' => $validated['name'],
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create span: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified span.
     */
    public function show(Request $request, Span $subject): View|\Illuminate\Http\RedirectResponse
    {
        try {
            // Basic debug info
            \Illuminate\Support\Facades\Log::info('Span Show Request', [
                'route_param' => $request->segment(2),
                'span_id' => $subject->id,
                'span_type' => $subject->type_id,
                'is_uuid' => Str::isUuid($request->segment(2)),
                'slug' => $subject->slug
            ]);
            
            // If we're accessing via UUID and a slug exists, redirect to the slug URL
            $routeParam = $request->segment(2); // Get the actual URL segment
            
            if (Str::isUuid($routeParam) && $subject->slug) {
                \Illuminate\Support\Facades\Log::info('Redirecting to slug URL', [
                    'from' => $routeParam,
                    'to' => $subject->slug
                ]);
                
                return redirect()
                    ->route('spans.show', ['subject' => $subject->slug], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // Check if the span is private and the user is not authenticated
            if ($subject->access_level !== 'public' && !Auth::check()) {
                return redirect()->route('login');
            }

            // Authorize access using the SpanPolicy
            if (Auth::check()) {
                $this->authorize('view', $subject);
            }

            // Check if this is a person and they have a Desert Island Discs set
            $desertIslandDiscsSet = null;
            if ($subject->type_id === 'person') {
                try {
                    $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($subject);
                } catch (\Exception $e) {
                    // Log the error but don't fail the page
                    Log::warning('Failed to get Desert Island Discs set for person', [
                        'person_id' => $subject->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $span = $subject; // For view compatibility
            return view('spans.show', compact('span', 'desertIslandDiscsSet'));
        } catch (AuthorizationException $e) {
            // Return a 403 forbidden view
            return view('errors.403');
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error in spans show', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'route_param' => $request->segment(2)
            ]);
            // Return error page
            if (app()->environment('production')) {
                return view('errors.500');
            } else {
                return view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
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
            ->limit(100) // Limit to prevent memory exhaustion
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
            $typeId = $request->input('type_id', $span->type_id);
            $state = $request->input('state', $span->state);
            
            // Check if this span type is marked as timeless
            $spanType = SpanType::find($typeId);
            $isTimeless = $spanType && ($spanType->metadata['timeless'] ?? false);
            
            $startYearRule = 'nullable|integer';
            if (!$isTimeless && $state !== 'placeholder') {
                $startYearRule = 'required|integer';
            }

            // Custom validation for date patterns
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'slug' => [
                    'nullable',
                    'string',
                    'max:255',
                    'regex:/^[a-z0-9-]+$/',
                    'unique:spans,slug,' . $span->id,
                    function ($attribute, $value, $fail) {
                        if (!$this->validateSlugNotReserved($value)) {
                            $reservedNames = $this->routeReservationService->getReservedNamesForDisplay();
                            $fail("The slug '{$value}' conflicts with a reserved route name. Reserved names include: " . implode(', ', $reservedNames));
                        }
                    }
                ],
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
        
        try {
            $spanName = $span->name;
            
            // Clean up any personal_span_id references before deleting the span
            $usersWithPersonalSpan = \App\Models\User::where('personal_span_id', $span->id)->get();
            if ($usersWithPersonalSpan->count() > 0) {
                Log::info('Cleaning up personal_span_id references before span deletion', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'affected_users' => $usersWithPersonalSpan->pluck('id')->toArray()
                ]);
                
                \App\Models\User::where('personal_span_id', $span->id)
                    ->update(['personal_span_id' => null]);
            }
            
            $span->delete();
            
            // Debug: Log the request details
            Log::info('Span delete request details', [
                'expectsJson' => request()->expectsJson(),
                'wantsJson' => request()->wantsJson(),
                'isAjax' => request()->ajax(),
                'accept' => request()->header('Accept'),
                'contentType' => request()->header('Content-Type'),
                'userAgent' => request()->header('User-Agent')
            ]);
            
            // If this is an AJAX request, return JSON
            if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Span '{$spanName}' deleted successfully"
                ]);
            }
            
            return redirect()->route('spans.index')
                ->with('status', "Span '{$spanName}' deleted successfully");
                
        } catch (\Exception $e) {
            Log::error('Error deleting span', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage()
            ]);
            
            if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete span: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withErrors(['error' => 'Failed to delete span: ' . $e->getMessage()]);
        }
    }

    /**
     * Explore spans that start or end on a specific date.
     */
    public function exploreDate(string $date): View
    {
        // Parse the date string into components
        $dateParts = explode('-', $date);
        $year = (int) $dateParts[0];
        $month = isset($dateParts[1]) ? (int) $dateParts[1] : null;
        $day = isset($dateParts[2]) ? (int) $dateParts[2] : null;
        
        // Determine the precision level
        $precision = 'year';
        if ($day !== null) {
            $precision = 'day';
        } elseif ($month !== null) {
            $precision = 'month';
        }

        // Build the base query for spans that start or end on this date
        $query = Span::query()
            ->where(function ($query) use ($year, $month, $day, $precision) {
                if ($precision === 'day') {
                    // For day precision, show spans that start or end on this exact date
                    $query->where(function ($q) use ($year, $month, $day) {
                        $q->where('start_year', $year)
                          ->where('start_month', $month)
                          ->where('start_day', $day);
                    })
                    ->orWhere(function ($q) use ($year, $month, $day) {
                        $q->where('end_year', $year)
                          ->where('end_month', $month)
                          ->where('end_day', $day);
                    });
                } elseif ($precision === 'month') {
                    // For month precision, show spans that start or end in this month
                    $query->where(function ($q) use ($year, $month) {
                        $q->where('start_year', $year)
                          ->where('start_month', $month);
                    })
                    ->orWhere(function ($q) use ($year, $month) {
                        $q->where('end_year', $year)
                          ->where('end_month', $month);
                    });
                } else {
                    // For year precision, show spans that start or end in this year
                    $query->where('start_year', $year)
                          ->orWhere('end_year', $year);
                }
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

        // Get all spans and separate them into different lists based on precision
        $allSpans = $query->get();
        
        if ($precision === 'day') {
            // For day precision, separate by start/end on exact date
            $spansStartingOnDate = $allSpans->filter(function ($span) use ($year, $month, $day) {
                return $span->start_year == $year && 
                       $span->start_month == $month && 
                       $span->start_day == $day;
            });

            $spansEndingOnDate = $allSpans->filter(function ($span) use ($year, $month, $day) {
                return $span->end_year == $year && 
                       $span->end_month == $month && 
                       $span->end_day == $day;
            });

            // Initialize empty collections for other precision levels
            $spansStartingInMonth = collect();
            $spansEndingInMonth = collect();
            $spansStartingInYear = collect();
            $spansEndingInYear = collect();
            
        } elseif ($precision === 'month') {
            // For month precision, separate by start/end in month
            $spansStartingInMonth = $allSpans->filter(function ($span) use ($year, $month) {
                return $span->start_year == $year && 
                       $span->start_month == $month;
            });

            $spansEndingInMonth = $allSpans->filter(function ($span) use ($year, $month) {
                return $span->end_year == $year && 
                       $span->end_month == $month;
            });

            // Initialize empty collections for other precision levels
            $spansStartingOnDate = collect();
            $spansEndingOnDate = collect();
            $spansStartingInYear = collect();
            $spansEndingInYear = collect();
            
        } else {
            // For year precision, separate by start/end in year
            $spansStartingInYear = $allSpans->filter(function ($span) use ($year) {
                return $span->start_year == $year;
            });

            $spansEndingInYear = $allSpans->filter(function ($span) use ($year) {
                return $span->end_year == $year;
            });

            // Initialize empty collections for other precision levels
            $spansStartingOnDate = collect();
            $spansEndingOnDate = collect();
            $spansStartingInMonth = collect();
            $spansEndingInMonth = collect();
        }

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

        // Limit each collection to 5 random items
        $spansStartingOnDate = $spansStartingOnDate->shuffle()->take(5);
        $spansEndingOnDate = $spansEndingOnDate->shuffle()->take(5);
        $spansStartingInMonth = $spansStartingInMonth->shuffle()->take(5);
        $spansEndingInMonth = $spansEndingInMonth->shuffle()->take(5);
        $spansStartingInYear = $spansStartingInYear->shuffle()->take(5);
        $spansEndingInYear = $spansEndingInYear->shuffle()->take(5);
        $connectionsStartingOnDate = $connectionsStartingOnDate->shuffle()->take(5);
        $connectionsEndingOnDate = $connectionsEndingOnDate->shuffle()->take(5);
        $connectionsStartingInMonth = $connectionsStartingInMonth->shuffle()->take(5);
        $connectionsEndingInMonth = $connectionsEndingInMonth->shuffle()->take(5);
        $connectionsStartingInYear = $connectionsStartingInYear->shuffle()->take(5);
        $connectionsEndingInYear = $connectionsEndingInYear->shuffle()->take(5);

        // Wikipedia data is now loaded asynchronously via AJAX
        $wikipediaData = [];

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
            'wikipediaData',
            'date',
            'precision',
            'year',
            'month',
            'day'
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
                            ->whereHas('spanPermissions', function ($q) use ($user) {
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
     * Get the YAML representation of a span
     */
    public function getYaml(Span $span)
    {
        try {
            $yamlContent = $this->yamlService->spanToYaml($span);
            
            return response()->json([
                'success' => true,
                'yaml' => $yamlContent
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate YAML: ' . $e->getMessage()
            ], 500);
        }
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
        
        // Validate the YAML to extract basic information
        $validationResult = $this->yamlService->yamlToSpanData($yamlContent);
        
        $existingSpan = null;
        $mergeData = null;
        
        if ($validationResult['success']) {
            $data = $validationResult['data'];
            $spanName = $data['name'] ?? '';
            $spanType = $data['type'] ?? '';
            
            // Check if a span with this name and type already exists
            if ($spanName && $spanType) {
                $existingSpan = $this->yamlService->findExistingSpan($spanName, $spanType);
                
                if ($existingSpan) {
                    // Check if user has permission to update this span
                    if (auth()->user()->can('update', $existingSpan)) {
                        // Generate merged data for preview
                        $mergeData = $this->yamlService->mergeYamlWithExistingSpan($existingSpan, $data);
                        $mergeData['yaml_content'] = $yamlContent;
                    }
                }
            }
        }
        
        // Get all connection types and span types for help text
        $connectionTypes = ConnectionTypeModel::orderBy('type')->get();
        $spanTypes = SpanType::orderBy('type_id')->get();
        
        // Pass null for span to indicate this is a new span
        return view('spans.yaml-editor', [
            'span' => null,
            'yamlContent' => $yamlContent,
            'connectionTypes' => $connectionTypes,
            'spanTypes' => $spanTypes,
            'existingSpan' => $existingSpan,
            'mergeData' => $mergeData
        ]);
    }

    /**
     * Validate YAML content for new spans
     */
    public function validateYamlNew(Request $request)
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
        
        // Check for existing span
        $data = $validationResult['data'];
        $existingSpan = null;
        $mergeData = null;
        
        if (isset($data['name']) && isset($data['type'])) {
            $existingSpan = $this->yamlService->findExistingSpan($data['name'], $data['type']);
            
            if ($existingSpan) {
                // Check if user has permission to update this span
                if (auth()->user()->can('update', $existingSpan)) {
                    // Generate merged data for preview
                    $mergeData = $this->yamlService->mergeYamlWithExistingSpan($existingSpan, $data);
                }
            }
        }
        
        // Generate visual translation
        $visualTranslation = $this->yamlService->translateToVisual($validationResult['data']);
        
        return response()->json([
            'success' => true,
            'data' => $validationResult['data'],
            'visual' => $visualTranslation,
            'existingSpan' => $existingSpan ? [
                'id' => $existingSpan->id,
                'name' => $existingSpan->name,
                'type' => $existingSpan->type_id,
                'state' => $existingSpan->state,
                'has_permission' => auth()->user()->can('update', $existingSpan)
            ] : null,
            'mergeData' => $mergeData
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
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content'], $span->slug, $span);
        if (!$validationResult['success']) {
            $debug = null;
            if (auth()->user() && auth()->user()->is_admin) {
                $debug = "Raw YAML:\n" . $validated['yaml_content'] . "\n\n";
                if (!empty($validationResult['errors'])) {
                    $debug .= "Errors:\n" . print_r($validationResult['errors'], true);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors'],
                'debug' => $debug,
            ], 422);
        }
        // Generate visual translation and impact analysis
        $visualTranslation = $this->yamlService->translateToVisual($validationResult['data']);
        $changeImpacts = $this->yamlService->analyzeChangeImpacts($validationResult['data'], $span);
        
        return response()->json([
            'success' => true,
            'data' => $validationResult['data'],
            'visual' => $visualTranslation,
            'impacts' => $changeImpacts
        ]);
    }

    /**
     * Apply validated YAML to an existing span
     */
    public function applyYaml(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content'], $span->slug, $span);
        if (!$validationResult['success']) {
            $debug = null;
            if (auth()->user() && auth()->user()->is_admin) {
                $debug = "Raw YAML:\n" . $validated['yaml_content'] . "\n\n";
                if (!empty($validationResult['errors'])) {
                    $debug .= "Errors:\n" . print_r($validationResult['errors'], true);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors'],
                'debug' => $debug,
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
     * Apply merged YAML to an existing span
     */
    public function applyMergedYaml(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        $validated = $request->validate([
            'yaml_content' => 'required|string'
        ]);
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content']);
        if (!$validationResult['success']) {
            $debug = null;
            if (auth()->user() && auth()->user()->is_admin) {
                $debug = "Raw YAML:\n" . $validated['yaml_content'] . "\n\n";
                if (!empty($validationResult['errors'])) {
                    $debug .= "Errors:\n" . print_r($validationResult['errors'], true);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors'],
                'debug' => $debug,
            ], 422);
        }
        // Generate merged data
        $mergedData = $this->yamlService->mergeYamlWithExistingSpan($span, $validationResult['data']);
        
        // Apply the merged changes to the database
        $applyResult = $this->yamlService->applyMergedYamlToSpan($span, $mergedData);
        
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
        $validationResult = $this->yamlService->yamlToSpanData($validated['yaml_content']);
        if (!$validationResult['success']) {
            $debug = null;
            if (auth()->user() && auth()->user()->is_admin) {
                $debug = "Raw YAML:\n" . $validated['yaml_content'] . "\n\n";
                if (!empty($validationResult['errors'])) {
                    $debug .= "Errors:\n" . print_r($validationResult['errors'], true);
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'YAML validation failed',
                'errors' => $validationResult['errors'],
                'debug' => $debug,
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
     * Clear AI cache for a specific person
     */
    private function clearAiCacheForPerson(string $name): void
    {
        try {
            // Clear cache for the name without disambiguation
            $cacheKey = 'ai_yaml_' . md5(strtolower($name));
            Cache::forget($cacheKey);
            
            // Also clear any cached versions with disambiguation (common patterns)
            $commonDisambiguations = [
                'the musician',
                'the actor',
                'the politician',
                'the writer',
                'the scientist',
                'the artist'
            ];
            
            foreach ($commonDisambiguations as $disambiguation) {
                $cacheKeyWithDisambiguation = $cacheKey . '_' . md5(strtolower($disambiguation));
                Cache::forget($cacheKeyWithDisambiguation);
            }
            
            Log::info('Cleared AI cache for person', ['name' => $name]);
        } catch (\Exception $e) {
            Log::warning('Failed to clear AI cache for person', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
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

            // Collect example spans for each type
            $exampleSpans = [];

            // For each span type, get up to 5 example spans
            foreach ($spanTypes as $spanType) {
                $query = Span::query()
                    ->where('type_id', $spanType->type_id)
                    ->orderByRaw('COALESCE(start_year, 9999)')
                    ->orderByRaw('COALESCE(start_month, 12)')
                    ->orderByRaw('COALESCE(start_day, 31)');

                // Show all states by default
                $query->whereIn('state', ['complete', 'draft', 'placeholder']);

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

                $exampleSpans[$spanType->type_id] = $query->limit(5)->get();
            }

            return view('spans.types', compact('spanTypes', 'exampleSpans'));
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
     * Display a specific span type with all spans of that type.
     */
    public function showType(Request $request, string $type): View
    {
        try {
            // Find the span type
            $spanType = SpanType::where('type_id', $type)->first();
            
            if (!$spanType) {
                abort(404, 'Span type not found');
            }

            // Build the query for spans of this type
            $query = Span::query()
                ->where('type_id', $type)
                ->orderByRaw('COALESCE(start_year, 9999)')
                ->orderByRaw('COALESCE(start_month, 12)')
                ->orderByRaw('COALESCE(start_day, 31)');

            // Show all states by default
            $query->whereIn('state', ['complete', 'draft', 'placeholder']);

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

            // Handle search within this type
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

            $spans = $query->paginate(20);

            return view('spans.type-show', compact('spanType', 'spans'));
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('Error in span type show', [
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
     * Display all subtypes for a specific span type.
     */
    public function showSubtypes(Request $request, string $type): View|Response
    {
        try {
            // Find the span type
            $spanType = SpanType::where('type_id', $type)->first();
            
            if (!$spanType) {
                abort(404, 'Span type not found');
            }

            // Get all spans of this type to extract unique subtypes
            $query = Span::query()
                ->where('type_id', $type)
                ->whereNotNull('metadata->subtype')
                ->where('metadata->subtype', '!=', '');

            // Apply access filtering
            if (!Auth::check()) {
                $query->where('access_level', 'public');
            } else {
                $user = Auth::user();
                if (!$user->is_admin) {
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

            // Get unique subtypes with counts using the Span model method
            $subtypes = Span::getSubtypesForType($type);
            
            // Collect example spans for each subtype
            $subtypeExamples = [];

            // For each subtype, get up to 3 example spans
            foreach ($subtypes as $subtype) {
                $exampleQuery = Span::query()
                    ->where('type_id', $type)
                    ->where('metadata->subtype', $subtype->subtype)
                    ->whereIn('state', ['complete', 'draft', 'placeholder']);

                // Apply same access filtering
                if (!Auth::check()) {
                    $exampleQuery->where('access_level', 'public');
                } else {
                    $user = Auth::user();
                    if (!$user->is_admin) {
                        $exampleQuery->where(function ($query) use ($user) {
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

                $subtypeExamples[$subtype->subtype] = $exampleQuery
                    ->orderByRaw('COALESCE(start_year, 9999)')
                    ->orderByRaw('COALESCE(start_month, 12)')
                    ->orderByRaw('COALESCE(start_day, 31)')
                    ->limit(3)
                    ->get();
            }

            return view('spans.type-subtypes', compact('spanType', 'subtypes', 'subtypeExamples'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in span type subtypes', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
     * Display all spans of a specific type and subtype.
     */
    public function showTypeSubtype(Request $request, string $type, string $subtype): View|Response
    {
        try {
            // Find the span type
            $spanType = SpanType::where('type_id', $type)->first();
            
            if (!$spanType) {
                abort(404, 'Span type not found');
            }

            // Build the query for spans of this type and subtype
            $query = Span::query()
                ->where('type_id', $type)
                ->where('metadata->subtype', $subtype)
                ->orderByRaw('COALESCE(start_year, 9999)')
                ->orderByRaw('COALESCE(start_month, 12)')
                ->orderByRaw('COALESCE(start_day, 31)');

            // Show all states by default
            $query->whereIn('state', ['complete', 'draft', 'placeholder']);

            // Apply access filtering
            if (!Auth::check()) {
                $query->where('access_level', 'public');
            } else {
                $user = Auth::user();
                if (!$user->is_admin) {
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

            // Handle search within this type/subtype
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

            $spans = $query->paginate(20);

            return view('spans.type-subtype-show', compact('spanType', 'subtype', 'spans'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in span type subtype show', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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

    /**
     * Display the version history for a span.
     */
    public function history(Request $request, Span $span): View
    {
        $versions = $span->versions()->with('changedBy')->orderByDesc('version_number')->get();
        
        // Get connection changes affecting this span
        $connectionChanges = ConnectionVersion::whereHas('connection', function($query) use ($span) {
            $query->where('parent_id', $span->id)
                  ->orWhere('child_id', $span->id);
        })->with(['connection.subject', 'connection.object', 'connection.type', 'connection.connectionSpan', 'changedBy'])
          ->orderByDesc('created_at')
          ->get()
          ->map(function($connVersion) use ($span) {
              $connection = $connVersion->connection;
              $otherSpan = $connection->parent_id === $span->id 
                  ? $connection->object 
                  : $connection->subject;
              
              return [
                  'type' => 'connection_change',
                  'version' => $connVersion,
                  'connection' => $connection,
                  'other_span' => $otherSpan,
                  'relationship_type' => $connection->type->forward_predicate ?? $connection->type_id,
                  'is_parent' => $connection->parent_id === $span->id
              ];
          });
        
        // Combine and sort by date
        $allChanges = collect()
            ->concat($versions->map(fn($v) => ['type' => 'span_change', 'version' => $v]))
            ->concat($connectionChanges)
            ->sortByDesc(fn($change) => $change['version']->created_at);
        
        return view('spans.history', compact('span', 'allChanges', 'versions'));
    }

    /**
     * Display a specific version of a span with changes from the previous version.
     */
    public function showVersion(Request $request, Span $span, int $version): View
    {
        $versionModel = $span->getVersion($version);
        
        if (!$versionModel) {
            abort(404, 'Version not found');
        }

        // Get the previous version for comparison
        $previousVersion = $span->versions()
            ->where('version_number', '<', $version)
            ->orderByDesc('version_number')
            ->first();

        $changes = [];
        if ($previousVersion) {
            $changes = $versionModel->getDiffFrom($previousVersion);
        }

        return view('spans.version-show', compact('span', 'versionModel', 'previousVersion', 'changes'));
    }

    /**
     * Display a story for a span.
     */
    public function story(Request $request, Span $span): View
    {
        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        return view('spans.story', compact('span', 'story'));
    }

    /**
     * Display all Desert Island Discs sets.
     */
    public function desertIslandDiscs(Request $request): View
    {
        $query = Span::query()
            ->where('type_id', 'set')
            ->where(function($q) {
                $q->whereJsonContains('metadata->subtype', 'desertislanddiscs')
                  ->orWhere('metadata->subtype', 'desertislanddiscs');
            })
            ->orderBy('name');

        // Apply access filtering
        if (!Auth::check()) {
            $query->where('access_level', 'public');
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
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

        $sets = $query->paginate(20);

        // Use cached set contents for each set
        $sets->getCollection()->transform(function ($set) {
            $tracks = $set->getSetContents()->filter(function($item) {
                return $item->type_id === 'thing' && 
                       ($item->metadata['subtype'] ?? null) === 'track';
            });
            $set->preloaded_tracks = $tracks;
            return $set;
        });

        \Illuminate\Support\Facades\Log::info('Desert Island Discs query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'count' => $sets->count(),
            'total' => $sets->total(),
            'is_authenticated' => Auth::check(),
            'user_id' => Auth::id()
        ]);

        return view('desert-island-discs.index', compact('sets'));
    }

    /**
     * Display all connection types for a span.
     */
    public function connectionTypes(Request $request, Span $span): View
    {
        // Get all connection types that have connections with this span
        $connectionTypes = ConnectionType::whereHas('connections', function($query) use ($span) {
            $query->where('parent_id', $span->id)
                  ->orWhere('child_id', $span->id);
        })->withCount(['connections' => function($query) use ($span) {
            $query->where('parent_id', $span->id)
                  ->orWhere('child_id', $span->id);
        }])->orderBy('type')->get();

        return view('spans.connection-types.index', compact('span', 'connectionTypes'));
    }

    /**
     * Display connections of a specific type for a span.
     */
    public function connectionsByType(Request $request, Span $span, ConnectionType $connectionType): View
    {
        // Get connections of this type involving the span with access control
        $user = auth()->user();
        $connections = Connection::where('type_id', $connectionType->type)
            ->where(function($query) use ($span) {
                $query->where('parent_id', $span->id)
                      ->orWhere('child_id', $span->id);
            })
            ->where(function($query) use ($user) {
                if (!$user) {
                    // Guest users can only see connections involving public spans
                    $query->whereHas('subject', function($q) {
                        $q->where('access_level', 'public');
                    })->whereHas('object', function($q) {
                        $q->where('access_level', 'public');
                    });
                } elseif (!$user->is_admin) {
                    // Regular users can see connections involving spans they have permission to view
                    $query->where(function($subQ) use ($user) {
                        $subQ->whereHas('subject', function($q) use ($user) {
                            $q->where(function($spanQ) use ($user) {
                                $spanQ->where('access_level', 'public')
                                    ->orWhere('owner_id', $user->id)
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->where('user_id', $user->id)
                                              ->whereIn('permission_type', ['view', 'edit']);
                                    })
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->whereNotNull('group_id')
                                              ->whereIn('permission_type', ['view', 'edit'])
                                              ->whereHas('group', function($groupQ) use ($user) {
                                                  $groupQ->whereHas('users', function($userQ) use ($user) {
                                                      $userQ->where('user_id', $user->id);
                                                  });
                                              });
                                    });
                            });
                        })->whereHas('object', function($q) use ($user) {
                            $q->where(function($spanQ) use ($user) {
                                $spanQ->where('access_level', 'public')
                                    ->orWhere('owner_id', $user->id)
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->where('user_id', $user->id)
                                              ->whereIn('permission_type', ['view', 'edit']);
                                    })
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->whereNotNull('group_id')
                                              ->whereIn('permission_type', ['view', 'edit'])
                                              ->whereHas('group', function($groupQ) use ($user) {
                                                  $groupQ->whereHas('users', function($userQ) use ($user) {
                                                      $userQ->where('user_id', $user->id);
                                                  });
                                              });
                                    });
                            });
                        });
                    });
                }
            })
            ->with(['subject', 'object', 'connectionSpan'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform connections to show the other span and relationship direction
        $connections->getCollection()->transform(function($connection) use ($span) {
            $isParent = $connection->parent_id === $span->id;
            $otherSpan = $isParent ? $connection->object : $connection->subject;
            $predicate = $isParent ? $connection->type->forward_predicate : $connection->type->inverse_predicate;
            
            $connection->other_span = $otherSpan;
            $connection->is_parent = $isParent;
            $connection->predicate = $predicate;
            
            return $connection;
        });

        return view('spans.connection-types.show', compact('span', 'connectionType', 'connections'));
    }

    /**
     * Display a specific connection between a subject and object of a particular type.
     */
    public function showConnection(Request $request, Span $subject, string $predicate, Span $object): View
    {
        // Find the connection type based on the predicate
        $predicateWithSpaces = str_replace('-', ' ', $predicate);
        $connectionType = ConnectionType::where('forward_predicate', $predicateWithSpaces)
            ->orWhere('inverse_predicate', $predicateWithSpaces)
            ->first();

        if (!$connectionType) {
            abort(404, 'Connection type not found');
        }

        // Find the connection between the subject and object
        $connection = Connection::where('type_id', $connectionType->type)
            ->where(function($query) use ($subject, $object) {
                $query->where(function($q) use ($subject, $object) {
                    $q->where('parent_id', $subject->id)
                      ->where('child_id', $object->id);
                })->orWhere(function($q) use ($subject, $object) {
                    $q->where('parent_id', $object->id)
                      ->where('child_id', $subject->id);
                });
            })
            ->first();

        if (!$connection) {
            abort(404, 'Connection not found');
        }

        // Get the connection span (the span that represents this connection)
        $connectionSpan = $connection->connectionSpan;
        if (!$connectionSpan) {
            abort(404, 'Connection span not found');
        }

        // Check if the connection span is private and the user is not authenticated
        if ($connectionSpan->access_level !== 'public' && !Auth::check()) {
            return redirect()->route('login');
        }

        // Check if this is a person and they have a Desert Island Discs set
        $desertIslandDiscsSet = null;
        if ($connectionSpan->type_id === 'person') {
            try {
                $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($connectionSpan);
            } catch (\Exception $e) {
                // Log the error but don't fail the page
                Log::warning('Failed to get Desert Island Discs set for person', [
                    'person_id' => $connectionSpan->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Use the same view as the span show page, showing the connection span
        $span = $connectionSpan; // For view compatibility
        return view('spans.show', compact('span', 'desertIslandDiscsSet', 'subject', 'object', 'connectionType'));
    }

    /**
     * Display all connections of a specific type for a subject.
     */
    public function listConnections(Request $request, Span $subject, string $predicate): View|\Illuminate\Http\RedirectResponse
    {
        // Find the connection type based on the predicate
        $predicateWithSpaces = str_replace('-', ' ', $predicate);
        $connectionType = ConnectionType::where('forward_predicate', $predicateWithSpaces)
            ->orWhere('inverse_predicate', $predicateWithSpaces)
            ->first();

        if (!$connectionType) {
            // If the predicate is not a valid connection type, redirect to the span show page
            return redirect()->route('spans.show', $subject);
        }

        // Get all connections of this type involving the subject with access control
        $user = auth()->user();
        $connections = Connection::where('type_id', $connectionType->type)
            ->where(function($query) use ($subject) {
                $query->where('parent_id', $subject->id)
                      ->orWhere('child_id', $subject->id);
            })
            ->where(function($query) use ($user) {
                if (!$user) {
                    // Guest users can only see connections involving public spans
                    $query->whereHas('subject', function($q) {
                        $q->where('access_level', 'public');
                    })->whereHas('object', function($q) {
                        $q->where('access_level', 'public');
                    });
                } elseif (!$user->is_admin) {
                    // Regular users can see connections involving spans they have permission to view
                    $query->where(function($subQ) use ($user) {
                        $subQ->whereHas('subject', function($q) use ($user) {
                            $q->where(function($spanQ) use ($user) {
                                $spanQ->where('access_level', 'public')
                                    ->orWhere('owner_id', $user->id)
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->where('user_id', $user->id)
                                              ->whereIn('permission_type', ['view', 'edit']);
                                    })
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->whereNotNull('group_id')
                                              ->whereIn('permission_type', ['view', 'edit'])
                                              ->whereHas('group', function($groupQ) use ($user) {
                                                  $groupQ->whereHas('users', function($userQ) use ($user) {
                                                      $userQ->where('user_id', $user->id);
                                                  });
                                              });
                                    });
                            });
                        })->whereHas('object', function($q) use ($user) {
                            $q->where(function($spanQ) use ($user) {
                                $spanQ->where('access_level', 'public')
                                    ->orWhere('owner_id', $user->id)
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->where('user_id', $user->id)
                                              ->whereIn('permission_type', ['view', 'edit']);
                                    })
                                    ->orWhereHas('spanPermissions', function($permQ) use ($user) {
                                        $permQ->whereNotNull('group_id')
                                              ->whereIn('permission_type', ['view', 'edit'])
                                              ->whereHas('group', function($groupQ) use ($user) {
                                                  $groupQ->whereHas('users', function($userQ) use ($user) {
                                                      $userQ->where('user_id', $user->id);
                                                  });
                                              });
                                    });
                            });
                        });
                    });
                }
            })
            ->with(['subject', 'object', 'connectionSpan'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform connections to show the other span and relationship direction
        $connections->getCollection()->transform(function($connection) use ($subject, $connectionType) {
            $isParent = $connection->parent_id === $subject->id;
            $otherSpan = $isParent ? $connection->object : $connection->subject;
            $predicate = $isParent ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
            
            $connection->other_span = $otherSpan;
            $connection->is_parent = $isParent;
            $connection->predicate = $predicate;
            
            return $connection;
        });

        return view('spans.connections', compact('subject', 'connectionType', 'connections', 'predicate'));
    }

    /**
     * Show spans shared with the current user via group memberships
     */
    public function sharedWithMe(Request $request): View
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Get all groups the user is a member of
        $groups = $user->groups()->with('spanPermissions.span.owner', 'spanPermissions.span.type')->orderBy('name')->get();

        // For each group, get all spans shared with that group (excluding public and own spans)
        $groupsWithSpans = $groups->map(function ($group) use ($user) {
            // Get all span IDs this group has view permission for
            $spanIds = $group->spanPermissions()
                ->where('permission_type', 'view')
                ->pluck('span_id')
                ->unique();

            // Fetch the spans, excluding public and user's own
            $spans = Span::with(['owner', 'type'])
                ->whereIn('id', $spanIds)
                ->where('access_level', '!=', 'public')
                ->where('owner_id', '!=', $user->id)
                ->orderBy('name')
                ->get();

            return [
                'group' => $group,
                'spans' => $spans,
            ];
        });

        // Get spans that the user owns and has shared with groups
        $spansSharedByMe = Span::with(['spanPermissions.group', 'type'])
            ->where('owner_id', $user->id)
            ->where('access_level', 'shared')
            ->whereHas('spanPermissions', function ($query) {
                $query->whereNotNull('group_id');
            })
            ->orderBy('name')
            ->get()
            ->groupBy(function ($span) {
                // Group by the first group that has access (for display purposes)
                $firstGroupPermission = $span->spanPermissions->whereNotNull('group_id')->first();
                return $firstGroupPermission ? $firstGroupPermission->group->name : 'Other Groups';
            });

        // Get all span types for filtering (if needed in the view)
        $spanTypes = SpanType::where('type_id', '!=', 'connection')
            ->orderBy('name')
            ->get();

        return view('spans.shared-with-me', compact('groupsWithSpans', 'spansSharedByMe', 'spanTypes'));
    }

    /**
     * Validate that a slug doesn't conflict with reserved route names
     */
    private function validateSlugNotReserved($slug): bool
    {
        if (empty($slug)) {
            return true; // Empty slugs are auto-generated, so they're fine
        }
        
        return !$this->routeReservationService->isReserved($slug);
    }

    /**
     * Improve an existing span with AI-generated YAML data
     */
    public function improveWithAi(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $validated = $request->validate([
            'ai_yaml' => 'required|string',
        ]);

        try {
            $yamlService = app(YamlSpanService::class);

            // Parse and validate the AI YAML data
            Log::info('AI YAML improvement content for validation', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'yaml_length' => strlen($validated['ai_yaml']),
                'yaml_sample' => substr($validated['ai_yaml'], 0, 500)
            ]);
            
            $validationResult = $yamlService->yamlToSpanData($validated['ai_yaml'], $span->slug, $span);
            Log::info('YAML improvement validation result', [
                'span_id' => $span->id,
                'success' => $validationResult['success'],
                'errors' => $validationResult['errors'] ?? []
            ]);
            
            if (!$validationResult['success']) {
                $errorMessage = 'Failed to validate AI improvement data';
                if (!empty($validationResult['errors'])) {
                    $errorMessage .= ': ' . implode(', ', $validationResult['errors']);
                }
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage
                ], 422);
            }

            // Merge the AI data with the existing span
            $mergedData = $yamlService->mergeYamlWithExistingSpan($span, $validationResult['data']);
            
            // Apply the merged data to the span
            $applyResult = $yamlService->applyMergedYamlToSpan($span, $mergedData);
            
            if ($applyResult['success']) {
                // Clear AI cache for this person so future generations include the new data
                $this->clearAiCacheForPerson($span->name);
                
                // Explicitly clear timeline caches for the updated span
                $span->clearTimelineCaches();
                
                return response()->json([
                    'success' => true,
                    'span_id' => $span->id,
                    'span' => $span->fresh(),
                    'message' => 'Span improved successfully with AI data.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $applyResult['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('AI span improvement error', [
                'error' => $e->getMessage(),
                'span_id' => $span->id,
                'span_name' => $span->name
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to improve span: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview changes that would be made by improving a span with AI-generated YAML
     */
    public function previewImprovement(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $validated = $request->validate([
            'ai_yaml' => 'required|string',
        ]);

        try {
            $yamlService = app(YamlSpanService::class);

            // Parse and validate the AI YAML data (skip slug validation for preview)
            $validationResult = $yamlService->yamlToSpanDataForPreview($validated['ai_yaml'], $span);
            
            if (!$validationResult['success']) {
                $errorMessage = 'Failed to validate AI improvement data';
                if (!empty($validationResult['errors'])) {
                    $errorMessage .= ': ' . implode(', ', $validationResult['errors']);
                }
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage
                ], 422);
            }

            // Generate merged data to see what the final state would be
            $mergedData = $yamlService->mergeYamlWithExistingSpan($span, $validationResult['data']);
            
            // Analyze the impacts of the changes
            $impacts = $yamlService->analyzeChangeImpacts($mergedData, $span);
            
            // Get current span data for comparison
            $currentData = $yamlService->spanToArray($span);
            
            // Create a structured diff showing what will change
            $diff = $this->createStructuredDiff($currentData, $mergedData);
            
            return response()->json([
                'success' => true,
                'impacts' => $impacts,
                'diff' => $diff,
                'current_data' => $currentData,
                'merged_data' => $mergedData,
                'message' => 'Preview generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('AI span improvement preview error', [
                'error' => $e->getMessage(),
                'span_id' => $span->id,
                'span_name' => $span->name
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a structured diff between current and merged data
     */
    private function createStructuredDiff(array $currentData, array $mergedData): array
    {
        $diff = [
            'basic_fields' => [],
            'metadata' => [],
            'sources' => [],
            'connections' => []
        ];

        // Compare basic fields
        $basicFields = ['description', 'notes', 'start', 'end'];
        foreach ($basicFields as $field) {
            $current = $currentData[$field] ?? null;
            $merged = $mergedData[$field] ?? null;
            
            if ($current !== $merged) {
                $diff['basic_fields'][] = [
                    'field' => $field,
                    'current' => $current,
                    'new' => $merged,
                    'action' => $current === null ? 'add' : ($merged === null ? 'remove' : 'update')
                ];
            }
        }

        // Compare metadata
        $currentMetadata = $currentData['metadata'] ?? [];
        $mergedMetadata = $mergedData['metadata'] ?? [];
        
        $allMetadataKeys = array_unique(array_merge(array_keys($currentMetadata), array_keys($mergedMetadata)));
        foreach ($allMetadataKeys as $key) {
            $current = $currentMetadata[$key] ?? null;
            $merged = $mergedMetadata[$key] ?? null;
            
            if ($current !== $merged) {
                $diff['metadata'][] = [
                    'key' => $key,
                    'current' => $current,
                    'new' => $merged,
                    'action' => $current === null ? 'add' : ($merged === null ? 'remove' : 'update')
                ];
            }
        }

        // Compare sources
        $currentSources = $currentData['sources'] ?? [];
        $mergedSources = $mergedData['sources'] ?? [];
        
        $addedSources = array_diff($mergedSources, $currentSources);
        $removedSources = array_diff($currentSources, $mergedSources);
        
        if (!empty($addedSources)) {
            $diff['sources'][] = [
                'action' => 'add',
                'sources' => array_values($addedSources)
            ];
        }
        
        if (!empty($removedSources)) {
            $diff['sources'][] = [
                'action' => 'remove',
                'sources' => array_values($removedSources)
            ];
        }

        // Compare connections
        $currentConnections = $currentData['connections'] ?? [];
        $mergedConnections = $mergedData['connections'] ?? [];
        
        $allConnectionTypes = array_unique(array_merge(array_keys($currentConnections), array_keys($mergedConnections)));
        
        foreach ($allConnectionTypes as $type) {
            $current = $currentConnections[$type] ?? [];
            $merged = $mergedConnections[$type] ?? [];
            
            $currentNames = array_column($current, 'name');
            $mergedNames = array_column($merged, 'name');
            
            $added = array_diff($mergedNames, $currentNames);
            $removed = array_diff($currentNames, $mergedNames);
            
            if (!empty($added) || !empty($removed)) {
                $diff['connections'][] = [
                    'type' => $type,
                    'added' => array_values($added),
                    'removed' => array_values($removed)
                ];
            }
        }

        return $diff;
    }
}
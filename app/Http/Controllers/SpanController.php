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
use App\Services\YamlValidationService;
use App\Services\SpreadsheetValidationService;

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
        // Require auth for all routes except show, index, search, explore, desertIslandDiscs, explorePlaques, connectionTypes, connectionsByType, showConnection, and listConnections
        $this->middleware('auth')->except(['show', 'index', 'search', 'explore', 'desertIslandDiscs', 'explorePlaques', 'connectionTypes', 'connectionsByType', 'showConnection', 'listConnections']);
        $this->yamlService = $yamlService;
        $this->routeReservationService = $routeReservationService;
    }

    /**
     * Safely log span data without causing deep serialization issues
     */
    private function safeLogSpanData(string $message, Span $span, array $additionalData = []): void
    {
        try {
            // Create a safe representation of the span for logging
            $safeSpanData = [
                'id' => $span->id,
                'name' => $span->name,
                'type_id' => $span->type_id,
                'state' => $span->state,
                'start_year' => $span->start_year,
                'end_year' => $span->end_year,
                'owner_id' => $span->owner_id,
                'access_level' => $span->access_level,
                'connections_count' => $span->connectionsAsSubject()->count() + $span->connectionsAsObject()->count(),
            ];

            Log::info($message, array_merge($safeSpanData, $additionalData));
        } catch (\Exception $e) {
            // If logging fails, just log a minimal message
            Log::warning("Failed to log detailed span data: " . $e->getMessage(), [
                'span_id' => $span->id ?? 'unknown',
                'span_name' => $span->name ?? 'unknown',
                'original_message' => $message
            ]);
        }
    }

    /**
     * Safely log connection data without causing deep serialization issues
     */
    private function safeLogConnectionData(string $message, Connection $connection, array $additionalData = []): void
    {
        try {
            // Create a safe representation of the connection for logging
            $safeConnectionData = [
                'id' => $connection->id,
                'type_id' => $connection->type_id,
                'parent_id' => $connection->parent_id,
                'child_id' => $connection->child_id,
                'connection_span_id' => $connection->connection_span_id,
            ];

            Log::info($message, array_merge($safeConnectionData, $additionalData));
        } catch (\Exception $e) {
            // If logging fails, just log a minimal message
            Log::warning("Failed to log detailed connection data: " . $e->getMessage(), [
                'connection_id' => $connection->id ?? 'unknown',
                'original_message' => $message
            ]);
        }
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
     * Quick create education connection and generate academic year phases (Sep–Jul)
     * Or add phases to existing education connection
     */
    public function quickAddEducation(Request $request)
    {
        // Handle adding phases to existing education connection
        if ($request->input('action') === 'add_phases_to_existing') {
            return $this->addPhasesToExistingEducation($request);
        }

        $validated = $request->validate([
            'person_id' => 'required|uuid|exists:spans,id',
            'organisation_name' => 'required|string|max:255',
            'organisation_id' => 'nullable|uuid|exists:spans,id',
            'start_year' => 'required|integer|min:1800|max:2100',
            'end_year' => 'required|integer|min:1800|max:2100',
        ]);

        $person = Span::findOrFail($validated['person_id']);
        $this->authorize('update', $person);

        if ($person->type_id !== 'person') {
            return response()->json(['success' => false, 'message' => 'Only person spans can have education added'], 422);
        }

        DB::beginTransaction();
        try {
            // Use selected organisation if provided, otherwise find or create by name
            if (!empty($validated['organisation_id'])) {
                $organisation = Span::findOrFail($validated['organisation_id']);
            } else {
                $organisation = Span::firstOrCreate(
                    ['name' => $validated['organisation_name'], 'type_id' => 'organisation'],
                    [
                        'owner_id' => Auth::id(),
                        'updater_id' => Auth::id(),
                        'state' => 'draft',
                        'access_level' => 'private'
                    ]
                );
            }

            // Create connection span for education dates
            $connectionSpan = Span::create([
                'name' => $person->name . ' – education at ' . $organisation->name,
                'type_id' => 'connection',
                'owner_id' => Auth::id(),
                'updater_id' => Auth::id(),
                'state' => 'draft',
                'access_level' => 'private',
                'start_year' => $validated['start_year'],
                'start_month' => 9,
                'start_day' => 1,
                'end_year' => $validated['end_year'],
                'end_month' => 7,
                'end_day' => 31,
                'start_precision' => 'day',
                'end_precision' => 'day'
            ]);

            // Link person to organisation with education connection
            $educationConnection = Connection::create([
                'type_id' => 'education',
                'parent_id' => $person->id,
                'child_id' => $organisation->id,
                'connection_span_id' => $connectionSpan->id,
            ]);

            // Generate academic year phases (Sep to Jul)
            $this->generateAcademicYearPhases($connectionSpan, $person);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('quickAddEducation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Quick add a residence connection
     */
    public function quickAddResidence(Request $request)
    {
        $validated = $request->validate([
            'person_id' => 'required|uuid|exists:spans,id',
            'place_name' => 'required|string|max:255',
            'place_id' => 'nullable|uuid|exists:spans,id',
            'start_year' => 'nullable|integer|min:1800|max:2100',
            'end_year' => 'nullable|integer|min:1800|max:2100',
        ]);

        $person = Span::findOrFail($validated['person_id']);
        $this->authorize('update', $person);

        if ($person->type_id !== 'person') {
            return response()->json(['success' => false, 'message' => 'Only person spans can have residences added'], 422);
        }

        DB::beginTransaction();
        try {
            // Use selected place if provided, otherwise find or create by name
            if (!empty($validated['place_id'])) {
                $place = Span::findOrFail($validated['place_id']);
            } else {
                $place = Span::firstOrCreate(
                    ['name' => $validated['place_name'], 'type_id' => 'place'],
                    [
                        'owner_id' => Auth::id(),
                        'updater_id' => Auth::id(),
                        'state' => 'draft',
                        'access_level' => 'private'
                    ]
                );
            }

            // Create connection span for residence dates if provided
            $connectionSpanData = [
                'name' => $person->name . ' lived in ' . $place->name,
                'type_id' => 'connection',
                'owner_id' => Auth::id(),
                'updater_id' => Auth::id(),
                'state' => 'draft',
                'access_level' => 'private',
            ];

            // Add dates if provided
            if (!empty($validated['start_year'])) {
                $connectionSpanData['start_year'] = $validated['start_year'];
                $connectionSpanData['start_precision'] = 'year';
            }
            if (!empty($validated['end_year'])) {
                $connectionSpanData['end_year'] = $validated['end_year'];
                $connectionSpanData['end_precision'] = 'year';
            }

            $connectionSpan = Span::create($connectionSpanData);

            // Link person to place with residence connection
            $residenceConnection = Connection::create([
                'type_id' => 'residence',
                'parent_id' => $person->id,
                'child_id' => $place->id,
                'connection_span_id' => $connectionSpan->id,
            ]);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('quickAddResidence failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add phases to an existing education connection
     */
    private function addPhasesToExistingEducation(Request $request)
    {
        $validated = $request->validate([
            'connection_span_id' => 'required|uuid|exists:spans,id',
        ]);

        $connectionSpan = Span::findOrFail($validated['connection_span_id']);
        
        // Check if this is an education connection span
        if ($connectionSpan->type_id !== 'connection') {
            return response()->json(['success' => false, 'message' => 'Invalid connection span'], 422);
        }

        // Find the education connection that uses this span
        $educationConnection = Connection::where('connection_span_id', $connectionSpan->id)
            ->where('type_id', 'education')
            ->first();

        if (!$educationConnection) {
            return response()->json(['success' => false, 'message' => 'No education connection found for this span'], 422);
        }

        // Get the person from the education connection
        $person = $educationConnection->parent;
        $this->authorize('update', $person);

        // Check if phases already exist
        $existingPhases = Connection::where('type_id', 'during')
            ->where(function($q) use ($connectionSpan) {
                $q->where('parent_id', $connectionSpan->id)
                  ->orWhere('child_id', $connectionSpan->id);
            })
            ->count();

        if ($existingPhases > 0) {
            return response()->json(['success' => false, 'message' => 'Phases already exist for this education connection'], 422);
        }

        DB::beginTransaction();
        try {
            // Generate phases using the same logic as quickAddEducation
            $this->generateAcademicYearPhases($connectionSpan, $person);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('addPhasesToExistingEducation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate academic year phases for an education connection span
     */
    private function generateAcademicYearPhases(Span $connectionSpan, Span $person)
    {
        $startYear = $connectionSpan->start_year;
        $endYear = $connectionSpan->end_year;

        if (!$startYear || !$endYear) {
            throw new \Exception('Education connection must have start and end years to generate phases');
        }

        // Generate phases for each academic year (Sep to Jul)
        $currentYear = $startYear;
        $yearCounter = 1;

        while ($currentYear < $endYear) {
            $phaseStartYear = $currentYear;
            $phaseEndYear = $currentYear + 1;

            // Create phase span (e.g., "Year 1 - 1990 - Alleyn's")
            $phaseSpan = Span::create([
                'id' => Str::uuid(),
                'name' => "Year {$yearCounter} - {$phaseStartYear} - " . $connectionSpan->name,
                'type_id' => 'phase',
                'state' => 'complete',
                'start_year' => $phaseStartYear,
                'start_month' => 9, // September
                'end_year' => $phaseEndYear,
                'end_month' => 7, // July
                'owner_id' => $person->owner_id,
                'access_level' => $person->access_level,
            ]);

            // Create phase connection span (e.g., "Year 1 - 1990 - Alleyn's (during - Richard Northover - education at Alleyn's)")
            $phaseConnectionSpan = Span::create([
                'id' => Str::uuid(),
                'name' => "Year {$yearCounter} - {$phaseStartYear} - " . $connectionSpan->name . " (during - " . $person->name . " - " . $connectionSpan->name . ")",
                'type_id' => 'connection',
                'state' => 'complete',
                'start_year' => $phaseStartYear,
                'start_month' => 9, // September
                'end_year' => $phaseEndYear,
                'end_month' => 7, // July
                'owner_id' => $person->owner_id,
                'access_level' => $person->access_level,
            ]);

            // Create "during" connection (education_connection_span -> phase_span)
            Connection::create([
                'id' => Str::uuid(),
                'type_id' => 'during',
                'parent_id' => $connectionSpan->id,
                'child_id' => $phaseSpan->id,
                'connection_span_id' => $phaseConnectionSpan->id,
            ]);

            $currentYear++;
            $yearCounter++;
        }
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
    public function show(Request $request, Span $subject): View|\Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
    {
        try {
            // If we're accessing via UUID and a slug exists, redirect to slug URL for consistency
            $routeParam = $request->segment(2); // Get the actual URL segment

            if (Str::isUuid($routeParam) && $subject->slug) {
                if (config('app.debug')) {
                    Log::debug('Span show: redirecting to slug URL', [
                        'from' => $routeParam,
                        'to' => $subject->slug
                    ]);
                }
                
                return redirect()
                    ->route('spans.show', ['subject' => $subject->slug], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // If this is a set span, redirect to the sets route
            if ($subject->type_id === 'set') {
                if (config('app.debug')) {
                    Log::debug('Span show: redirecting set to sets route', [
                        'span_id' => $subject->id,
                        'span_name' => $subject->name,
                        'subtype' => $subject->subtype
                    ]);
                }
                
                return redirect()
                    ->route('sets.show', ['set' => $subject], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // If this is a photo span, redirect to the photos route
            // Redirect place spans to /places route
            if ($subject->type_id === 'place') {
                if (config('app.debug')) {
                    Log::debug('Span show: redirecting place to places route', [
                        'span_id' => $subject->id,
                        'span_name' => $subject->name
                    ]);
                }

                return redirect()
                    ->route('places.show', ['span' => $subject], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            if ($subject->type_id === 'thing' && ($subject->metadata['subtype'] ?? null) === 'photo') {
                if (config('app.debug')) {
                    Log::debug('Span show: redirecting photo to photos route', [
                        'span_id' => $subject->id,
                        'span_name' => $subject->name,
                        'subtype' => $subject->metadata['subtype'] ?? null
                    ]);
                }

                return redirect()
                    ->route('photos.show', ['photo' => $subject], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // Check for global time travel cookie and redirect if it exists
            $timeTravelDate = $request->cookie('time_travel_date');
            
            if ($timeTravelDate) {
                // Validate the date format before redirecting
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $timeTravelDate) && checkdate(
                    (int) substr($timeTravelDate, 5, 2),
                    (int) substr($timeTravelDate, 8, 2),
                    (int) substr($timeTravelDate, 0, 4)
                )) {
                    return redirect()->route('spans.at-date', [
                        'span' => $subject,
                        'date' => $timeTravelDate
                    ]);
                } else {
                    // Invalid date in cookie, clear it
                    $request->cookies->remove('time_travel_date');
                }
            }

            // Check if the span is private and the user is not authenticated
            if ($subject->access_level !== 'public' && !Auth::check()) {
                return redirect()->route('login');
            }

            // Authorize access using the SpanPolicy
            if (Auth::check()) {
                $this->authorize('view', $subject);
            }

            // Validate data integrity: if this span is referenced as a connection_span_id, it must be type 'connection'
            $connectionReference = \App\Models\Connection::where('connection_span_id', $subject->id)->first();
            if ($connectionReference && $subject->type_id !== 'connection') {
                Log::channel('spans')->error('Data integrity issue: Span is referenced as connection_span_id but has wrong type_id', [
                    'span_id' => $subject->id,
                    'span_name' => $subject->name,
                    'span_type_id' => $subject->type_id,
                    'expected_type_id' => 'connection',
                    'connection_id' => $connectionReference->id
                ]);
                // Auto-fix: update the type_id to 'connection'
                $subject->type_id = 'connection';
                $subject->save();
                Log::channel('spans')->info('Auto-fixed span type_id to connection', [
                    'span_id' => $subject->id,
                    'span_name' => $subject->name
                ]);
            }

            // Cache span show data (eager loads + Desert Island Discs) for 5 minutes
            $spanShowCacheKey = 'span_show_data_' . $subject->id;
            $cached = Cache::remember($spanShowCacheKey, 300, function () use ($subject) {
                $subject->load([
                    'type',
                    'owner',
                    'updater',
                ]);
                $desertIslandDiscsSet = null;
                if ($subject->type_id === 'person') {
                    try {
                        $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($subject);
                    } catch (\Exception $e) {
                        Log::warning('Failed to get Desert Island Discs set for person', [
                            'person_id' => $subject->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                return ['span' => $subject, 'desertIslandDiscsSet' => $desertIslandDiscsSet];
            });
            $subject = $cached['span'];
            $desertIslandDiscsSet = $cached['desertIslandDiscsSet'];

            // Additional validation: ensure the type relationship is correct
            if ($subject->type && $subject->type->type_id !== $subject->type_id) {
                Log::channel('spans')->warning('Type relationship mismatch detected', [
                    'span_id' => $subject->id,
                    'span_type_id' => $subject->type_id,
                    'relationship_type_id' => $subject->type->type_id
                ]);
                // Reload the type relationship
                $subject->load('type');
            }

            $span = $subject; // For view compatibility

            // HTTP conditional requests for guest + public span: return 304 if unchanged
            if (!Auth::check() && $subject->access_level === 'public' && $subject->updated_at) {
                $etag = '"' . md5($subject->id . $subject->updated_at->timestamp) . '"';
                $lastModified = $subject->updated_at->format('D, d M Y H:i:s \G\M\T');

                $requestEtag = trim(str_replace('W/', '', $request->header('If-None-Match', '')), ' "');
                if ($requestEtag !== '' && $requestEtag === trim($etag, '"')) {
                    return response('', 304)->withHeaders([
                        'ETag' => $etag,
                        'Last-Modified' => $lastModified,
                    ]);
                }

                $ifModifiedSince = $request->header('If-Modified-Since');
                if ($ifModifiedSince) {
                    $since = \Carbon\Carbon::parse($ifModifiedSince);
                    if ($subject->updated_at->lte($since)) {
                        return response('', 304)->withHeaders([
                            'ETag' => $etag,
                            'Last-Modified' => $lastModified,
                        ]);
                    }
                }

                $response = response()->view('spans.show', compact('span', 'desertIslandDiscsSet'));
                $response->header('ETag', $etag);
                $response->header('Last-Modified', $lastModified);
                $response->header('Cache-Control', 'private, max-age=60');
                return $response;
            }

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
     * Show a span at a specific date (time travel mode)
     */
    public function showAtDate(Request $request, Span $span, string $date): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        try {
            // If we're accessing via UUID and a slug exists, redirect to the slug URL
            $routeParam = $request->segment(2); // Get the actual URL segment
            
            if (Str::isUuid($routeParam) && $span->slug) {
                \Illuminate\Support\Facades\Log::info('Redirecting time travel to slug URL', [
                    'from' => $routeParam,
                    'to' => $span->slug,
                    'date' => $date
                ]);
                
                return redirect()
                    ->route('spans.at-date', ['span' => $span->slug, 'date' => $date], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // Redirect place spans to /places route (places are timeless, so at-date doesn't apply)
            if ($span->type_id === 'place') {
                \Illuminate\Support\Facades\Log::info('Redirecting place span to places route (from at-date)', [
                    'span_id' => $span->id,
                    'span_name' => $span->name
                ]);

                return redirect()
                    ->route('places.show', ['span' => $span], 301)
                    ->with('status', session('status')); // Preserve flash message
            }

            // Parse the date parameter (expecting YYYY-MM-DD format)
            $dateParts = explode('-', $date);
            if (count($dateParts) !== 3) {
                abort(400, 'Date must be in YYYY-MM-DD format');
            }
            
            $year = (int) $dateParts[0];
            $month = (int) $dateParts[1];
            $day = (int) $dateParts[2];

            // Validate date components
            if ($year < 1000 || $year > 2100) {
                abort(400, 'Invalid year');
            }
            if ($month < 1 || $month > 12) {
                abort(400, 'Invalid month');
            }
            if ($day < 1 || $day > 31) {
                abort(400, 'Invalid day');
            }
            
            // Additional validation: check if the date is actually valid
            if (!checkdate($month, $day, $year)) {
                abort(400, 'Invalid date');
            }

            // Check if the span is private and the user is not authenticated
            if ($span->access_level !== 'public' && !Auth::check()) {
                return redirect()->route('login');
            }

            // Authorize access using the SpanPolicy
            if (Auth::check()) {
                $this->authorize('view', $span);
            }

            // Get connections that are ongoing at this date
            $ongoingConnections = $this->getOngoingConnectionsAtDate($span, $year, $month, $day);

            // Format the date for display
            $displayDate = $this->formatDateForDisplay($year, $month, $day);

            // Calculate the span's age at this date
            $ageInfo = $this->calculateSpanAgeAtDate($span, $year, $month, $day);

            // Generate story for this span at this date
            $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
            $story = $storyGenerator->generateStoryAtDate($span, $date);

            // Get leadership roles at this date
            $leadershipService = app(\App\Services\LeadershipRoleService::class);
            $leadership = $leadershipService->getLeadershipAtDate($year, $month, $day);

            // Don't set global time travel cookie - only set it when explicitly using the time travel modal
            // This allows viewing a span at a specific date without affecting the rest of the site

            return response()->view('spans.at-date', compact('span', 'date', 'displayDate', 'ongoingConnections', 'ageInfo', 'story', 'leadership'));
        } catch (AuthorizationException $e) {
            return response()->view('errors.403');
        } catch (\Exception $e) {
            Log::error('Error in spans showAtDate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'span_id' => $span->id,
                'date' => $date
            ]);
            
            if (app()->environment('production')) {
                return response()->view('errors.500');
            } else {
                return response()->view('errors.500', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Get connections that are ongoing at a specific date
     */
    private function getOngoingConnectionsAtDate(Span $span, int $year, int $month, int $day): array
    {
        $ongoingConnections = [];

        // Get all connections where this span is involved
        $connectionsAsSubject = $span->connectionsAsSubject()->with(['child', 'type'])->get();
        $connectionsAsObject = $span->connectionsAsObject()->with(['parent', 'type'])->get();

        // Check outgoing connections (where this span is the subject)
        foreach ($connectionsAsSubject as $connection) {
            if ($this->isConnectionOngoingAtDate($connection, $year, $month, $day)) {
                $ongoingConnections[] = [
                    'connection' => $connection,
                    'other_span' => $connection->child,
                    'direction' => 'outgoing',
                    'type' => $connection->type
                ];
            }
        }

        // Check incoming connections (where this span is the object)
        foreach ($connectionsAsObject as $connection) {
            if ($this->isConnectionOngoingAtDate($connection, $year, $month, $day)) {
                $ongoingConnections[] = [
                    'connection' => $connection,
                    'other_span' => $connection->parent,
                    'direction' => 'incoming',
                    'type' => $connection->type
                ];
            }
        }

        return $ongoingConnections;
    }

    /**
     * Check if a connection is ongoing at a specific date
     */
    private function isConnectionOngoingAtDate(Connection $connection, int $year, int $month, int $day): bool
    {
        // Get the connection span if it exists
        $connectionSpan = $connection->connectionSpan;
        
        if (!$connectionSpan) {
            return false; // No connection span means no temporal data
        }

        // Check if the connection has start/end dates
        $hasStartDate = $connectionSpan->start_year || $connectionSpan->start_month || $connectionSpan->start_day;
        $hasEndDate = $connectionSpan->end_year || $connectionSpan->end_month || $connectionSpan->end_day;

        if (!$hasStartDate && !$hasEndDate) {
            return false; // No temporal data
        }

        // Create the target date for comparison
        $targetDate = \Carbon\Carbon::create($year, $month, $day, 12, 0, 0); // Use noon to avoid timezone issues

        // Get the expanded date ranges based on precision
        $startRange = $connectionSpan->getStartDateRange();
        $endRange = $connectionSpan->getEndDateRange();

        // Check if the target date falls within the connection's date range
        if ($startRange[0] && $targetDate < $startRange[0]) {
            return false; // Connection hasn't started yet
        }

        if ($endRange[1] && $targetDate > $endRange[1]) {
            return false; // Connection has already ended
        }

        return true; // Connection is ongoing at this date
    }

    /**
     * Format date for display
     */
    private function formatDateForDisplay(int $year, int $month, int $day): string
    {
        return date('j F Y', mktime(0, 0, 0, $month, $day, $year));
    }

    /**
     * Calculate the span's age at a specific date
     */
    private function calculateSpanAgeAtDate(Span $span, int $year, int $month, int $day): array
    {
        $targetDate = (object)[
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];

        // Check if span has a start date
        if (!$span->start_year) {
            return [
                'status' => 'no_start_date',
                'message' => "wasn't alive (no birth date recorded)"
            ];
        }

        $startDate = (object)[
            'year' => $span->start_year,
            'month' => $span->start_month ?? 1,
            'day' => $span->start_day ?? 1,
        ];

        // Check if target date is before span's start
        if ($year < $startDate->year || 
            ($year == $startDate->year && $month < $startDate->month) ||
            ($year == $startDate->year && $month == $startDate->month && $day < $startDate->day)) {
            return [
                'status' => 'before_birth',
                'message' => "wasn't alive yet"
            ];
        }

        // Check if span has an end date and if target date is after it
        if ($span->end_year) {
            $endDate = (object)[
                'year' => $span->end_year,
                'month' => $span->end_month ?? 1,
                'day' => $span->end_day ?? 1,
            ];

            if ($year > $endDate->year || 
                ($year == $endDate->year && $month > $endDate->month) ||
                ($year == $endDate->year && $month == $endDate->month && $day > $endDate->day)) {
                return [
                    'status' => 'after_death',
                    'message' => "wasn't alive anymore"
                ];
            }
        }

        // Calculate age at target date
        $age = \App\Helpers\DateDurationCalculator::calculateDuration($startDate, $targetDate);
        
        if (!$age) {
            return [
                'status' => 'error',
                'message' => "age calculation error"
            ];
        }

        // Format age message
        $ageParts = [];
        if ($age['years'] > 0) {
            $ageParts[] = $age['years'] . ' year' . ($age['years'] != 1 ? 's' : '');
        }
        if ($age['months'] > 0) {
            $ageParts[] = $age['months'] . ' month' . ($age['months'] != 1 ? 's' : '');
        }
        if ($age['days'] > 0) {
            $ageParts[] = $age['days'] . ' day' . ($age['days'] != 1 ? 's' : '');
        }

        if (empty($ageParts)) {
            $ageParts[] = '0 days';
        }

        return [
            'status' => 'alive',
            'message' => 'was ' . implode(', ', $ageParts) . ' old',
            'age' => $age
        ];
    }

    /**
     * Exit time travel mode - clear cookie and redirect to present
     */
    public function exitTimeTravel(Request $request, Span $span): \Illuminate\Http\RedirectResponse
    {
        // Clear the global time travel cookie
        $cookie = cookie('time_travel_date', null, -1); // Expire immediately

        return redirect()->route('spans.show', $span)
            ->withCookie($cookie);
    }

    /**
     * Global exit time travel mode - clear cookie and redirect to current page
     */
    public function exitTimeTravelGlobal(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Clear the global time travel cookie
        $cookie = cookie('time_travel_date', null, -1); // Expire immediately

        // Try to get the current URL path
        $path = $request->path();
        
        // Check if we're on a span at-date page
        if (preg_match('/^spans\/([^\/]+)\/at\/\d{4}-\d{2}-\d{2}$/', $path, $matches)) {
            $spanIdentifier = $matches[1];
            return redirect()->route('spans.show', ['subject' => $spanIdentifier])->withCookie($cookie);
        }
        
        // Check if we're on a date exploration page
        if (preg_match('/^date\/\d{4}(-\d{2}(-\d{2})?)?$/', $path)) {
            return redirect('/' . $path)->withCookie($cookie);
        }
        
        // Check if we're on an explore page
        if (preg_match('/^explore\//', $path)) {
            return redirect('/' . $path)->withCookie($cookie);
        }
        
        // For spans index or any other page, redirect to homepage
        return redirect()->route('home')->withCookie($cookie);
    }

    /**
     * Toggle time travel mode - if active, exit; if inactive, show modal
     */
    public function toggleTimeTravel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $timeTravelDate = $request->cookie('time_travel_date');
        
        if ($timeTravelDate) {
            // Time travel is active, exit it
            $cookie = cookie('time_travel_date', null, -1); // Expire immediately
            
            // Try to get the span from the referrer URL
            $referrer = $request->headers->get('referer');
            if ($referrer) {
                $path = parse_url($referrer, PHP_URL_PATH);
                
                // Check if we're on a span page
                if ($path && preg_match('/^\/spans\/([^\/]+)(?:\/at\/\d{4}-\d{2}-\d{2})?$/', $path, $matches)) {
                    $spanIdentifier = $matches[1];
                    return redirect()->route('spans.show', ['subject' => $spanIdentifier])->withCookie($cookie);
                }
                
                // Check if we're on the homepage
                if ($path === '/') {
                    return redirect()->route('home')->withCookie($cookie);
                }
                
                // Check if we're on the spans index
                if ($path === '/spans') {
                    return redirect()->route('spans.index')->withCookie($cookie);
                }
                
                // Check if we're on other pages (explore, date exploration, etc.)
                if ($path && preg_match('/^\/(explore|date)\//', $path)) {
                    // Redirect to the same page without time travel
                    return redirect($path)->withCookie($cookie);
                }
                
                // For any other page, redirect to homepage
                return redirect()->route('home')->withCookie($cookie);
            }
            
            return redirect()->route('home')->withCookie($cookie);
        } else {
            // Time travel is inactive, redirect to modal
            return redirect()->route('time-travel.modal');
        }
    }

    /**
     * Show time travel modal
     */
    public function showTimeTravelModal(Request $request): \Illuminate\View\View
    {
        return view('modals.time-travel');
    }

    /**
     * Start time travel with selected date
     */
    public function startTimeTravel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'travel_day' => 'required|integer|min:1|max:31',
            'travel_month' => 'required|integer|min:1|max:12',
            'travel_year' => 'required|integer|min:1000|max:9999'
        ]);

        $day = $request->input('travel_day');
        $month = $request->input('travel_month');
        $year = $request->input('travel_year');

        // Validate that the date is actually valid
        if (!checkdate($month, $day, $year)) {
            return back()->withErrors(['travel_day' => 'Invalid date. Please check your day, month, and year.']);
        }

        // Format the date as YYYY-MM-DD
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        
        // Set the global time travel cookie
        $cookie = cookie('time_travel_date', $date, 60 * 24 * 30); // 30 days
        
        // Try to determine the current span from the referrer URL
        $referrer = $request->headers->get('referer');
        if ($referrer) {
            $path = parse_url($referrer, PHP_URL_PATH);
            if ($path && preg_match('/^\/spans\/([^\/]+)(?:\/at\/\d{4}-\d{2}-\d{2})?$/', $path, $matches)) {
                $spanIdentifier = $matches[1];
                return redirect()->route('spans.at-date', ['span' => $spanIdentifier, 'date' => $date])
                    ->withCookie($cookie);
            }
        }
        
        // Fallback to date exploration page if we can't determine the current span
        return redirect()->route('date.explore', ['date' => $date])
            ->withCookie($cookie);
    }

    /**
     * Show the new comparison page
     */
    public function compare(Span $span)
    {
        // If this is a photo span, redirect to the photos compare route
        if ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo') {
            return redirect()
                ->route('photos.compare', ['photo' => $span], 301)
                ->with('status', session('status')); // Preserve flash message
        }

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
     * 
     * This method works for both regular spans and connection spans.
     * Connection spans (type_id = 'connection') are spans that represent relationships
     * between other spans. The edit view automatically shows connection-specific fields
     * when editing a connection span via the metadata form component.
     */
    public function edit(Span $span)
    {
        // If this is a photo span, redirect to the photos edit route
        if ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo') {
            return redirect()
                ->route('photos.edit', ['photo' => $span], 301)
                ->with('status', session('status')); // Preserve flash message
        }

        $this->authorize('update', $span);

        $spanTypes = SpanType::all();
        
        // Log what span types we're getting
        Log::channel('spans')->info('SpanTypes loaded for edit page', [
            'span_id' => $span->id,
            'total_span_types_count' => $spanTypes->count(),
            'span_type_ids' => $spanTypes->pluck('type_id')->toArray(),
            'has_connection_type' => $spanTypes->contains('type_id', 'connection'),
            'connection_type_details' => $spanTypes->firstWhere('type_id', 'connection')?->toArray() ?? 'not_found'
        ]);
        
        $connectionTypes = ConnectionType::orderBy('forward_predicate')->get();
        
        // Get available spans, but ensure we include the current connection's object if it exists
        // This is important for connection spans where the object might not be in the first 100 results
        $availableSpans = Span::where('id', '!=', $span->id)
            ->with('type')
            ->orderBy('name')
            ->limit(100) // Limit to prevent memory exhaustion
            ->get();
        
        // If this is a connection span, ensure the connection's object is included in availableSpans
        if ($span->type_id === 'connection') {
            $connection = \App\Models\Connection::where('connection_span_id', $span->id)
                ->with(['object.type'])
                ->first();
            
            if ($connection && $connection->object && !$availableSpans->contains('id', $connection->object->id)) {
                // Add the object to the collection if it's not already there
                $availableSpans->push($connection->object);
            }
        }
        
        // Get the current span type, or if type_id is provided in the query, get that type
        // Use the span's type_id directly to ensure we get the correct type, especially for connection spans
        $requestedTypeId = request('type_id');
        $actualTypeId = $span->type_id;
        
        // Check if this span is referenced as a connection_span_id - if so, it MUST be type 'connection'
        $isReferencedAsConnection = \App\Models\Connection::where('connection_span_id', $span->id)->exists();
        
        // Log detailed information for debugging
        Log::channel('spans')->info('Loading edit page', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'span_slug' => $span->slug,
            'actual_type_id' => $actualTypeId,
            'requested_type_id' => $requestedTypeId,
            'is_referenced_as_connection' => $isReferencedAsConnection,
            'span_type_from_relationship' => $span->type?->type_id ?? 'null',
            'span_type_from_relationship_name' => $span->type?->name ?? 'null',
            'span_raw_attributes' => [
                'type_id' => $span->getRawOriginal('type_id') ?? 'not_set',
                'name' => $span->getRawOriginal('name') ?? 'not_set'
            ]
        ]);
        
        // If this span is referenced as a connection but has wrong type_id, log the issue
        if ($isReferencedAsConnection && $actualTypeId !== 'connection') {
            Log::channel('spans')->error('DATA INTEGRITY ISSUE: Span is referenced as connection_span_id but type_id is wrong', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'actual_type_id' => $actualTypeId,
                'expected_type_id' => 'connection',
                'connection_count' => \App\Models\Connection::where('connection_span_id', $span->id)->count()
            ]);
        }
        
        $spanType = $requestedTypeId 
            ? SpanType::where('type_id', $requestedTypeId)->firstOrFail() 
            : SpanType::where('type_id', $actualTypeId)->first();
        
        // Fallback to the relationship if direct lookup fails (shouldn't happen, but be safe)
        if (!$spanType) {
            Log::channel('spans')->warning('SpanType not found by direct lookup, using relationship', [
                'span_id' => $span->id,
                'type_id' => $actualTypeId
            ]);
            $spanType = $span->type;
        }
        
        // Log what we're actually using
        if ($spanType) {
            Log::channel('spans')->info('SpanType determined for edit page', [
                'span_id' => $span->id,
                'spanType_type_id' => $spanType->type_id,
                'spanType_name' => $spanType->name,
                'matches_actual_type_id' => $spanType->type_id === $actualTypeId
            ]);
        }

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
            // Note: Connection spans cannot change type - they must always be 'connection'
            $requestedTypeId = $request->input('type_id');
            $currentTypeId = $span->type_id;
            
            if ($request->has('type_id') && $requestedTypeId !== null && (string)$requestedTypeId !== (string)$currentTypeId) {
                // Prevent type changes for connection spans
                if ($span->type_id === 'connection') {
                    return back()
                        ->withInput()
                        ->withErrors(['type_id' => 'Connection spans cannot be changed to a different type. They must always remain as "connection".']);
                }

                // Check if transitionToType method exists (it may not be implemented yet)
                if (method_exists($span, 'transitionToType')) {
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
                } else {
                    // Method doesn't exist - for now, just update the type directly
                    // This is a simplified approach until transitionToType is implemented
                    Log::channel('spans')->warning('Type transition method not available, updating type directly', [
                        'span_id' => $span->id,
                        'old_type' => $span->type_id,
                        'new_type' => $request->type_id
                    ]);
                    
                    $span->type_id = $request->type_id;
                    // Continue with normal update flow below
                }
            }

            // If connection fields are provided, update the connection
            if ($request->has(['subject_id', 'object_id', 'connection_type'])) {
                // Get the connection where this span is the connection span
                $connection = Connection::where('connection_span_id', $span->id)->first();
                if (!$connection) {
                    return back()
                        ->withInput()
                        ->withErrors(['error' => 'Connection not found. This connection span does not have an associated connection record.']);
                }

                try {
                    // Update the connection
                    $connection->update([
                        'parent_id' => $validated['subject_id'],
                        'child_id' => $validated['object_id'],
                        'type_id' => $validated['connection_type']
                    ]);

                    // Refresh the connection to get updated relationships
                    $connection->refresh();

                    // Get the updated connection type and spans
                    $connectionType = $connection->type;
                    $subject = $connection->subject;
                    $object = $connection->object;

                    if (!$connectionType) {
                        return back()
                            ->withInput()
                            ->withErrors(['connection_type' => 'The selected connection type does not exist.']);
                    }

                    if (!$subject) {
                        return back()
                            ->withInput()
                            ->withErrors(['subject_id' => 'The selected subject (parent) span does not exist.']);
                    }

                    if (!$object) {
                        return back()
                            ->withInput()
                            ->withErrors(['object_id' => 'The selected object (child) span does not exist.']);
                    }

                    // Update the span name in SPO format
                    $validated['name'] = "{$subject->name} {$connectionType->forward_predicate} {$object->name}";
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle database constraint violations
                    $errorCode = $e->getCode();
                    if ($errorCode == 23505) { // PostgreSQL unique violation
                        return back()
                            ->withInput()
                            ->withErrors(['error' => 'A connection with these properties already exists.']);
                    }
                    throw $e; // Re-throw if it's not a constraint violation we can handle
                }
            }

            // Preserve metadata keys not in the form, and protect structured geolocation from
            // being overwritten by schema text fields (e.g. place type has a "coordinates" text
            // input that would otherwise replace the latitude/longitude array).
            if (array_key_exists('metadata', $validated)) {
                $existingMetadata = $span->metadata ?? [];
                $requestMetadata = $validated['metadata'] ?? [];
                $merged = array_replace_recursive($existingMetadata, $requestMetadata);

                // Restore structured geolocation keys if the form overwrote them with a scalar/empty value.
                // The place type schema has a "coordinates" text field; submitting it overwrites the
                // latitude/longitude array, so we restore when the merged value is not a valid structure.
                $geolocationKeys = ['coordinates', 'osm_data', 'external_refs'];
                foreach ($geolocationKeys as $key) {
                    $existingValue = $existingMetadata[$key] ?? null;
                    $mergedValue = $merged[$key] ?? null;
                    $existingIsValid = is_array($existingValue) && !empty($existingValue);
                    $mergedIsInvalid = is_string($mergedValue) || $mergedValue === null
                        || (is_array($mergedValue) && empty($mergedValue));
                    if ($existingIsValid && $mergedIsInvalid) {
                        $merged[$key] = $existingValue;
                    }
                }

                $validated['metadata'] = $merged;
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so they're handled by Laravel's default handler
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::channel('spans')->error('Model not found when updating span', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'One or more referenced records (spans, connection types, etc.) could not be found. Please refresh the page and try again.']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database errors
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            Log::channel('spans')->error('Database error when updating span', [
                'span_id' => $span->id,
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
                'driver_code' => $e->errorInfo[1] ?? null
            ]);

            // Provide specific messages for common database errors
            if (strpos($errorMessage, 'duplicate key') !== false || strpos($errorMessage, 'UNIQUE constraint') !== false) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'This record already exists. The name, slug, or another unique field conflicts with an existing record.']);
            }

            if (strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'FOREIGN KEY constraint') !== false) {
                return back()
                    ->withInput()
                    ->withErrors(['error' => 'Cannot update: this record is referenced by other records. Please check connections and relationships.']);
            }

            // Generic database error
            $userMessage = config('app.debug') 
                ? "Database error: " . $errorMessage
                : 'A database error occurred while saving. Please check your input and try again.';

            return back()
                ->withInput()
                ->withErrors(['error' => $userMessage]);
        } catch (\Exception $e) {
            // Log any other errors that occur
            Log::channel('spans')->error('Error updating span', [
                'span_id' => $span->id,
                'span_type' => $span->type_id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Provide more specific error messages based on the exception type
            $errorMessage = 'An error occurred while saving the span.';
            
            if ($span->type_id === 'connection') {
                $errorMessage = 'An error occurred while saving the connection. ';
                
                if (strpos($e->getMessage(), 'subject') !== false || strpos($e->getMessage(), 'object') !== false) {
                    $errorMessage .= 'There was a problem with the connection\'s subject or object. Please check that both spans exist.';
                } elseif (strpos($e->getMessage(), 'connection_type') !== false || strpos($e->getMessage(), 'type') !== false) {
                    $errorMessage .= 'There was a problem with the connection type. Please verify the connection type is valid.';
                } else {
                    $errorMessage .= 'Please check the connection details and try again.';
                }
            }

            // In debug mode, show the actual error message
            if (config('app.debug')) {
                $errorMessage .= ' Error: ' . $e->getMessage();
            }

            return back()
                ->withInput()
                ->withErrors(['error' => $errorMessage]);
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
            
            return redirect()->route('home')
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

        // Get leadership roles and display date for the current precision (day, month, or year)
        $leadership = null;
        $displayDate = null;
        $familyAgesAtDate = collect();
        $leadershipService = app(\App\Services\LeadershipRoleService::class);

        if ($precision === 'day' && $month !== null && $day !== null) {
            $leadership = $leadershipService->getLeadershipAtDate($year, $month, $day);
            $displayDate = $this->formatDateForDisplay($year, $month, $day);
        } elseif ($precision === 'month' && $month !== null) {
            $periodStart = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfDay();
            $periodEnd = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
            $leadership = $leadershipService->getLeadershipInPeriod($periodStart, $periodEnd);
            $displayDate = $periodStart->format('F Y');
        } elseif ($precision === 'year') {
            $periodStart = \Carbon\Carbon::createFromDate($year, 1, 1)->startOfDay();
            $periodEnd = \Carbon\Carbon::createFromDate($year, 12, 31)->endOfDay();
            $leadership = $leadershipService->getLeadershipInPeriod($periodStart, $periodEnd);
            $displayDate = (string) $year;
        }

        if ($precision === 'day' && $month !== null && $day !== null) {
            // Family members alive at this date, grouped as on the span family card (signed-in user only)
            $user = Auth::user();
            if ($user && $user->personalSpan) {
                $personalSpan = $user->personalSpan;
                $targetDate = \Carbon\Carbon::createFromDate($year, $month, $day)->startOfDay();
                $addAliveMembersWithAge = function ($spans) use ($targetDate) {
                    $result = collect();
                    foreach ($spans as $member) {
                        if ($member->type_id !== 'person' || $member->start_year === null) {
                            continue;
                        }
                        $birthDate = \Carbon\Carbon::create(
                            $member->start_year,
                            $member->start_month ?? 1,
                            $member->start_day ?? 1
                        )->startOfDay();
                        if ($targetDate->lt($birthDate)) {
                            continue;
                        }
                        $endDate = null;
                        if ($member->end_year !== null) {
                            $endDate = \Carbon\Carbon::create(
                                $member->end_year,
                                $member->end_month ?? 12,
                                $member->end_day ?? 31
                            )->startOfDay();
                        }
                        if ($endDate !== null && !$targetDate->lte($endDate)) {
                            continue;
                        }
                        $result->push(['span' => $member, 'age' => $birthDate->diffInYears($targetDate)]);
                    }
                    return $result->sortByDesc('age')->values();
                };
                $ancestors = $personalSpan->ancestors(3);
                $descendants = $personalSpan->descendants(2);
                $groups = [
                    ['title' => 'Great-Grandparents', 'members' => $addAliveMembersWithAge($ancestors->where('generation', 3)->pluck('span'))],
                    ['title' => 'Grandparents', 'members' => $addAliveMembersWithAge($ancestors->where('generation', 2)->pluck('span'))],
                    ['title' => 'Parents', 'members' => $addAliveMembersWithAge($ancestors->where('generation', 1)->pluck('span'))],
                    ['title' => 'Uncles & Aunts', 'members' => $addAliveMembersWithAge($personalSpan->unclesAndAunts())],
                    ['title' => 'Siblings', 'members' => $addAliveMembersWithAge($personalSpan->siblings())],
                    ['title' => 'Cousins', 'members' => $addAliveMembersWithAge($personalSpan->cousins())],
                    ['title' => 'You', 'members' => $addAliveMembersWithAge(collect([$personalSpan]))],
                    ['title' => 'Children', 'members' => $addAliveMembersWithAge($descendants->where('generation', 1)->pluck('span'))],
                    ['title' => 'Nephews & Nieces', 'members' => $addAliveMembersWithAge($personalSpan->nephewsAndNieces())],
                    ['title' => 'Extra Nephews & Nieces', 'members' => $addAliveMembersWithAge($personalSpan->extraNephewsAndNieces())],
                    ['title' => 'Grandchildren', 'members' => $addAliveMembersWithAge($descendants->where('generation', 2)->pluck('span'))],
                ];
                foreach ($groups as $group) {
                    if ($group['members']->isNotEmpty()) {
                        $familyAgesAtDate->push($group);
                    }
                }
            }
        }

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
                'day',
                'leadership',
                'displayDate',
                'familyAgesAtDate'
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
            // Admins can see all spans
            if (!$user->is_admin) {
                // Authenticated user - can see public, owned, and shared spans (including group permissions)
                $spans->where(function ($q) use ($user) {
                    $q->where('access_level', 'public')
                        ->orWhere('owner_id', $user->id)
                        ->orWhere(function ($q) use ($user) {
                            $q->where('access_level', 'shared')
                                ->whereHas('spanPermissions', function ($q) use ($user) {
                                    $q->where(function($subQ) use ($user) {
                                        // Direct user permissions
                                        $subQ->where('user_id', $user->id)
                                             // Group permissions
                                             ->orWhereHas('group', function($groupQ) use ($user) {
                                                 $groupQ->whereHas('users', function($userQ) use ($user) {
                                                     $userQ->where('users.id', $user->id);
                                                 });
                                             });
                                    });
                                });
                        });
                });
            }
            // If admin, no restrictions - can see all spans
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

        // Order by connection count (richer spans first)
        $spans->withCount(['connectionsAsSubject', 'connectionsAsObject'])
            ->orderByRaw(
                '(SELECT COUNT(*) FROM connections WHERE connections.parent_id = spans.id) + ' .
                '(SELECT COUNT(*) FROM connections WHERE connections.child_id = spans.id) DESC'
            );

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
                    'state' => $span->state,
                    'subtype' => $span->subtype
                ];
            });

        return response()->json([
            'spans' => $results
        ]);
    }

    /**
     * Update only the description of a span
     */
    public function updateDescription(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $validated = $request->validate([
            'description' => 'nullable|string|max:10000'
        ]);
        
        try {
            $span->description = $validated['description'];
            $span->updater_id = Auth::id();
            $span->save();
            
            Log::info('Span description updated', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'updated_by' => Auth::id(),
                'description_length' => strlen($validated['description'] ?? '')
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Description updated successfully',
                'description' => $span->description
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update span description', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update description: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get YAML content for a span
     */
    public function getYaml(Span $span)
    {
        $this->authorize('view', $span);
        
        try {
            // Use the safe serialization method to prevent timeouts
            $yamlContent = $this->yamlService->spanToYamlSafe($span);
            
            return response()->json([
                'success' => true,
                'yaml_content' => $yamlContent
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate YAML for span', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
            
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
     * Show the spreadsheet editor for a span
     */
    public function spreadsheetEditor(Span $span)
    {
        $this->authorize('update', $span);
        
        // Eager load both outgoing and incoming connections
        $span->load([
            'owner', 'updater', 'connectionsAsSubject.type', 'connectionsAsSubject.object', 'connectionsAsSubject.connectionSpan',
            'connectionsAsObject.type', 'connectionsAsObject.subject', 'connectionsAsObject.connectionSpan'
        ]);
        
        // Prepare span data for the spreadsheet editor
        $spanData = [
            'id' => $span->id,
            'slug' => $span->slug,
            'name' => $span->getRawName(), // Use raw name to avoid time-aware resolution during data loading
            'type' => $span->type->type_id ?? '',
            'state' => $span->state,
            'start_year' => $span->start_year,
            'start_month' => $span->start_month,
            'start_day' => $span->start_day,
            'end_year' => $span->end_year,
            'end_month' => $span->end_month,
            'end_day' => $span->end_day,
            'description' => $span->description,
            'notes' => $span->notes,
            'access_level' => $span->access_level,
            'metadata' => $span->metadata ?? (object)[],
            // Extract common metadata fields for core fields table
            'subtype' => $span->metadata['subtype'] ?? null,
            // System fields
            'created_at' => $span->created_at?->toISOString(),
            'updated_at' => $span->updated_at?->toISOString(),
            'updated_by' => $span->updater?->name ?? '',
            'owner' => $span->owner?->name ?? '',
            'connections' => collect()
                // Outgoing connections (span is subject)
                ->concat($span->connectionsAsSubject->map(function($conn) use ($span) {
                    return [
                        'subject' => $span->getRawName(),
                        'subject_id' => $span->id,
                        'predicate' => $conn->type->type,
                        'object' => $conn->object ? $conn->object->getRawName() : '',
                        'object_id' => $conn->object_id,
                        'direction' => 'outgoing',
                        'start_year' => $conn->connectionSpan?->start_year,
                        'start_month' => $conn->connectionSpan?->start_month,
                        'start_day' => $conn->connectionSpan?->start_day,
                        'end_year' => $conn->connectionSpan?->end_year,
                        'end_month' => $conn->connectionSpan?->end_month,
                        'end_day' => $conn->connectionSpan?->end_day,
                        'metadata' => $conn->connectionSpan?->metadata ?? (object)[]
                    ];
                }))
                // Incoming connections (span is object)
                ->concat($span->connectionsAsObject->map(function($conn) use ($span) {
                    return [
                        'subject' => $conn->subject ? $conn->subject->getRawName() : '',
                        'subject_id' => $conn->subject_id,
                        'predicate' => $conn->type->type,
                        'object' => $span->getRawName(),
                        'object_id' => $span->id,
                        'direction' => 'incoming',
                        'start_year' => $conn->connectionSpan?->start_year,
                        'start_month' => $conn->connectionSpan?->start_month,
                        'start_day' => $conn->connectionSpan?->start_day,
                        'end_year' => $conn->connectionSpan?->end_year,
                        'end_month' => $conn->connectionSpan?->end_month,
                        'end_day' => $conn->connectionSpan?->end_day,
                        'metadata' => $conn->connectionSpan?->metadata ?? (object)[]
                    ];
                }))
                ->toArray()
        ];
        
        // If this span is a connection span, load connection details
        if ($span->type_id === 'connection') {
            $connection = Connection::where('connection_span_id', $span->id)
                ->with(['subject', 'object', 'type'])
                ->first();
            
            if ($connection) {
                $spanData['connection_details'] = [
                    'type_id' => $connection->type_id,
                    'subject_id' => $connection->parent_id,
                    'subject_name' => $connection->subject?->getRawName() ?? '',
                    'subject_type' => $connection->subject?->type_id ?? null,
                    'subject_subtype' => $connection->subject?->metadata['subtype'] ?? null,
                    'object_id' => $connection->child_id,
                    'object_name' => $connection->object?->getRawName() ?? '',
                    'object_type' => $connection->object?->type_id ?? null,
                    'object_subtype' => $connection->object?->metadata['subtype'] ?? null,
                ];
            } else {
                $spanData['connection_details'] = null;
            }
        } else {
            $spanData['connection_details'] = null;
        }
        
        // Get all connection types and span types for help text
        $connectionTypes = ConnectionTypeModel::orderBy('type')->get();
        $spanTypes = SpanType::orderBy('type_id')->get();
        
        // Get complete span type metadata for dynamic field handling
        $spanTypeMetadata = [];
        foreach ($spanTypes as $type) {
            $spanTypeMetadata[$type->type_id] = [
                'metadata' => $type->metadata ?? [],
                'schema' => $type->metadata['schema'] ?? []
            ];
        }
        
        // Get complete connection type metadata for connection details
        $connectionTypeMetadata = [];
        foreach ($connectionTypes as $connectionType) {
            $connectionTypeMetadata[$connectionType->type] = [
                'forward_predicate' => $connectionType->forward_predicate,
                'inverse_predicate' => $connectionType->inverse_predicate,
                'allowed_span_types' => $connectionType->metadata['allowed_span_types'] ?? null,
            ];
        }
        
        return view('spans.spreadsheet-editor', compact('span', 'spanData', 'connectionTypes', 'spanTypes', 'spanTypeMetadata', 'connectionTypeMetadata'));
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
    public function history(Request $request, Span $span, ?int $version = null): View
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
        
        // Handle version selection - default to most recent if not specified
        $versionModel = null;
        $previousVersion = null;
        $changes = [];
        
        if ($version !== null) {
            $versionModel = $span->getVersion($version);
            if (!$versionModel) {
                abort(404, 'Version not found');
            }
            
            // Get the previous version for comparison
            $previousVersion = $span->versions()
                ->where('version_number', '<', $version)
                ->orderByDesc('version_number')
                ->first();
            
            if ($previousVersion) {
                $changes = $versionModel->getDiffFrom($previousVersion);
            }
        } else {
            // Default to most recent version
            $versionModel = $versions->first();
            if ($versionModel) {
                $previousVersion = $span->versions()
                    ->where('version_number', '<', $versionModel->version_number)
                    ->orderByDesc('version_number')
                    ->first();
                
                if ($previousVersion) {
                    $changes = $versionModel->getDiffFrom($previousVersion);
                }
            }
        }
        
        return view('spans.history', compact('span', 'allChanges', 'versions', 'versionModel', 'previousVersion', 'changes'));
    }

    /**
     * Display a specific version of a span with changes from the previous version.
     * Redirects to history page with version parameter for unified view.
     */
    public function showVersion(Request $request, Span $span, int $version): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('spans.history', [$span, $version]);
    }

    /**
     * Display a story for a span.
     */
    public function story(Request $request, Span $span): View|\Illuminate\Http\RedirectResponse
    {
        // If this is a photo span, redirect to the photos story route
        if ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo') {
            return redirect()
                ->route('photos.story', ['photo' => $span], 301)
                ->with('status', session('status')); // Preserve flash message
        }

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        return view('spans.story', compact('span', 'story'));
    }

    /**
     * Display the explore showcase page.
     */
    public function explore(Request $request): View
    {
        return view('explore.index');
    }

    /**
     * Display films exploration page with force-directed graph
     */
    public function exploreFilms(Request $request): View
    {
        // Get all films (public or accessible to user)
        $filmsQuery = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'film');
        
        // Apply access filtering
        if (!Auth::check()) {
            $filmsQuery->where('access_level', 'public');
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
                $filmsQuery->where(function ($query) use ($user) {
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
        
        $films = $filmsQuery->with([
            'connectionsAsSubject' => function($q) {
                $q->where('type_id', 'features')
                  ->whereHas('child', function($q2) {
                      $q2->where('type_id', 'person');
                  })
                  ->with(['child:id,name,type_id']);
            },
            'connectionsAsSubject.child',
            'connectionsAsObject' => function($q) {
                $q->where('type_id', 'created')
                  ->whereHas('parent', function($q2) {
                      $q2->where('type_id', 'person');
                  })
                  ->with(['parent:id,name,type_id']);
            },
            'connectionsAsObject.parent'
        ])->get();
        
        // Build graph data
        $nodes = [];
        $links = [];
        $nodeMap = []; // Map span IDs to node indices
        
        // Add film nodes
        foreach ($films as $film) {
            $nodeId = 'film_' . $film->id;
            $nodeMap[$film->id] = count($nodes);
            $nodes[] = [
                'id' => $nodeId,
                'span_id' => $film->id,
                'name' => $film->name,
                'type' => 'film',
                'type_id' => 'thing',
                'url' => route('spans.show', ['subject' => $film])
            ];
            
            // Add "features" connections (film -> features -> person)
            foreach ($film->connectionsAsSubject as $conn) {
                if ($conn->type_id === 'features' && $conn->child && $conn->child->type_id === 'person') {
                    $person = $conn->child;
                    $personNodeId = 'person_' . $person->id;
                    
                    // Add person node if not already added
                    if (!isset($nodeMap[$person->id])) {
                        $nodeMap[$person->id] = count($nodes);
                        $nodes[] = [
                            'id' => $personNodeId,
                            'span_id' => $person->id,
                            'name' => $person->name,
                            'type' => 'person',
                            'type_id' => 'person',
                            'url' => route('spans.show', ['subject' => $person])
                        ];
                    }
                    
                    // Add link (using node indices)
                    $links[] = [
                        'source' => $nodeMap[$film->id],
                        'target' => $nodeMap[$person->id],
                        'type' => 'features',
                        'type_id' => 'features',
                        'source_id' => $nodeId,
                        'target_id' => $personNodeId
                    ];
                }
            }
            
            // Add "created" connections (person -> created -> film)
            foreach ($film->connectionsAsObject as $conn) {
                if ($conn->type_id === 'created' && $conn->parent && $conn->parent->type_id === 'person') {
                    $person = $conn->parent;
                    $personNodeId = 'person_' . $person->id;
                    
                    // Add person node if not already added
                    if (!isset($nodeMap[$person->id])) {
                        $nodeMap[$person->id] = count($nodes);
                        $nodes[] = [
                            'id' => $personNodeId,
                            'span_id' => $person->id,
                            'name' => $person->name,
                            'type' => 'person',
                            'type_id' => 'person',
                            'url' => route('spans.show', ['subject' => $person])
                        ];
                    }
                    
                    // Add link (using node indices)
                    $links[] = [
                        'source' => $nodeMap[$person->id],
                        'target' => $nodeMap[$film->id],
                        'type' => 'created',
                        'type_id' => 'created',
                        'source_id' => $personNodeId,
                        'target_id' => $nodeId
                    ];
                }
            }
        }
        
        $graphData = [
            'nodes' => $nodes,
            'links' => $links
        ];
        
        return view('explore.films', compact('graphData'));
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
            
            // Preload album data for each track to avoid N+1 queries
            $tracks->each(function($track) {
                $track->cached_album = $track->getContainingAlbum();
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
     * Display all plaques on a map.
     */
    public function explorePlaques(Request $request): View
    {
        // Get all plaque spans
        $query = Span::query()
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'plaque')
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

        $plaques = $query->get();

        // Get location data for each plaque
        $plaquesWithLocations = [];
        foreach ($plaques as $plaque) {
            // Find the location connection for this plaque
            $locationConnection = Connection::where('type_id', 'located')
                ->where('parent_id', $plaque->id)
                ->with(['child'])
                ->first();

            if ($locationConnection && $locationConnection->child) {
                $location = $locationConnection->child;
                $metadata = $location->metadata ?? [];
                
                // Check if location has coordinates
                $coordinates = $metadata['coordinates'] ?? null;
                if ($coordinates && isset($coordinates['latitude']) && isset($coordinates['longitude'])) {
                    // Get connections to people - plaque (parent) features person (child)
                    $personConnections = Connection::where('type_id', 'features')
                        ->where('parent_id', $plaque->id) // Plaque is the parent
                        ->whereHas('child', function($query) {
                            $query->where('type_id', 'person'); // Only get person connections, not photos
                        })
                        ->with(['child'])
                        ->get();
                    
                    // Get connections to organisations - plaque (parent) features organisation (child)
                    $organisationConnections = Connection::where('type_id', 'features')
                        ->where('parent_id', $plaque->id) // Plaque is the parent
                        ->whereHas('child', function($query) {
                            $query->where('type_id', 'organisation');
                        })
                        ->with(['child'])
                        ->get();
                    
                    $plaquesWithLocations[] = [
                        'id' => $plaque->id, // Add ID at top level for easier access
                        'plaque' => $plaque,
                        'location' => $location,
                        'latitude' => (float) $coordinates['latitude'],
                        'longitude' => (float) $coordinates['longitude'],
                        'name' => $plaque->name,
                        'description' => $plaque->description,
                        'url' => route('spans.show', $plaque),
                        'person_connections' => $personConnections->map(function($conn) {
                            return [
                                'id' => $conn->child->id,
                                'name' => $conn->child->name,
                                'type' => $conn->child->type_id,
                                'url' => route('spans.show', $conn->child)
                            ];
                        })->toArray(),
                        'organisation_connections' => $organisationConnections->map(function($conn) {
                            return [
                                'id' => $conn->child->id,
                                'name' => $conn->child->name,
                                'type' => $conn->child->type_id,
                                'url' => route('spans.show', $conn->child)
                            ];
                        })->toArray()
                    ];
                }
            }
        }

        return view('plaques.index', compact('plaquesWithLocations'));
    }

    /**
     * Display "At Your Age" comparisons for the authenticated user.
     */
    public function atYourAge(Request $request): View
    {
        $user = Auth::user();
        
        if (!$user || !$user->personalSpan) {
            abort(403, 'You must have a personal span to view this page.');
        }

        $personalSpan = $user->personalSpan;
        $today = \App\Helpers\DateHelper::getCurrentDate();
        
        // Calculate age
        $birthDate = \Carbon\Carbon::createFromDate(
            $personalSpan->start_year,
            $personalSpan->start_month ?? 1,
            $personalSpan->start_day ?? 1
        );
        
        // Check if we're in time travel mode and the date is before birth
        $isBeforeBirth = $today->lt($birthDate);
        
        if ($isBeforeBirth) {
            // Calculate time before birth
            $timeBeforeBirth = $today->diff($birthDate);
            $age = (object)['y' => 0, 'm' => 0, 'd' => 0]; // Dummy age for compatibility
        } else {
            // Calculate normal age
            $age = $birthDate->diff($today);
        }

        // Get random person spans that the user can see (excluding the user themselves)
        $query = Span::where('type_id', 'person')
            ->where('id', '!=', $personalSpan->id) // Exclude the user
            ->where('access_level', 'public') // Only public spans
            ->where('state', 'complete') // Only complete spans (includes living and deceased)
            ->whereNotNull('start_year') // Only spans with birth dates
            ->whereNotNull('start_month')
            ->whereNotNull('start_day');
        
        if ($isBeforeBirth) {
            // When in time travel mode before birth, show people who were alive on that date
            // and were born before the current time travel date
            $query->where('start_year', '<=', $today->year);
            
            // Also filter out people who died before the time travel date
            $query->where(function($q) use ($today) {
                $q->whereNull('end_year') // Still alive
                  ->orWhere('end_year', '>=', $today->year); // Or died after the time travel date
            });
        } else {
            // Normal mode: only people older than the user
            $query->where('start_year', '<', $personalSpan->start_year);
        }
        
        $randomSpans = $query->inRandomOrder()
            ->limit(100) // Increased significantly to ensure we find 9 people with sufficient connections
            ->get();
        
        $randomComparisons = [];
        $connectionThreshold = 5; // Start with requiring 5+ connections
        
        foreach ($randomSpans as $randomSpan) {
            $randomBirthDate = \Carbon\Carbon::createFromDate(
                $randomSpan->start_year,
                $randomSpan->start_month ?? 1,
                $randomSpan->start_day ?? 1
            );
            
            if ($isBeforeBirth) {
                // In time travel mode before birth, just use the current time travel date
                $randomAgeDate = $today;
            } else {
                // Calculate the date when this person was the user's current age
                $randomAgeDate = $randomBirthDate->copy()->addYears($age->y)
                    ->addMonths($age->m)
                    ->addDays($age->d);
                
                // Check if this person was already dead when they were the user's current age
                $wasDeadAtUserAge = false;
                if ($randomSpan->end_year && $randomSpan->end_month && $randomSpan->end_day) {
                    $deathDate = \Carbon\Carbon::createFromDate(
                        $randomSpan->end_year,
                        $randomSpan->end_month,
                        $randomSpan->end_day
                    );
                    
                    // If they died before reaching the user's current age, exclude them
                    if ($deathDate->lt($randomAgeDate)) {
                        $wasDeadAtUserAge = true;
                    }
                }
                
                // Skip if they were dead at the user's age
                if ($wasDeadAtUserAge) {
                    continue;
                }
            }
            
            // Check if this person has enough connections that will be visible at the target date
            $connectionsAtDate = \App\Models\Connection::where(function($query) use ($randomSpan) {
                $query->where('parent_id', $randomSpan->id)
                      ->orWhere('child_id', $randomSpan->id);
            })
            ->where('parent_id', '!=', $randomSpan->id) // Exclude self-referential connections
            ->where('child_id', '!=', $randomSpan->id) // Exclude self-referential connections
            ->where('type_id', '!=', 'contains') // Exclude contains connections
            ->whereHas('connectionSpan', function($query) use ($randomAgeDate) {
                $query->where('start_year', '<=', $randomAgeDate->year)
                      ->where(function($q) use ($randomAgeDate) {
                          $q->whereNull('end_year')
                            ->orWhere('end_year', '>=', $randomAgeDate->year);
                      });
            })
            ->count();
            
            if ($connectionsAtDate < $connectionThreshold) {
                continue; // Skip people with insufficient connections at the target date
            }
            
            // Add this person to our comparisons since they passed all checks
            $randomComparisons[] = [
                'span' => $randomSpan,
                'date' => $randomAgeDate
            ];
            
            // Stop once we have 9 valid comparisons (3 columns of 3)
            if (count($randomComparisons) >= 9) {
                break;
            }
        }
        
        // If we didn't find enough people with 5+ connections, try with 3+ connections
        if (count($randomComparisons) < 6 && $connectionThreshold == 5) {
            $connectionThreshold = 3;
            
            // Reset and try again with lower threshold
            $randomComparisons = [];
            foreach ($randomSpans as $randomSpan) {
                $randomBirthDate = \Carbon\Carbon::createFromDate(
                    $randomSpan->start_year,
                    $randomSpan->start_month ?? 1,
                    $randomSpan->start_day ?? 1
                );
                
                $randomAgeDate = $randomBirthDate->copy()->addYears($age->y)
                    ->addMonths($age->m)
                    ->addDays($age->d);
                
                // Check if this person was already dead when they were the user's current age
                $wasDeadAtUserAge = false;
                if ($randomSpan->end_year && $randomSpan->end_month && $randomSpan->end_day) {
                    $deathDate = \Carbon\Carbon::createFromDate(
                        $randomSpan->end_year,
                        $randomSpan->end_month,
                        $randomSpan->end_day
                    );
                    
                    if ($deathDate->lt($randomAgeDate)) {
                        $wasDeadAtUserAge = true;
                    }
                }
                
                // Skip if they were dead at the user's age
                if ($wasDeadAtUserAge) {
                    continue;
                }
                
                // Check if this person has enough connections that will be visible at the target date
                $connectionsAtDate = \App\Models\Connection::where(function($query) use ($randomSpan) {
                    $query->where('parent_id', $randomSpan->id)
                          ->orWhere('child_id', $randomSpan->id);
                })
                ->where('child_id', '!=', $randomSpan->id) // Exclude self-referential connections
                ->where('type_id', '!=', 'contains') // Exclude contains connections
                ->whereHas('connectionSpan', function($query) use ($randomAgeDate) {
                    $query->where('start_year', '<=', $randomAgeDate->year)
                          ->where(function($q) use ($randomAgeDate) {
                              $q->whereNull('end_year')
                                ->orWhere('end_year', '>=', $randomAgeDate->year);
                          });
                })
                ->count();
                
                if ($connectionsAtDate < $connectionThreshold) {
                    continue; // Skip people with insufficient connections at the target date
                }
                
                // Add this person to our comparisons since they passed all checks
                $randomComparisons[] = [
                    'span' => $randomSpan,
                    'date' => $randomAgeDate
                ];
                
                if (count($randomComparisons) >= 9) {
                    break;
                }
            }
        }

        // Generate stories for each comparison using the at-date story generator
        $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
        $enhancedComparisons = [];
        
        foreach ($randomComparisons as $comparison) {
            $story = $storyGenerator->generateStoryAtDate($comparison['span'], $comparison['date']->format('Y-m-d'));
            $enhancedComparisons[] = [
                'span' => $comparison['span'],
                'date' => $comparison['date'],
                'story' => $story
            ];
        }

        // Log the results for debugging
        \Illuminate\Support\Facades\Log::info('At Your Age search results', [
            'user_id' => $user->id,
            'user_age' => $age->y . 'y ' . $age->m . 'm ' . $age->d . 'd',
            'candidates_searched' => $randomSpans->count(),
            'valid_comparisons_found' => count($enhancedComparisons),
            'target_count' => 6,
            'connection_threshold_used' => $connectionThreshold,
            'connection_check_type' => 'connections_at_target_date',
            'stories_generated' => true
        ]);

        return view('explore.at-your-age', compact('enhancedComparisons', 'age', 'personalSpan', 'isBeforeBirth', 'today'));
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
    public function showConnection(Request $request, Span $subject, string $predicate, Span $object): View|\Illuminate\Http\RedirectResponse
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

        // Redirect to all-connections page with hash anchor for the predicate
        return redirect()->route('spans.all-connections', $subject)->withFragment($predicate);

        // Get all connections of this type involving the subject with access control
        $user = auth()->user();
        $userId = $user?->id ?? 'guest';
        
        // Cache key includes user ID for proper access control
        $cacheKey = "connections_list_{$subject->id}_{$connectionType->type}_{$userId}";
        
        $connections = Cache::remember($cacheKey, 300, function () use ($subject, $connectionType, $user) {
            return Connection::where('connections.type_id', $connectionType->type)
                ->where(function($query) use ($subject) {
                    $query->where('connections.parent_id', $subject->id)
                          ->orWhere('connections.child_id', $subject->id);
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
                ->with([
                    'subject:id,name,type_id,metadata,access_level,owner_id',
                    'object:id,name,type_id,metadata,access_level,owner_id',
                    'connectionSpan:id,slug,start_year,start_month,start_day,end_year,end_month,end_day'
                ])
                ->get();
        });
        
        // Transform connections to show the other span and relationship direction
        $connections->transform(function($connection) use ($subject, $connectionType) {
            $isParent = $connection->parent_id === $subject->id;
            $otherSpan = $isParent ? $connection->object : $connection->subject;
            $predicate = $isParent ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
            
            $connection->other_span = $otherSpan;
            $connection->is_parent = $isParent;
            $connection->predicate = $predicate;
            
            return $connection;
        });
        
        // Sort the collection by connection span start date (earliest first)
        $connections = $connections->sortBy(function($connection) {
            $connectionSpan = $connection->connectionSpan;
            if (!$connectionSpan || !$connectionSpan->start_year) {
                return PHP_INT_MAX; // Put connections without dates at the end
            }
            
            // Create a sortable date string (YYYYMMDD format)
            $year = $connectionSpan->start_year;
            $month = $connectionSpan->start_month ?? 1;
            $day = $connectionSpan->start_day ?? 1;
            
            return sprintf('%04d%02d%02d', $year, $month, $day);
        })->values();
        
        // Create a custom paginator
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedConnections = $connections->slice($offset, $perPage);
        
        $connections = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedConnections,
            $connections->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        // Get all relevant connection types for this span with connection counts (cache separately)
        $connectionTypesCacheKey = "connection_types_{$subject->id}";
        $relevantConnectionTypes = Cache::remember($connectionTypesCacheKey, 600, function () use ($subject) {
            return ConnectionType::where(function($query) use ($subject) {
                $query->whereJsonContains('allowed_span_types->parent', $subject->type_id)
                      ->orWhereJsonContains('allowed_span_types->child', $subject->type_id);
            })->orderBy('forward_predicate')->get();
        });

        // Add connection counts for each type (cached per type)
        $connectionCounts = [];
        $connectionTypeDirections = [];
        $relevantConnectionTypes->each(function($type) use ($subject, &$connectionCounts, &$connectionTypeDirections, $user) {
            $countCacheKey = "connection_count_{$subject->id}_{$type->type}";
            $count = Cache::remember($countCacheKey, 300, function () use ($subject, $type) {
                return Connection::where('connections.type_id', $type->type)
                    ->where(function($query) use ($subject) {
                        $query->where('connections.parent_id', $subject->id)
                              ->orWhere('connections.child_id', $subject->id);
                    })->count();
            });
            
            $type->connection_count = $count;
            $connectionCounts[$type->type] = $count;
        });

        // Fetch ALL connections for the timeline (same logic as allConnections)
        $allConnectionsCacheKey = "connections_all_v4_{$subject->id}_{$userId}";
        $allConnectionsData = Cache::remember($allConnectionsCacheKey, 300, function () use ($subject, $user, $relevantConnectionTypes) {
            $allConnectionsFlat = collect();
            $connectionTypeDirections = [];
            
            foreach ($relevantConnectionTypes as $connectionType) {
                if ($connectionType->type === 'features') {
                    continue; // Exclude features
                }
                
                $connections = Connection::where('type_id', $connectionType->type)
                    ->where(function($query) use ($subject) {
                        $query->where('parent_id', $subject->id)
                              ->orWhere('child_id', $subject->id);
                    })
                    ->where(function($query) use ($user) {
                        if (!$user) {
                            $query->whereHas('subject', function($q) {
                                $q->where('access_level', 'public');
                            })->whereHas('object', function($q) {
                                $q->where('access_level', 'public');
                            });
                        } elseif (!$user->is_admin) {
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
                    ->with([
                        'subject:id,name,type_id,metadata,access_level,owner_id',
                        'object:id,name,type_id,metadata,access_level,owner_id',
                        'connectionSpan:id,slug,start_year,start_month,start_day,end_year,end_month,end_day,state'
                    ])
                    ->get();

                $connections->transform(function($conn) use ($subject, $connectionType) {
                    $isParent = $conn->parent_id === $subject->id;
                    $otherSpan = $isParent ? $conn->object : $conn->subject;
                    $predicate = $isParent ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
                    
                    $conn->other_span = $otherSpan;
                    $conn->is_parent = $isParent;
                    $conn->predicate = $predicate;
                    $conn->connection_type = $connectionType;
                    $conn->connection_type_id = $connectionType->type;
                    
                    return $conn;
                });

                $filteredConnections = $connections->filter(function($conn) use ($connectionType, $subject) {
                    if ($connectionType->type === 'features') {
                        return false;
                    }
                    if ($connectionType->type === 'created') {
                        $otherSpan = $conn->parent_id === $subject->id ? $conn->object : $conn->subject;
                        if ($otherSpan->type_id === 'thing' && 
                            isset($otherSpan->metadata['subtype']) && 
                            $otherSpan->metadata['subtype'] === 'photo') {
                            return false;
                        }
                        if ($otherSpan->type_id === 'note') {
                            return false;
                        }
                    }
                    return true;
                });

                if ($filteredConnections->count() > 0) {
                    $hasForward = $filteredConnections->contains(function($conn) {
                        return isset($conn->is_parent) && $conn->is_parent === true;
                    });
                    $hasInverse = $filteredConnections->contains(function($conn) {
                        return isset($conn->is_parent) && $conn->is_parent === false;
                    });
                    
                    $predicate = $hasForward ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
                    
                    $connectionTypeDirections[$connectionType->type] = [
                        'has_forward' => $hasForward,
                        'has_inverse' => $hasInverse,
                        'predicate' => $predicate
                    ];
                }
                
                $allConnectionsFlat = $allConnectionsFlat->merge($filteredConnections);
            }

            $connectionsWithDates = $allConnectionsFlat->filter(function($conn) {
                return $conn->connectionSpan && $conn->connectionSpan->start_year;
            })->sortBy(function($conn) {
                $connectionSpan = $conn->connectionSpan;
                if (!$connectionSpan || !$connectionSpan->start_year) {
                    return PHP_INT_MAX;
                }
                $year = $connectionSpan->start_year;
                $month = $connectionSpan->start_month ?? 1;
                $day = $connectionSpan->start_day ?? 1;
                return sprintf('%04d%02d%02d', $year, $month, $day);
            });

            $connectionsWithoutDates = $allConnectionsFlat->filter(function($conn) {
                return !$conn->connectionSpan || !$conn->connectionSpan->start_year;
            });

            $allConnections = $connectionsWithDates->concat($connectionsWithoutDates)->values();

            return [
                'allConnections' => $allConnections,
                'connectionTypeDirections' => $connectionTypeDirections
            ];
        });

        $allConnections = $allConnectionsData['allConnections'];
        $connectionTypeDirections = array_merge($connectionTypeDirections, $allConnectionsData['connectionTypeDirections']);

        return view('spans.connections', compact(
            'subject', 
            'connectionType', 
            'connections', 
            'predicate', 
            'relevantConnectionTypes',
            'connectionCounts',
            'connectionTypeDirections',
            'allConnections'
        ));
    }

    /**
     * Show all connections for a span in a comprehensive Gantt chart view
     */
    public function allConnections(Request $request, Span $subject): View
    {
        $user = auth()->user();
        $userId = $user?->id ?? 'guest';
        
        // Cache key includes user ID for proper access control
        // Version 4: includes connectionCounts and connectionTypeDirections in return array
        $cacheKey = "connections_all_v4_{$subject->id}_{$userId}";
        
        $cachedData = Cache::remember($cacheKey, 300, function () use ($subject, $user) {
            // Get all relevant connection types for this span with connection counts
            // Exclude "features" connections
            $relevantConnectionTypes = ConnectionType::where(function($query) use ($subject) {
                $query->whereJsonContains('allowed_span_types->parent', $subject->type_id)
                      ->orWhereJsonContains('allowed_span_types->child', $subject->type_id);
            })->whereNotIn('type', ['features'])
              ->orderBy('forward_predicate')->get();

            // Collect all connections across all types, then merge and sort chronologically
            $allConnectionsFlat = collect();
            $connectionCounts = [];
            $connectionTypeDirections = []; // Track whether each type has forward/inverse connections
            
            foreach ($relevantConnectionTypes as $connectionType) {
                // Apply access control similar to listConnections
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
                    ->with([
                        'subject:id,name,type_id,metadata,access_level,owner_id',
                        'object:id,name,type_id,metadata,access_level,owner_id',
                        'connectionSpan:id,slug,start_year,start_month,start_day,end_year,end_month,end_day,state'
                    ])
                    ->get();

                // Transform connections to show the other span and relationship direction
                $connections->transform(function($connection) use ($subject, $connectionType) {
                    $isParent = $connection->parent_id === $subject->id;
                    $otherSpan = $isParent ? $connection->object : $connection->subject;
                    $predicate = $isParent ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
                    
                    $connection->other_span = $otherSpan;
                    $connection->is_parent = $isParent;
                    $connection->predicate = $predicate;
                    $connection->connection_type = $connectionType;
                    // Store the connection type ID for color coding
                    $connection->connection_type_id = $connectionType->type;
                    
                    return $connection;
                });

                // Filter out "created" connections to photos and notes, and "features" connections
                $filteredConnections = $connections->filter(function($conn) use ($connectionType, $subject) {
                    // Filter out "features" connections
                    if ($connectionType->type === 'features') {
                        return false;
                    }
                    
                    if ($connectionType->type === 'created') {
                        $otherSpan = $conn->parent_id === $subject->id ? $conn->object : $conn->subject;
                        // Filter out connections to photos (type=thing, subtype=photo)
                        if ($otherSpan->type_id === 'thing' && 
                            isset($otherSpan->metadata['subtype']) && 
                            $otherSpan->metadata['subtype'] === 'photo') {
                            return false;
                        }
                        // Filter out connections to notes
                        if ($otherSpan->type_id === 'note') {
                            return false;
                        }
                    }
                    return true;
                });

                // Store count for this connection type
                $connectionCounts[$connectionType->type] = $filteredConnections->count();
                
                // Track connection directions for this type
                // Only track if there are connections
                if ($filteredConnections->count() > 0) {
                    $hasForward = $filteredConnections->contains(function($conn) {
                        return isset($conn->is_parent) && $conn->is_parent === true;
                    });
                    $hasInverse = $filteredConnections->contains(function($conn) {
                        return isset($conn->is_parent) && $conn->is_parent === false;
                    });
                    
                    // Determine which predicate to show:
                    // - If there are any forward connections, prefer forward predicate
                    // - Otherwise, use inverse predicate
                    $predicate = $hasForward ? $connectionType->forward_predicate : $connectionType->inverse_predicate;
                    
                    $connectionTypeDirections[$connectionType->type] = [
                        'has_forward' => $hasForward,
                        'has_inverse' => $hasInverse,
                        'predicate' => $predicate
                    ];
                }
                
                // Add to flat collection for chronological sorting
                $allConnectionsFlat = $allConnectionsFlat->merge($filteredConnections);
            }

            // Sort all connections chronologically (across all types) by full start date
            $connectionsWithDates = $allConnectionsFlat->filter(function($conn) {
                return $conn->connectionSpan && $conn->connectionSpan->start_year;
            })->sortBy(function($conn) {
                $connectionSpan = $conn->connectionSpan;
                if (!$connectionSpan || !$connectionSpan->start_year) {
                    return PHP_INT_MAX; // Put connections without dates at the end
                }
                
                // Create a sortable date string (YYYYMMDD format)
                $year = $connectionSpan->start_year;
                $month = $connectionSpan->start_month ?? 1;
                $day = $connectionSpan->start_day ?? 1;
                
                return sprintf('%04d%02d%02d', $year, $month, $day);
            });

            $connectionsWithoutDates = $allConnectionsFlat->filter(function($conn) {
                return !$conn->connectionSpan || !$conn->connectionSpan->start_year;
            });

            // Final sorted collection: connections with dates (chronological), then without dates
            $allConnections = $connectionsWithDates->concat($connectionsWithoutDates)->values();

            return [
                'allConnections' => $allConnections, // Now a flat collection sorted chronologically
                'connectionCounts' => $connectionCounts,
                'relevantConnectionTypes' => $relevantConnectionTypes,
                'connectionTypeDirections' => $connectionTypeDirections
            ];
        });

        return view('spans.all-connections', [
            'subject' => $subject,
            'allConnections' => $cachedData['allConnections'],
            'connectionCounts' => $cachedData['connectionCounts'] ?? [],
            'connectionTypeDirections' => $cachedData['connectionTypeDirections'] ?? [],
            'relevantConnectionTypes' => $cachedData['relevantConnectionTypes']
        ]);
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
            try {
                $mergedData = $yamlService->mergeYamlWithExistingSpan($span, $validationResult['data']);
            } catch (\Exception $e) {
                Log::error('Failed to merge YAML with existing span in improveWithAi', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'error' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'validation_data_keys' => array_keys($validationResult['data']),
                    'validation_data_sample' => array_map(function($v) {
                        if (is_array($v)) {
                            return '[array with ' . count($v) . ' elements]';
                        }
                        if (is_string($v) && strlen($v) > 200) {
                            return substr($v, 0, 200) . '...';
                        }
                        return $v;
                    }, $validationResult['data'])
                ]);
                
                $errorMessage = 'Failed to merge AI data with existing span: ' . $e->getMessage();
                if (strpos($e->getMessage(), 'Array to string conversion') !== false || 
                    strpos($e->getMessage(), 'data type conversion') !== false) {
                    $errorMessage .= ' This appears to be a data type conversion error. The AI-generated YAML may contain fields with incorrect data types (e.g., arrays where strings are expected, or vice versa).';
                }
                $errorMessage .= ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
                
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'error_details' => [
                        'message' => $e->getMessage(),
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine(),
                        'exception_class' => get_class($e),
                        'step' => 'merge'
                    ]
                ], 500);
            }
            
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
                // Return detailed error information
                $errorResponse = [
                    'success' => false,
                    'error' => $applyResult['message']
                ];
                
                // Include field errors if available
                if (isset($applyResult['field_errors'])) {
                    $errorResponse['field_errors'] = $applyResult['field_errors'];
                    $errorResponse['error'] .= ' Field errors: ' . json_encode($applyResult['field_errors']);
                }
                
                // Include error details if available
                if (isset($applyResult['error_details'])) {
                    $errorResponse['error_details'] = $applyResult['error_details'];
                }
                
                return response()->json($errorResponse, 500);
            }

        } catch (\Exception $e) {
            Log::error('AI span improvement error', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'span_id' => $span->id,
                'span_name' => $span->name,
                'exception_class' => get_class($e)
            ]);

            // Build detailed error message
            $errorMessage = 'Failed to improve span: ' . $e->getMessage();
            
            // Add file and line information for debugging
            $errorMessage .= ' (File: ' . basename($e->getFile()) . ', Line: ' . $e->getLine() . ')';
            
            // Check if it's a type conversion error
            if (strpos($e->getMessage(), 'Array to string conversion') !== false) {
                $errorMessage .= '. This appears to be a data type conversion error. Please check the AI-generated YAML data for fields that should be strings but are arrays (or vice versa).';
            }

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'exception_class' => get_class($e)
                ]
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

        Log::info('Preview improvement request', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'span_type' => $span->type_id,
            'ai_yaml_length' => strlen($validated['ai_yaml']),
            'ai_yaml_sample' => substr($validated['ai_yaml'], 0, 500)
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
            try {
                $mergedData = $yamlService->mergeYamlWithExistingSpan($span, $validationResult['data']);
            } catch (\Exception $e) {
                Log::warning('Error merging YAML (using new data only)', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                $mergedData = $validationResult['data'];
            }
            
            // Analyze the impacts of the changes
            try {
                $impacts = $yamlService->analyzeChangeImpacts($validationResult['data'], $span);
            } catch (\Exception $e) {
                Log::warning('Error analyzing impacts', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                $impacts = [];
            }
            
            // Get current span data for comparison (use safe method to avoid deep nesting issues)
            try {
                $currentData = $yamlService->spanToArraySafe($span);
            } catch (\Exception $e) {
                Log::warning('Error converting span to array', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                $currentData = ['name' => $span->name, 'type' => $span->type_id, 'state' => $span->state];
            }
            
            // Create a structured diff showing what will change
            try {
                $diff = $this->createStructuredDiff($currentData, $mergedData);
            } catch (\Exception $e) {
                Log::warning('Error creating diff', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                $diff = [
                    'basic_fields' => [],
                    'metadata' => [],
                    'sources' => [],
                    'connections' => []
                ];
            }
            
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
                'span_name' => $span->name,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize metadata values for comparison (handle type differences)
     */
    private function normalizeMetadataForComparison($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $val) {
                $normalized[$key] = $this->normalizeMetadataForComparison($val);
            }
            return $normalized;
        }
        
        if (is_object($value)) {
            // Convert objects to arrays for comparison
            return $this->normalizeMetadataForComparison((array)$value);
        }
        
        if (is_string($value)) {
            // Try to convert string numbers to integers
            if (is_numeric($value) && ctype_digit($value)) {
                return (int)$value;
            }
            // Try to convert string booleans
            if ($value === 'true') {
                return true;
            }
            if ($value === 'false') {
                return false;
            }
        }
        
        // Handle other data types (null, boolean, integer, float, resource, etc.)
        if (is_resource($value)) {
            return '[resource]'; // Convert resources to string representation
        }
        
        if (is_callable($value)) {
            return '[callable]'; // Convert callables to string representation
        }
        
        return $value;
    }

    /**
     * Create a structured diff between current and merged data (database format)
     */
    private function createStructuredDiff(array $currentData, array $mergedData): array
    {
        $diff = [
            'basic_fields' => [],
            'metadata' => [],
            'sources' => [],
            'connections' => []
        ];

        // Compare basic fields (all editable core fields from spreadsheet)
        $basicFields = ['name', 'slug', 'state', 'access_level', 'description', 'notes'];
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

        // Compare type field (handle type vs type_id mapping)
        $currentType = $currentData['type'] ?? null;
        $mergedType = $mergedData['type_id'] ?? null;
        
        if ($currentType !== $mergedType) {
            $diff['basic_fields'][] = [
                'field' => 'type',
                'current' => $currentType,
                'new' => $mergedType,
                'action' => $currentType === null ? 'add' : ($mergedType === null ? 'remove' : 'update')
            ];
        }

        // Compare date fields
        $dateFields = ['start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day'];
        foreach ($dateFields as $field) {
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
            
            // Normalize both values for comparison
            $normalizedCurrent = $this->normalizeMetadataForComparison($current);
            $normalizedMerged = $this->normalizeMetadataForComparison($merged);
            

            
            if ($normalizedCurrent !== $normalizedMerged) {
                $diff['metadata'][] = [
                    'key' => $key,
                    'current' => $normalizedCurrent,
                    'new' => $normalizedMerged,
                    'action' => $current === null ? 'add' : ($merged === null ? 'remove' : 'update')
                ];
            }
        }

        // Compare connections (database format - array of connection objects)
        $currentConnections = $currentData['connections'] ?? [];
        $mergedConnections = $mergedData['connections'] ?? [];
        
        // Only compare if there are connections to compare
        if (!empty($mergedConnections) || !empty($currentConnections)) {
            // Group connections by predicate for easier comparison
            $currentByPredicate = [];
            $mergedByPredicate = [];
            
            foreach ($currentConnections as $conn) {
                $predicate = $conn['predicate'] ?? 'unknown';
                if (!isset($currentByPredicate[$predicate])) {
                    $currentByPredicate[$predicate] = [];
                }
                $currentByPredicate[$predicate][] = $conn;
            }
            
            foreach ($mergedConnections as $conn) {
                $predicate = $conn['predicate'] ?? 'unknown';
                if (!isset($mergedByPredicate[$predicate])) {
                    $mergedByPredicate[$predicate] = [];
                }
                $mergedByPredicate[$predicate][] = $conn;
            }
            
            $allPredicates = array_unique(array_merge(array_keys($currentByPredicate), array_keys($mergedByPredicate)));
            
            foreach ($allPredicates as $predicate) {
                $current = $currentByPredicate[$predicate] ?? [];
                $merged = $mergedByPredicate[$predicate] ?? [];
                
                // Compare connections by object name and key attributes
                $currentObjects = array_map(function($conn) {
                    return $conn['object'] ?? '';
                }, $current);
                $mergedObjects = array_map(function($conn) {
                    return $conn['object'] ?? '';
                }, $merged);
                
                $added = array_diff($mergedObjects, $currentObjects);
                $removed = array_diff($currentObjects, $mergedObjects);
                
                // Also check for modifications to existing connections
                $modified = [];
                $commonObjects = array_intersect($currentObjects, $mergedObjects);
                
                foreach ($commonObjects as $objectName) {
                    $currentConn = collect($current)->firstWhere('object', $objectName);
                    $mergedConn = collect($merged)->firstWhere('object', $objectName);
                    
                    if ($currentConn && $mergedConn) {
                        // Compare key connection attributes
                        $dateFields = ['start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day'];
                        $otherFields = ['subject', 'predicate', 'metadata'];
                        
                        $hasChanges = false;
                        $changes = [];
                        
                        foreach ($dateFields as $field) {
                            $currentVal = $currentConn[$field] ?? null;
                            $mergedVal = $mergedConn[$field] ?? null;
                            
                            // Normalize values for comparison (handle int vs string differences)
                            $currentVal = $currentVal !== null ? (int)$currentVal : null;
                            $mergedVal = $mergedVal !== null ? (int)$mergedVal : null;
                            
                            if ($currentVal !== $mergedVal) {
                                $hasChanges = true;
                                $changes[$field] = [
                                    'current' => $currentVal,
                                    'new' => $mergedVal
                                ];
                            }
                        }
                        
                        foreach ($otherFields as $field) {
                            $currentVal = $currentConn[$field] ?? null;
                            $mergedVal = $mergedConn[$field] ?? null;
                            
                            // Special handling for metadata field
                            if ($field === 'metadata') {
                                // Convert both to arrays and compare
                                $currentVal = is_array($currentVal) ? $currentVal : [];
                                $mergedVal = is_array($mergedVal) ? $mergedVal : [];
                                
                                // Normalize both values for comparison
                                $normalizedCurrent = $this->normalizeMetadataForComparison($currentVal);
                                $normalizedMerged = $this->normalizeMetadataForComparison($mergedVal);
                                
                                // Only mark as changed if the arrays are actually different
                                try {
                                    $currentJson = json_encode($normalizedCurrent, JSON_THROW_ON_ERROR);
                                    $mergedJson = json_encode($normalizedMerged, JSON_THROW_ON_ERROR);
                                    
                                    if ($currentJson !== $mergedJson) {
                                        $hasChanges = true;
                                        $changes[$field] = [
                                            'current' => $currentVal,
                                            'new' => $mergedVal
                                        ];
                                    }
                                } catch (\JsonException $e) {
                                    // If JSON encoding fails, do a simple comparison
                                    if ($normalizedCurrent !== $normalizedMerged) {
                                        $hasChanges = true;
                                        $changes[$field] = [
                                            'current' => $currentVal,
                                            'new' => $mergedVal
                                        ];
                                    }
                                }
                            } else {
                                // For other fields, do simple comparison
                                if ($currentVal !== $mergedVal) {
                                    $hasChanges = true;
                                    $changes[$field] = [
                                        'current' => $currentVal,
                                        'new' => $mergedVal
                                    ];
                                }
                            }
                        }
                        
                        if ($hasChanges) {
                            $modified[] = [
                                'object' => $objectName,
                                'changes' => $changes
                            ];
                        }
                    }
                }
                
                if (!empty($added) || !empty($removed) || !empty($modified)) {
                    $diff['connections'][] = [
                        'type' => $predicate,
                        'added' => array_values($added),
                        'removed' => array_values($removed),
                        'modified' => $modified
                    ];
                }
            }
        }

        return $diff;
    }

    /**
     * Create a structured diff between current and merged data (YAML format)
     */
    private function createStructuredDiffYaml(array $currentData, array $mergedData): array
    {
        $diff = [
            'basic_fields' => [],
            'metadata' => [],
            'sources' => [],
            'connections' => []
        ];

        // Compare basic fields (all editable core fields from spreadsheet)
        $basicFields = ['name', 'slug', 'type', 'state', 'access_level', 'description', 'notes', 'start', 'end'];
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
        
        // Only compare if there are connections to compare
        if (!empty($mergedConnections) || !empty($currentConnections)) {
            $allConnectionTypes = array_unique(array_merge(array_keys($currentConnections), array_keys($mergedConnections)));
            
            foreach ($allConnectionTypes as $type) {
                $current = $currentConnections[$type] ?? [];
                $merged = $mergedConnections[$type] ?? [];
                
                // Normalize the data for comparison
                $currentNormalized = $this->normalizeConnectionsForComparison($current);
                $mergedNormalized = $this->normalizeConnectionsForComparison($merged);
                
                $currentNames = array_column($currentNormalized, 'name');
                $mergedNames = array_column($mergedNormalized, 'name');
                
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
        }

        return $diff;
    }

    /**
     * Normalize connection data for comparison
     */
    private function normalizeConnectionsForComparison(array $connections): array
    {
        $normalized = [];
        
        foreach ($connections as $connection) {
            // Handle different data structures:
            // 1. From spanToArray (current data): connections are objects with 'name', 'start', 'end', etc.
            // 2. From spreadsheet editor (merged data): connections are objects with 'object', 'start_year', etc.
            
            $name = $connection['name'] ?? $connection['object'] ?? '';
            
            // Handle dates - current data has 'start'/'end' strings, merged data has year/month/day components
            $startYear = null;
            $startMonth = null;
            $startDay = null;
            $endYear = null;
            $endMonth = null;
            $endDay = null;
            
            if (isset($connection['start_year'])) {
                // From spreadsheet editor format
                $startYear = $connection['start_year'];
                $startMonth = $connection['start_month'] ?? null;
                $startDay = $connection['start_day'] ?? null;
            } elseif (isset($connection['start'])) {
                // From spanToArray format - parse the date string
                $startParts = explode('-', $connection['start']);
                if (count($startParts) >= 1) $startYear = (int)$startParts[0];
                if (count($startParts) >= 2) $startMonth = (int)$startParts[1];
                if (count($startParts) >= 3) $startDay = (int)$startParts[2];
            }
            
            if (isset($connection['end_year'])) {
                // From spreadsheet editor format
                $endYear = $connection['end_year'];
                $endMonth = $connection['end_month'] ?? null;
                $endDay = $connection['end_day'] ?? null;
            } elseif (isset($connection['end'])) {
                // From spanToArray format - parse the date string
                $endParts = explode('-', $connection['end']);
                if (count($endParts) >= 1) $endYear = (int)$endParts[0];
                if (count($endParts) >= 2) $endMonth = (int)$endParts[1];
                if (count($endParts) >= 3) $endDay = (int)$endParts[2];
            }
            
            $normalized[] = [
                'name' => $name,
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'start_day' => $startDay,
                'end_year' => $endYear,
                'end_month' => $endMonth,
                'end_day' => $endDay,
                'metadata' => $connection['metadata'] ?? []
            ];
        }
        
        return $normalized;
    }



    /**
     * Validate spreadsheet data without saving
     */
    public function validateSpreadsheetData(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        try {
            Log::channel('spans')->info('Validating spreadsheet data', [
                'span_id' => $span->id,
                'input_keys' => array_keys($request->all())
            ]);
            

            
            // Use the new SpreadsheetValidationService for validation
            $validationService = app(SpreadsheetValidationService::class);
            $validationErrors = $validationService->validateSpanData($request->all(), $span);
            

            
            return response()->json([
                'success' => empty($validationErrors),
                'errors' => $validationErrors,
                'valid' => empty($validationErrors)
            ]);

        } catch (\Exception $e) {
            Log::channel('spans')->error('Error validating spreadsheet data', [
                'span_id' => $span->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while validating the data. Please try again.'
            ], 500);
        }
    }

    /**
     * Update span from spreadsheet editor data
     */
    public function updateFromSpreadsheet(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        try {
            Log::channel('spans')->info('=== SPREADSHEET UPDATE START ===', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'request_method' => $request->method(),
                'request_url' => $request->url(),
                'input_data' => $request->all(),
                'input_keys' => array_keys($request->all())
            ]);

            // Use the new SpreadsheetValidationService for validation
            Log::channel('spans')->info('Starting validation...');
            $validationService = app(SpreadsheetValidationService::class);
            $validationErrors = $validationService->validateSpanData($request->all(), $span);
            
            Log::channel('spans')->info('Validation completed', [
                'validation_errors_count' => count($validationErrors),
                'validation_errors' => $validationErrors
            ]);
            
            if (!empty($validationErrors)) {
                Log::channel('spans')->error('Spreadsheet validation failed', [
                    'errors' => $validationErrors
                ]);
                return response()->json([
                    'success' => false,
                    'errors' => $validationErrors
                ], 422);
            }

            // Convert spreadsheet data directly to database format
            Log::channel('spans')->info('Converting spreadsheet data to database format...');
            $validated = $this->convertSpreadsheetDataToDatabase($request->all());
            
            Log::channel('spans')->info('Data conversion completed', [
                'converted_data_keys' => array_keys($validated),
                'converted_data' => $validated
            ]);

            // Handle type transition if type is changing
            if ($validated['type_id'] !== $span->type_id) {
                Log::channel('spans')->info('Type changing from spreadsheet', [
                    'span_id' => $span->id,
                    'old_type' => $span->type_id,
                    'new_type' => $validated['type_id']
                ]);
            }

            // Update basic span fields
            Log::channel('spans')->info('Updating span fields...', [
                'fields_to_update' => array_keys(array_filter([
                    'name' => $validated['name'],
                    'slug' => $validated['slug'],
                    'type_id' => $validated['type_id'],
                    'state' => $validated['state'],
                    'access_level' => $validated['access_level'],
                    'start_year' => $validated['start_year'],
                    'start_month' => $validated['start_month'],
                    'start_day' => $validated['start_day'],
                    'end_year' => $validated['end_year'],
                    'end_month' => $validated['end_month'],
                    'end_day' => $validated['end_day'],
                    'description' => $validated['description'],
                    'notes' => $validated['notes'],
                    'metadata' => $validated['metadata'] ?? []
                ]))
            ]);
            
            $span->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'type_id' => $validated['type_id'],
                'state' => $validated['state'],
                'access_level' => $validated['access_level'],
                'start_year' => $validated['start_year'],
                'start_month' => $validated['start_month'],
                'start_day' => $validated['start_day'],
                'end_year' => $validated['end_year'],
                'end_month' => $validated['end_month'],
                'end_day' => $validated['end_day'],
                'description' => $validated['description'],
                'notes' => $validated['notes'],
                'metadata' => $validated['metadata'] ?? []
            ]);

            // Handle connections
            if (isset($validated['connections'])) {
                Log::channel('spans')->info('Updating connections...', [
                    'connections_count' => count($validated['connections'])
                ]);
                $this->updateConnectionsFromSpreadsheet($span, $validated['connections']);
            } else {
                Log::channel('spans')->info('No connections to update');
            }

            Log::channel('spans')->info('=== SPREADSHEET UPDATE SUCCESS ===', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'changes' => $span->getChanges(),
                'changes_count' => count($span->getChanges())
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Span updated successfully',
                'span' => $span->fresh()
            ]);

        } catch (\Exception $e) {
            Log::channel('spans')->error('=== SPREADSHEET UPDATE ERROR ===', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while saving the span. Please try again.',
                'debug_message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update connections from spreadsheet data
     */
    private function updateConnectionsFromSpreadsheet(Span $span, array $connections)
    {
        // Get existing connections for this span
        $existingConnections = $span->connectionsAsSubject->merge($span->connectionsAsObject);
        
        // Create a map of existing connections by their key identifiers
        $existingMap = [];
        foreach ($existingConnections as $conn) {
            $key = $conn->subject_id . '-' . $conn->type_id . '-' . $conn->object_id;
            $existingMap[$key] = $conn;
        }

        // Process each connection from the spreadsheet
        foreach ($connections as $connData) {
            Log::channel('spans')->info('Processing connection from spreadsheet', [
                'connection_data' => $connData
            ]);
            
            $connectionType = ConnectionType::where('type', $connData['predicate'])->first();
            Log::channel('spans')->info('Connection type lookup', [
                'predicate' => $connData['predicate'],
                'found' => $connectionType ? true : false,
                'connection_type_id' => $connectionType ? $connectionType->id : null
            ]);
            if (!$connectionType) {
                Log::channel('spans')->warning('Connection type not found', [
                    'predicate' => $connData['predicate']
                ]);
                continue;
            }
            
            Log::channel('spans')->info('Found connection type', [
                'predicate' => $connData['predicate'],
                'connection_type' => $connectionType->toArray()
            ]);

            // Use the IDs directly from the spreadsheet data (IDs are required, no name fallback)
            $subject = null;
            $object = null;
            
            if (isset($connData['subject_id']) && $connData['subject_id']) {
                $subject = Span::find($connData['subject_id']);
            }
            if (isset($connData['object_id']) && $connData['object_id']) {
                $object = Span::find($connData['object_id']);
            }

            if (!$subject || !$object) {
                Log::channel('spans')->error('Connection missing required IDs or spans not found', [
                    'subject_id' => $connData['subject_id'] ?? 'missing',
                    'object_id' => $connData['object_id'] ?? 'missing',
                    'subject_name' => $connData['subject'] ?? 'N/A',
                    'object_name' => $connData['object'] ?? 'N/A',
                    'subject_found' => $subject ? true : false,
                    'object_found' => $object ? true : false
                ]);
                continue;
            }
            
            Log::channel('spans')->info('Found subject and object', [
                'subject_name' => $subject->name,
                'subject_id' => $subject->id,
                'object_name' => $object->name,
                'object_id' => $object->id
            ]);

            $key = $subject->id . '-' . $connectionType->type . '-' . $object->id;
            
            // Check if connection already exists
            if (isset($existingMap[$key])) {
                // Update existing connection
                $connection = $existingMap[$key];
                $connection->connectionSpan->update([
                    'start_year' => $connData['start_year'] ?? null,
                    'start_month' => $connData['start_month'] ?? null,
                    'start_day' => $connData['start_day'] ?? null,
                    'end_year' => $connData['end_year'] ?? null,
                    'end_month' => $connData['end_month'] ?? null,
                    'end_day' => $connData['end_day'] ?? null,
                    'metadata' => $connData['metadata'] ?? []
                ]);
                unset($existingMap[$key]);
            } else {
                // Create new connection
                $connectionSpan = Span::create([
                    'name' => "{$subject->name} {$connectionType->forward_predicate} {$object->name}",
                    'type_id' => 'connection',
                    'state' => 'placeholder', // Use placeholder to avoid date requirements
                    'access_level' => 'private',
                    'owner_id' => $span->owner_id, // Add required owner
                    'updater_id' => $span->updater_id, // Add required updater
                    'start_year' => $connData['start_year'] ?? null,
                    'start_month' => $connData['start_month'] ?? null,
                    'start_day' => $connData['start_day'] ?? null,
                    'end_year' => $connData['end_year'] ?? null,
                    'end_month' => $connData['end_month'] ?? null,
                    'end_day' => $connData['end_day'] ?? null,
                    'metadata' => $connData['metadata'] ?? []
                ]);

                Connection::create([
                    'parent_id' => $subject->id,
                    'child_id' => $object->id,
                    'type_id' => $connectionType->type,
                    'connection_span_id' => $connectionSpan->id
                ]);
            }
        }

        // Remove connections that are no longer in the spreadsheet
        foreach ($existingMap as $connection) {
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            $connection->delete();
        }
    }

    /**
     * Convert spreadsheet data to YAML format for validation
     */
    private function convertSpreadsheetDataToYaml(array $spreadsheetData): array
    {
        $yamlData = [
            'name' => $spreadsheetData['name'],
            'slug' => $spreadsheetData['slug'] ?? null,
            'type' => $spreadsheetData['type'], // Keep as string for validation
            'state' => $spreadsheetData['state'],
            'access_level' => $spreadsheetData['access_level'],
            'description' => $spreadsheetData['description'] ?? null,
            'notes' => $spreadsheetData['notes'] ?? null,
            'metadata' => $spreadsheetData['metadata'] ?? []
        ];

        // Handle subtype field from core fields table
        if (isset($spreadsheetData['subtype'])) {
            $yamlData['metadata']['subtype'] = $spreadsheetData['subtype'];
        }

        // Convert date components to YAML format and validate
        if (!empty($spreadsheetData['start_year'])) {
            $yamlData['start'] = $this->formatDateForYaml(
                $spreadsheetData['start_year'],
                $spreadsheetData['start_month'] ?? null,
                $spreadsheetData['start_day'] ?? null
            );
        }

        if (!empty($spreadsheetData['end_year'])) {
            $yamlData['end'] = $this->formatDateForYaml(
                $spreadsheetData['end_year'],
                $spreadsheetData['end_month'] ?? null,
                $spreadsheetData['end_day'] ?? null
            );
        }
        
        // Add custom date validation
        $dateErrors = $this->validateDateRanges($spreadsheetData);
        if (!empty($dateErrors)) {
            return ['__validation_errors' => $dateErrors];
        }

        // Convert connections to YAML format
        if (!empty($spreadsheetData['connections'])) {
            $yamlData['connections'] = [];
            foreach ($spreadsheetData['connections'] as $connection) {
                $predicate = $connection['predicate'];
                if (!isset($yamlData['connections'][$predicate])) {
                    $yamlData['connections'][$predicate] = [];
                }

                $connectionData = [
                    'name' => $connection['object'],
                    'type' => 'connection'
                ];

                // Add connection dates if present
                if (!empty($connection['start_year'])) {
                    $connectionData['start'] = $this->formatDateForYaml(
                        $connection['start_year'],
                        $connection['start_month'] ?? null,
                        $connection['start_day'] ?? null
                    );
                }

                if (!empty($connection['end_year'])) {
                    $connectionData['end'] = $this->formatDateForYaml(
                        $connection['end_year'],
                        $connection['end_month'] ?? null,
                        $connection['end_day'] ?? null
                    );
                }

                if (!empty($connection['metadata'])) {
                    $connectionData['metadata'] = $connection['metadata'];
                }

                $yamlData['connections'][$predicate][] = $connectionData;
            }
            

        }

        return $yamlData;
    }

    /**
     * Convert YAML data back to database format
     */
    private function convertYamlDataToDatabase(array $yamlData): array
    {
        $dbData = [
            'name' => $yamlData['name'],
            'slug' => $yamlData['slug'],
            'type_id' => $yamlData['type'], // Keep as string - database expects character varying
            'state' => $yamlData['state'],
            'access_level' => $yamlData['access_level'],
            'description' => $yamlData['description'],
            'notes' => $yamlData['notes'],
            'metadata' => $yamlData['metadata'] ?? [],
            // Always include date fields, even if null
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null
        ];

        // Convert YAML dates to database format
        if (isset($yamlData['start']) && !empty($yamlData['start'])) {
            $startDate = $this->parseDateFromYaml($yamlData['start']);
            $dbData['start_year'] = $startDate['year'];
            $dbData['start_month'] = $startDate['month'];
            $dbData['start_day'] = $startDate['day'];
        }

        if (isset($yamlData['end']) && !empty($yamlData['end'])) {
            $endDate = $this->parseDateFromYaml($yamlData['end']);
            $dbData['end_year'] = $endDate['year'];
            $dbData['end_month'] = $endDate['month'];
            $dbData['end_day'] = $endDate['day'];
        }

        // Convert connections back to spreadsheet format
        if (isset($yamlData['connections'])) {
            $dbData['connections'] = [];
            foreach ($yamlData['connections'] as $predicate => $connections) {
                foreach ($connections as $connection) {
                    $connectionData = [
                        'subject' => $yamlData['name'], // The current span is always the subject
                        'predicate' => $predicate,
                        'object' => $connection['name'],
                        'metadata' => $connection['metadata'] ?? []
                    ];

                    // Convert connection dates back to components
                    if (isset($connection['start'])) {
                        $startDate = $this->parseDateFromYaml($connection['start']);
                        $connectionData['start_year'] = $startDate['year'];
                        $connectionData['start_month'] = $startDate['month'];
                        $connectionData['start_day'] = $startDate['day'];
                    }

                    if (isset($connection['end'])) {
                        $endDate = $this->parseDateFromYaml($connection['end']);
                        $connectionData['end_year'] = $endDate['year'];
                        $connectionData['end_month'] = $endDate['month'];
                        $connectionData['end_day'] = $endDate['day'];
                    }

                    $dbData['connections'][] = $connectionData;
                }
            }
        }

        return $dbData;
    }

    /**
     * Convert spreadsheet data directly to database format
     */
    private function convertSpreadsheetDataToDatabase(array $spreadsheetData): array
    {
        $dbData = [
            'name' => $spreadsheetData['name'],
            'slug' => $spreadsheetData['slug'],
            'type_id' => $spreadsheetData['type'],
            'state' => $spreadsheetData['state'],
            'access_level' => $spreadsheetData['access_level'],
            'description' => $spreadsheetData['description'] ?? null,
            'notes' => $spreadsheetData['notes'] ?? null,
            'metadata' => $this->normalizeMetadataForComparison($spreadsheetData['metadata'] ?? [])
        ];

        // Handle subtype field from core fields table
        if (isset($spreadsheetData['subtype'])) {
            $dbData['metadata']['subtype'] = $spreadsheetData['subtype'];
        }

        // Add date fields
        if (!empty($spreadsheetData['start_year'])) {
            $dbData['start_year'] = (int)$spreadsheetData['start_year'];
            $dbData['start_month'] = !empty($spreadsheetData['start_month']) ? (int)$spreadsheetData['start_month'] : null;
            $dbData['start_day'] = !empty($spreadsheetData['start_day']) ? (int)$spreadsheetData['start_day'] : null;
        } else {
            $dbData['start_year'] = null;
            $dbData['start_month'] = null;
            $dbData['start_day'] = null;
        }

        if (!empty($spreadsheetData['end_year'])) {
            $dbData['end_year'] = (int)$spreadsheetData['end_year'];
            $dbData['end_month'] = !empty($spreadsheetData['end_month']) ? (int)$spreadsheetData['end_month'] : null;
            $dbData['end_day'] = !empty($spreadsheetData['end_day']) ? (int)$spreadsheetData['end_day'] : null;
        } else {
            $dbData['end_year'] = null;
            $dbData['end_month'] = null;
            $dbData['end_day'] = null;
        }

        // Add connections if present
        if (!empty($spreadsheetData['connections'])) {
            $dbData['connections'] = $spreadsheetData['connections'];
        }

        return $dbData;
    }

    /**
     * Format date components to YAML format
     */
    private function formatDateForYaml($year, $month = null, $day = null): string
    {
        // Convert to integers and validate
        $year = is_numeric($year) ? (int)$year : null;
        $month = is_numeric($month) ? (int)$month : null;
        $day = is_numeric($day) ? (int)$day : null;
        
        if (!$year) return '';
        
        $date = $year;
        if ($month && $month > 0 && $month <= 12) {
            $date .= '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            if ($day && $day > 0 && $day <= 31) {
                $date .= '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
            }
        }
        
        return $date;
    }

    /**
     * Parse date from YAML format to components
     */
    private function parseDateFromYaml($date): array
    {
        if (!$date || is_array($date)) {
            return ['year' => null, 'month' => null, 'day' => null];
        }

        $parts = explode('-', (string)$date);
        
        return [
            'year' => isset($parts[0]) ? (int)$parts[0] : null,
            'month' => isset($parts[1]) ? (int)$parts[1] : null,
            'day' => isset($parts[2]) ? (int)$parts[2] : null
        ];
    }
    
    /**
     * Validate date ranges for reasonable values
     */
    private function validateDateRanges(array $spreadsheetData): array
    {
        $errors = [];
        
        // Validate core span dates
        $errors = array_merge($errors, $this->validateSingleDateRange(
            $spreadsheetData['start_year'] ?? null,
            $spreadsheetData['start_month'] ?? null,
            $spreadsheetData['start_day'] ?? null,
            $spreadsheetData['end_year'] ?? null,
            $spreadsheetData['end_month'] ?? null,
            $spreadsheetData['end_day'] ?? null,
            'span'
        ));
        
        // Validate connection dates
        if (!empty($spreadsheetData['connections'])) {
            foreach ($spreadsheetData['connections'] as $index => $connection) {
                $connectionErrors = $this->validateSingleDateRange(
                    $connection['start_year'] ?? null,
                    $connection['start_month'] ?? null,
                    $connection['start_day'] ?? null,
                    $connection['end_year'] ?? null,
                    $connection['end_month'] ?? null,
                    $connection['end_day'] ?? null,
                    "connection " . ($index + 1) . " ({$connection['object']})"
                );
                
                foreach ($connectionErrors as $error) {
                    $errors[] = $error;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single date range (for spans or connections)
     */
    private function validateSingleDateRange($startYear, $startMonth, $startDay, $endYear, $endMonth, $endDay, string $context): array
    {
        $errors = [];
        
        // Validate start year
        if (!empty($startYear)) {
            $startYear = (int)$startYear;
            if ($startYear < 1000 || $startYear > 9999) {
                $errors[] = "{$context}: Start year must be between 1000 and 9999, got {$startYear}";
            }
        }
        
        // Validate end year
        if (!empty($endYear)) {
            $endYear = (int)$endYear;
            if ($endYear < 1000 || $endYear > 9999) {
                $errors[] = "{$context}: End year must be between 1000 and 9999, got {$endYear}";
            }
        }
        
        // Validate start month
        if (!empty($startMonth)) {
            $startMonth = (int)$startMonth;
            if ($startMonth < 1 || $startMonth > 12) {
                $errors[] = "{$context}: Start month must be between 1 and 12, got {$startMonth}";
            }
        }
        
        // Validate end month
        if (!empty($endMonth)) {
            $endMonth = (int)$endMonth;
            if ($endMonth < 1 || $endMonth > 12) {
                $errors[] = "{$context}: End month must be between 1 and 12, got {$endMonth}";
            }
        }
        
        // Validate start day
        if (!empty($startDay)) {
            $startDay = (int)$startDay;
            if ($startDay < 1 || $startDay > 31) {
                $errors[] = "{$context}: Start day must be between 1 and 31, got {$startDay}";
            }
        }
        
        // Validate end day
        if (!empty($endDay)) {
            $endDay = (int)$endDay;
            if ($endDay < 1 || $endDay > 31) {
                $errors[] = "{$context}: End day must be between 1 and 31, got {$endDay}";
            }
        }
        
        // Validate that end date is after start date
        if (!empty($startYear) && !empty($endYear)) {
            $startYear = (int)$startYear;
            $endYear = (int)$endYear;
            
            if ($endYear < $startYear) {
                $errors[] = "{$context}: End year ({$endYear}) cannot be before start year ({$startYear})";
            } elseif ($endYear === $startYear) {
                // Check months if years are the same
                if (!empty($startMonth) && !empty($endMonth)) {
                    $startMonth = (int)$startMonth;
                    $endMonth = (int)$endMonth;
                    
                    if ($endMonth < $startMonth) {
                        $errors[] = "{$context}: End month ({$endMonth}) cannot be before start month ({$startMonth}) in the same year";
                    } elseif ($endMonth === $startMonth) {
                        // Check days if months are the same
                        if (!empty($startDay) && !empty($endDay)) {
                            $startDay = (int)$startDay;
                            $endDay = (int)$endDay;
                            
                            if ($endDay < $startDay) {
                                $errors[] = "{$context}: End day ({$endDay}) cannot be before start day ({$startDay}) in the same month";
                            }
                        }
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single connection row from the spreadsheet
     */
    public function validateConnectionRow(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        $connectionData = $request->input('connection');
        
        // Convert the connection data to YAML format for validation
        $yamlData = [
            'name' => $span->name,
            'type' => $span->type->type_id ?? 'unknown',
            'connections' => [
                $connectionData['predicate'] => [
                    [
                        'name' => $connectionData['object'],
                        'type' => 'connection',
                        'start' => $this->formatDateForYaml(
                            $connectionData['start_year'] ?? null,
                            $connectionData['start_month'] ?? null,
                            $connectionData['start_day'] ?? null
                        ),
                        'end' => $this->formatDateForYaml(
                            $connectionData['end_year'] ?? null,
                            $connectionData['end_month'] ?? null,
                            $connectionData['end_day'] ?? null
                        ),
                        'metadata' => $connectionData['metadata'] ?? []
                    ]
                ]
            ]
        ];
        
        // Use YamlValidationService to validate the connection
        $yamlValidationService = app(YamlValidationService::class);
        $validationErrors = $yamlValidationService->validateSchema($yamlData, $span->slug, $span);
        
        // Filter errors to only include connection-related errors
        $connectionErrors = array_filter($validationErrors, function($error) {
            return strpos($error, 'connection') !== false;
        });
        
        return response()->json([
            'success' => empty($connectionErrors),
            'errors' => array_values($connectionErrors)
        ]);
    }
    
    /**
     * Preview changes that would be made from spreadsheet data
     */
    public function previewSpreadsheetChanges(Request $request, Span $span)
    {
        $this->authorize('update', $span);
        
        try {
            Log::channel('spans')->info('Preview spreadsheet data', [
                'span_id' => $span->id,
                'input_keys' => array_keys($request->all())
            ]);
            
            // Convert spreadsheet data directly to database format (same as save process)
            $validated = $this->convertSpreadsheetDataToDatabase($request->all());
            
            // Get current span data for comparison (same format as validated data)
            $currentData = [
                'name' => $span->name,
                'slug' => $span->slug,
                'type' => $span->type->type_id ?? '',
                'state' => $span->state,
                'access_level' => $span->access_level,
                'description' => $span->description,
                'notes' => $span->notes,
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'metadata' => $span->metadata ?? [],
                'connections' => collect()
                    ->concat($span->connectionsAsSubject->map(function($conn) use ($span) {
                        return [
                            'subject' => $span->name,
                            'predicate' => $conn->type->type,
                            'object' => $conn->object->name ?? '',
                            'start_year' => $conn->connectionSpan?->start_year,
                            'start_month' => $conn->connectionSpan?->start_month,
                            'start_day' => $conn->connectionSpan?->start_day,
                            'end_year' => $conn->connectionSpan?->end_year,
                            'end_month' => $conn->connectionSpan?->end_month,
                            'end_day' => $conn->connectionSpan?->end_day,
                            'metadata' => $conn->connectionSpan?->metadata ?? []
                        ];
                    }))
                    ->concat($span->connectionsAsObject->map(function($conn) use ($span) {
                        return [
                            'subject' => $conn->subject->name ?? '',
                            'predicate' => $conn->type->type,
                            'object' => $span->name,
                            'start_year' => $conn->connectionSpan?->start_year,
                            'start_month' => $conn->connectionSpan?->start_month,
                            'start_day' => $conn->connectionSpan?->start_day,
                            'end_year' => $conn->connectionSpan?->end_year,
                            'end_month' => $conn->connectionSpan?->end_month,
                            'end_day' => $conn->connectionSpan?->end_day,
                            'metadata' => $conn->connectionSpan?->metadata ?? []
                        ];
                    }))
                    ->toArray()
            ];
            
            // Create a structured diff showing what will change
            try {
                $diff = $this->createStructuredDiff($currentData, $validated);
            } catch (\Exception $e) {
                Log::warning('Error creating diff', ['span_id' => $span->id, 'error' => $e->getMessage()]);
                $diff = [
                    'basic_fields' => [],
                    'metadata' => [],
                    'sources' => [],
                    'connections' => []
                ];
            }
            
            // Debug: Log what changes were detected
            Log::channel('spans')->info('Diff results', [
                'has_basic_field_changes' => !empty($diff['basic_fields']),
                'has_metadata_changes' => !empty($diff['metadata']),
                'has_connection_changes' => !empty($diff['connections']),
                'basic_fields_count' => count($diff['basic_fields']),
                'metadata_count' => count($diff['metadata']),
                'connections_count' => count($diff['connections']),
                'diff_summary' => $diff
            ]);
            
            return response()->json([
                'success' => true,
                'diff' => $diff,
                'current_data' => $currentData,
                'merged_data' => $validated,
                'message' => 'Preview generated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Spreadsheet preview error', [
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
}
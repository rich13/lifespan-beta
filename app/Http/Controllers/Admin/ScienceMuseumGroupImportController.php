<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScienceMuseumGroupApiService;
use App\Models\User;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ScienceMuseumGroupImportController extends Controller
{
    protected ScienceMuseumGroupApiService $smgService;

    public function __construct(ScienceMuseumGroupApiService $smgService)
    {
        $this->middleware(['auth', 'admin']);
        $this->smgService = $smgService;
    }

    /**
     * Show the Science Museum Group import interface
     */
    public function index()
    {
        return view('admin.import.science-museum-group.index');
    }

    /**
     * Search for objects in the Science Museum Group collection
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ]);

        $query = $request->input('query');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        try {
            $results = $this->smgService->searchObjects($query, $page, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search Science Museum Group objects', [
                'error' => $e->getMessage(),
                'query' => $query
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search objects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific object and its related data
     */
    public function getObjectData(Request $request)
    {
        $request->validate([
            'object_id' => 'required|string'
        ]);

        $objectId = $request->input('object_id');
        
        Log::info('SMG getObjectData called', ['object_id' => $objectId]);

        try {
            // Get the object data
            $objectData = $this->smgService->getObject($objectId);
            
            if (!$objectData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Object not found'
                ], 404);
            }

            $extractedObject = $this->smgService->extractObjectData($objectData);
            
            // Get related data
            $relatedData = [
                'object' => $extractedObject,
                'makers' => [],
                'places' => [],
                'images' => []
            ];

            // Get maker data
            foreach ($extractedObject['makers'] as $maker) {
                $makerData = $this->smgService->getPerson($maker['id']);
                if ($makerData) {
                    $relatedData['makers'][] = $this->smgService->extractPersonData($makerData);
                }
            }

            // Get place data
            foreach ($extractedObject['places'] as $place) {
                $placeData = $this->smgService->getPlace($place['id']);
                if ($placeData) {
                    $relatedData['places'][] = $this->smgService->extractPlaceData($placeData);
                }
            }

            // Process images
            $relatedData['images'] = $extractedObject['images'];

            return response()->json([
                'success' => true,
                'data' => $relatedData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Science Museum Group object data', [
                'error' => $e->getMessage(),
                'object_id' => $objectId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get object data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview the import - check for existing spans and prepare import data
     */
    public function preview(Request $request)
    {
        $request->validate([
            'object_id' => 'required|string'
        ]);

        $objectId = $request->input('object_id');

        try {
            // Get the object data
            $objectData = $this->smgService->getObject($objectId);
            
            if (!$objectData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Object not found'
                ], 404);
            }

            $extractedObject = $this->smgService->extractObjectData($objectData);
            
            // Add suggested thing subtype
            $extractedObject['suggested_subtype'] = $this->smgService->mapObjectTypeToSubtype($extractedObject['object_type']);
            
            // Get related data (makers, places, images)
            $relatedData = [
                'object' => $extractedObject,
                'makers' => [],
                'places' => [],
                'images' => []
            ];

            // Get maker data
            foreach ($extractedObject['makers'] as $maker) {
                $makerData = $this->smgService->getPerson($maker['id']);
                if ($makerData) {
                    $isOrg = $this->smgService->isOrganization($makerData);
                    
                    if ($isOrg) {
                        $orgData = $this->smgService->extractOrganizationData($makerData);
                        $orgData['is_organization'] = true;
                        $relatedData['makers'][] = $orgData;
                    } else {
                        $personData = $this->smgService->extractPersonData($makerData);
                        $personData['is_organization'] = false;
                        $relatedData['makers'][] = $personData;
                    }
                }
            }

            // Get place data
            foreach ($extractedObject['places'] as $place) {
                $placeData = $this->smgService->getPlace($place['id']);
                if ($placeData) {
                    $relatedData['places'][] = $this->smgService->extractPlaceData($placeData);
                }
            }

            // Process images
            $relatedData['images'] = $extractedObject['images'];
            
            // Check for existing spans using processed data
            $existingSpans = $this->findExistingSpans($extractedObject, $relatedData['makers'], $relatedData['places']);
            
            // Prepare import preview
            $preview = [
                'object' => $extractedObject,
                'makers' => $relatedData['makers'],
                'places' => $relatedData['places'],
                'images' => $relatedData['images'],
                'existing_spans' => $existingSpans,
                'import_plan' => $this->generateImportPlan($extractedObject, $existingSpans, $relatedData['makers'], $relatedData['places'])
            ];

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview Science Museum Group import', [
                'error' => $e->getMessage(),
                'object_id' => $objectId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform the actual import
     */
    public function import(Request $request)
    {
        $request->validate([
            'object_id' => 'required|string',
            'import_options' => 'array'
        ]);

        $objectId = $request->input('object_id');
        $importOptions = $request->input('import_options', []);

        try {
            DB::beginTransaction();

            // Get the object data
            $objectData = $this->smgService->getObject($objectId);
            
            if (!$objectData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Object not found'
                ], 404);
            }

            $extractedObject = $this->smgService->extractObjectData($objectData);
            
            // Use selected subtype or fall back to suggested
            $selectedSubtype = $importOptions['thing_subtype'] ?? null;
            $suggestedSubtype = $this->smgService->mapObjectTypeToSubtype($extractedObject['object_type']);
            $extractedObject['suggested_subtype'] = $selectedSubtype ?? $suggestedSubtype;
            
            // Process maker and place data (same as preview method)
            $relatedData = [
                'object' => $extractedObject,
                'makers' => [],
                'places' => [],
                'images' => []
            ];

            // Get maker data
            foreach ($extractedObject['makers'] as $maker) {
                $makerData = $this->smgService->getPerson($maker['id']);
                if ($makerData) {
                    $isOrg = $this->smgService->isOrganization($makerData);
                    
                    if ($isOrg) {
                        $orgData = $this->smgService->extractOrganizationData($makerData);
                        $orgData['is_organization'] = true;
                        $relatedData['makers'][] = $orgData;
                    } else {
                        $personData = $this->smgService->extractPersonData($makerData);
                        $personData['is_organization'] = false;
                        $relatedData['makers'][] = $personData;
                    }
                }
            }

            // Get place data
            foreach ($extractedObject['places'] as $place) {
                $placeData = $this->smgService->getPlace($place['id']);
                if ($placeData) {
                    $relatedData['places'][] = $this->smgService->extractPlaceData($placeData);
                }
            }

            // Process images
            $relatedData['images'] = $extractedObject['images'];
            
            // Perform the import with processed data
            $importResult = $this->performImport($extractedObject, $importOptions, $relatedData['makers'], $relatedData['places']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $importResult
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to import Science Museum Group object', [
                'error' => $e->getMessage(),
                'object_id' => $objectId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import object: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear the API cache
     */
    public function clearCache()
    {
        try {
            $this->smgService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear Science Museum Group cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find existing spans that match the import data
     */
    protected function findExistingSpans(array $objectData, array $processedMakers = [], array $processedPlaces = []): array
    {
        $existing = [
            'object' => null,
            'makers' => [],
            'places' => []
        ];

        // Check for existing object by title
        $existing['object'] = Span::where('name', $objectData['title'])
            ->whereHas('type', function($query) {
                $query->where('type_id', 'thing');
            })
            ->first();

        // Check for existing makers (check appropriate type based on processed data)
        $makersToCheck = !empty($processedMakers) ? $processedMakers : $objectData['makers'];
        
        foreach ($makersToCheck as $maker) {
            $makerName = $maker['name'] ?? '';
            $makerId = $maker['id'] ?? '';
            $isOrganization = $maker['is_organization'] ?? false;
            
            Log::info('Checking for existing maker', [
                'maker_name' => $makerName,
                'maker_id' => $makerId,
                'is_organization' => $isOrganization
            ]);
            
            if (empty($makerName)) {
                Log::info('Skipping maker with empty name', ['maker_id' => $makerId]);
                continue;
            }
            
            // Check for existing span based on the detected type
            $spanType = $isOrganization ? 'organisation' : 'person';
            
            // Try exact match first
            $existingSpan = Span::where('name', $makerName)
                ->whereHas('type', function($query) use ($spanType) {
                    $query->where('type_id', $spanType);
                })
                ->first();
            
            // If no exact match, try case-insensitive match
            if (!$existingSpan) {
                $existingSpan = Span::whereRaw('LOWER(name) = ?', [strtolower($makerName)])
                    ->whereHas('type', function($query) use ($spanType) {
                        $query->where('type_id', $spanType);
                    })
                    ->first();
            }
            
            // If still no match, try normalized comparison (remove punctuation, normalize spaces)
            if (!$existingSpan) {
                $normalizedName = preg_replace('/[^\w\s]/', '', strtolower(trim($makerName)));
                $existingSpan = Span::whereRaw('LOWER(REPLACE(REPLACE(name, \'.\', \'\'), \',\', \'\')) = ?', [$normalizedName])
                    ->whereHas('type', function($query) use ($spanType) {
                        $query->where('type_id', $spanType);
                    })
                    ->first();
            }
            
            if ($existingSpan) {
                Log::info('Found existing ' . $spanType, [
                    'maker_name' => $makerName,
                    'existing_span_name' => $existingSpan->name,
                    'existing_span_id' => $existingSpan->id,
                    'match_type' => $existingSpan->name === $makerName ? 'exact' : 'case_insensitive'
                ]);
                $existing['makers'][] = [
                    'smg_id' => $makerId,
                    'span' => $existingSpan,
                    'type' => $spanType
                ];
            } else {
                Log::info('No existing ' . $spanType . ' span found for maker', [
                    'maker_name' => $makerName,
                    'maker_id' => $makerId
                ]);
            }
        }

        // Check for existing places
        $placesToCheck = !empty($processedPlaces) ? $processedPlaces : $objectData['places'];
        
        foreach ($placesToCheck as $place) {
            $placeName = $place['name'] ?? '';
            $placeId = $place['id'] ?? '';
            
            if (empty($placeName)) {
                continue;
            }
            
            $existingPlace = Span::where('name', $placeName)
                ->whereHas('type', function($query) {
                    $query->where('type_id', 'place');
                })
                ->first();
            
            if ($existingPlace) {
                $existing['places'][] = [
                    'smg_id' => $placeId,
                    'span' => $existingPlace
                ];
            }
        }

        return $existing;
    }

    /**
     * Generate import plan based on existing spans
     */
    protected function generateImportPlan(array $objectData, array $existingSpans, array $processedMakers = [], array $processedPlaces = []): array
    {
        $plan = [
            'object' => [
                'action' => $existingSpans['object'] ? 'connect' : 'create',
                'span' => $existingSpans['object']
            ],
            'makers' => [],
            'places' => [],
            'images' => []
        ];

        // Plan for makers
        $makersToPlan = !empty($processedMakers) ? $processedMakers : $objectData['makers'];
        
        foreach ($makersToPlan as $maker) {
            $makerId = $maker['id'] ?? '';
            $existingMaker = collect($existingSpans['makers'])
                ->firstWhere('smg_id', $makerId);
            
            $plan['makers'][] = [
                'smg_data' => $maker,
                'action' => $existingMaker ? 'connect' : 'create',
                'span' => $existingMaker['span'] ?? null,
                'existing_type' => $existingMaker['type'] ?? null
            ];
        }

        // Plan for places
        $placesToPlan = !empty($processedPlaces) ? $processedPlaces : $objectData['places'];
        
        foreach ($placesToPlan as $place) {
            $placeId = $place['id'] ?? '';
            $existingPlace = collect($existingSpans['places'])
                ->firstWhere('smg_id', $placeId);
            
            $plan['places'][] = [
                'smg_data' => $place,
                'action' => $existingPlace ? 'connect' : 'create',
                'span' => $existingPlace['span'] ?? null
            ];
        }

        // Plan for images
        foreach ($objectData['images'] as $image) {
            $plan['images'][] = [
                'smg_data' => $image,
                'action' => 'create'
            ];
        }

        return $plan;
    }

    /**
     * Perform the actual import using the import plan
     */
    protected function performImport(array $objectData, array $importOptions, array $processedMakers = [], array $processedPlaces = []): array
    {
        $result = [
            'object' => null,
            'makers' => [],
            'places' => [],
            'images' => [],
            'connections' => []
        ];

        $user = Auth::user();

        // Get existing spans and generate import plan
        $existingSpans = $this->findExistingSpans($objectData, $processedMakers, $processedPlaces);
        $importPlan = $this->generateImportPlan($objectData, $existingSpans, $processedMakers, $processedPlaces);

        // Import or connect object
        if ($importOptions['import_object'] ?? true) {
            if ($importPlan['object']['action'] === 'connect') {
                $result['object'] = $importPlan['object']['span'];
                Log::info('Connected to existing object', ['name' => $result['object']->name]);
            } else {
                $result['object'] = $this->importObject($objectData, $user);
                if ($result['object']) {
                    Log::info('Created new object', ['name' => $result['object']->name]);
                } else {
                    Log::error('Failed to create object', ['object_data' => $objectData]);
                    throw new \Exception('Failed to create object span');
                }
            }
        }

        // Import or connect makers
        if ($importOptions['import_makers'] ?? true) {
            $makerTypeChoices = $importOptions['maker_type_choices'] ?? [];
            foreach ($importPlan['makers'] as $index => $makerPlan) {
                $userChoice = $makerTypeChoices[$index] ?? null;
                
                if ($makerPlan['action'] === 'connect') {
                    if ($makerPlan['span']) {
                        $result['makers'][] = $makerPlan['span'];
                        Log::info('Connected to existing maker', [
                            'name' => $makerPlan['span']->name,
                            'type' => $makerPlan['existing_type']
                        ]);
                    } else {
                        Log::error('Existing maker span is null', ['maker_plan' => $makerPlan]);
                        throw new \Exception('Existing maker span is null');
                    }
                } else {
                    $makerSpan = $this->importPerson($makerPlan['smg_data'], $user, $userChoice);
                    if ($makerSpan) {
                        $result['makers'][] = $makerSpan;
                        Log::info('Created new maker', ['name' => $makerSpan->name]);
                    } else {
                        Log::error('Failed to create maker', ['maker_data' => $makerPlan['smg_data']]);
                        throw new \Exception('Failed to create maker span');
                    }
                }
                
                // Create connection between object and maker
                if ($result['object'] && $result['makers'][$index]) {
                    $connection = $this->createConnection($result['object'], $result['makers'][$index], 'created', $user);
                    $result['connections'][] = $connection;
                }
            }
        }

        // Import or connect places
        if ($importOptions['import_places'] ?? true) {
            foreach ($importPlan['places'] as $placePlan) {
                if ($placePlan['action'] === 'connect') {
                    if ($placePlan['span']) {
                        $result['places'][] = $placePlan['span'];
                        Log::info('Connected to existing place', ['name' => $placePlan['span']->name]);
                    } else {
                        Log::error('Existing place span is null', ['place_plan' => $placePlan]);
                        throw new \Exception('Existing place span is null');
                    }
                } else {
                    $placeSpan = $this->importPlace($placePlan['smg_data'], $user);
                    if ($placeSpan) {
                        $result['places'][] = $placeSpan;
                        Log::info('Created new place', ['name' => $placeSpan->name]);
                    } else {
                        Log::warning('Skipping place that could not be created', ['place_data' => $placePlan['smg_data']]);
                        // Don't throw exception, just skip this place
                        continue;
                    }
                }
                
                // Create connection between object and place
                if ($result['object'] && $result['places'][count($result['places']) - 1]) {
                    $connection = $this->createConnection($result['object'], $result['places'][count($result['places']) - 1], 'located', $user);
                    $result['connections'][] = $connection;
                }
            }
        }

        // Import first image only (for testing)
        if ($importOptions['import_images'] ?? true) {
            $firstImage = $objectData['images'][0] ?? null;
            if ($firstImage) {
                $imageSpan = $this->importImage($firstImage, $user, $objectData);
                if ($imageSpan) {
                    $result['images'][] = $imageSpan;
                    
                    // Create connection between image and object (image is subject, object is object)
                    if ($result['object'] && $imageSpan) {
                        $connection = $this->createConnection($imageSpan, $result['object'], 'features', $user);
                        $result['connections'][] = $connection;
                    }
                } else {
                    Log::error('Failed to create image', ['image_data' => $firstImage]);
                    throw new \Exception('Failed to create image span');
                }
            }
        }

        return $result;
    }

    /**
     * Import or connect an object
     */
    protected function importObject(array $objectData, User $user): ?Span
    {
        // Check if object already exists
        $existingObject = Span::where('name', $objectData['title'])
            ->whereHas('type', function($query) {
                $query->where('type_id', 'thing');
            })
            ->first();

        if ($existingObject) {
            return $existingObject;
        }

        // Create new object span
        $thingType = SpanType::where('type_id', 'thing')->first();
        
        if (!$thingType) {
            Log::error('Thing span type not found');
            throw new \Exception('Thing span type not found in database');
        }
        
        Log::info('Creating thing span', [
            'name' => $objectData['title'],
            'type_id' => $thingType->type_id,
            'creation_date' => $objectData['creation_date'] ?? null
        ]);
        
        $span = Span::create([
            'name' => $objectData['title'],
            'type_id' => $thingType->type_id,
            'description' => $objectData['description'],
            'start_year' => $objectData['creation_date']['from'] ?? null,
            'end_year' => $objectData['creation_date']['to'] ?? null,
            'metadata' => [
                'subtype' => $objectData['suggested_subtype'] ?? 'artifact',
                'smg_id' => $objectData['id'],
                'smg_type' => $objectData['type'],
                'smg_object_type' => $objectData['object_type'],
                'identifiers' => json_encode($objectData['identifiers']),
                'categories' => json_encode($objectData['categories']),
                'source' => 'Science Museum Group'
            ],
            'sources' => [
                [
                    'type' => 'museum_collection',
                    'name' => 'Science Museum Group',
                    'url' => $objectData['links']['self'] ?? null,
                    'identifier' => $objectData['id']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'published'
        ]);

        return $span;
    }

    /**
     * Import or connect a person or organization
     */
    protected function importPerson(array $personData, User $user, ?string $userChoice = null): ?Span
    {
        // Get full data from SMG API
        $fullPersonData = $this->smgService->getPerson($personData['id']);
        if (!$fullPersonData) {
            return null;
        }

        // Use user's choice if provided, otherwise use our detection
        $isOrg = false;
        if ($userChoice) {
            $isOrg = ($userChoice === 'organisation');
        } else {
            $isOrg = $this->smgService->isOrganization($fullPersonData);
        }
        
        if ($isOrg) {
            return $this->importOrganization($personData, $user);
        } else {
            return $this->importIndividualPerson($personData, $user);
        }
    }

    /**
     * Import or connect an individual person
     */
    protected function importIndividualPerson(array $personData, User $user): ?Span
    {
        // Check if person already exists
        $existingPerson = Span::where('name', $personData['name'])
            ->whereHas('type', function($query) {
                $query->where('type_id', 'person');
            })
            ->first();

        if ($existingPerson) {
            return $existingPerson;
        }

        // Get person data from SMG API
        $fullPersonData = $this->smgService->getPerson($personData['id']);
        if (!$fullPersonData) {
            return null;
        }

        $extractedPerson = $this->smgService->extractPersonData($fullPersonData);

        // Create new person span
        $personType = SpanType::where('type_id', 'person')->first();
        
        if (!$personType) {
            Log::error('Person span type not found');
            throw new \Exception('Person span type not found in database');
        }
        
        // Extract birth and death dates
        $birthDate = $extractedPerson['birth_date'];
        $deathDate = $extractedPerson['death_date'];
        
        $startYear = $birthDate['value'] ?? $birthDate['from'] ?? null;
        $endYear = $deathDate['value'] ?? $deathDate['from'] ?? null;
        
        Log::info('Creating person span with dates', [
            'name' => $extractedPerson['name'],
            'birth_date' => $birthDate,
            'death_date' => $deathDate,
            'start_year' => $startYear,
            'end_year' => $endYear
        ]);
        
        $span = Span::create([
            'name' => $extractedPerson['name'],
            'type_id' => $personType->type_id,
            'description' => $extractedPerson['biography'],
            'start_year' => $startYear,
            'start_month' => $startYear ? 1 : null,
            'start_day' => $startYear ? 1 : null,
            'end_year' => $endYear,
            'end_month' => $endYear ? 1 : null,
            'end_day' => $endYear ? 1 : null,
            'metadata' => [
                'smg_id' => $extractedPerson['id'],
                'smg_type' => $extractedPerson['type'],
                'nationality' => is_array($extractedPerson['nationality']) ? implode(', ', $extractedPerson['nationality']) : $extractedPerson['nationality'],
                'occupation' => is_array($extractedPerson['occupation']) ? implode(', ', $extractedPerson['occupation']) : $extractedPerson['occupation'],
                'gender' => $extractedPerson['gender'],
                'birth_place' => $extractedPerson['birth_place'],
                'death_place' => $extractedPerson['death_place'],
                'source' => 'Science Museum Group'
            ],
            'sources' => [
                [
                    'type' => 'museum_collection',
                    'name' => 'Science Museum Group',
                    'url' => $extractedPerson['links']['self'] ?? null,
                    'identifier' => $extractedPerson['id']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'published'
        ]);

        return $span;
    }

    /**
     * Import or connect an organization
     */
    protected function importOrganization(array $personData, User $user): ?Span
    {
        // Check if organization already exists
        $existingOrg = Span::where('name', $personData['name'])
            ->whereHas('type', function($query) {
                $query->where('type_id', 'organisation');
            })
            ->first();

        if ($existingOrg) {
            return $existingOrg;
        }

        // Get organization data from SMG API
        $fullOrgData = $this->smgService->getPerson($personData['id']);
        if (!$fullOrgData) {
            return null;
        }

        $extractedOrg = $this->smgService->extractOrganizationData($fullOrgData);

        // Create new organization span
        $orgType = SpanType::where('type_id', 'organisation')->first();
        
        if (!$orgType) {
            Log::error('Organisation span type not found');
            throw new \Exception('Organisation span type not found in database');
        }
        
        // Extract founding and dissolution dates
        $foundingDate = $extractedOrg['founding_date'];
        $dissolutionDate = $extractedOrg['dissolution_date'];
        
        $startYear = $foundingDate['value'] ?? $foundingDate['from'] ?? null;
        $endYear = $dissolutionDate['value'] ?? $dissolutionDate['from'] ?? null;
        
        // Determine if we have proper dates or need to create a placeholder
        $hasProperDates = $startYear !== null;
        $state = $hasProperDates ? 'published' : 'placeholder';
        
        Log::info('Creating organisation span with dates', [
            'name' => $extractedOrg['name'],
            'founding_date' => $foundingDate,
            'dissolution_date' => $dissolutionDate,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'state' => $state
        ]);
        
        $span = Span::create([
            'name' => $extractedOrg['name'],
            'type_id' => $orgType->type_id,
            'description' => $extractedOrg['description'],
            'start_year' => $startYear,
            'start_month' => $startYear ? 1 : null,
            'start_day' => $startYear ? 1 : null,
            'end_year' => $endYear,
            'end_month' => $endYear ? 1 : null,
            'end_day' => $endYear ? 1 : null,
            'metadata' => [
                'smg_id' => $extractedOrg['id'],
                'smg_type' => $extractedOrg['type'],
                'nationality' => is_array($extractedOrg['nationality']) ? implode(', ', $extractedOrg['nationality']) : $extractedOrg['nationality'],
                'occupation' => is_array($extractedOrg['occupation']) ? implode(', ', $extractedOrg['occupation']) : $extractedOrg['occupation'],
                'founding_place' => $extractedOrg['founding_place'],
                'source' => 'Science Museum Group'
            ],
            'sources' => [
                [
                    'type' => 'museum_collection',
                    'name' => 'Science Museum Group',
                    'url' => $extractedOrg['links']['self'] ?? null,
                    'identifier' => $extractedOrg['id']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => $state
        ]);

        return $span;
    }

    /**
     * Import or connect a place
     */
    protected function importPlace(array $placeData, User $user): ?Span
    {
        // Check if place already exists
        $existingPlace = Span::where('name', $placeData['name'])
            ->whereHas('type', function($query) {
                $query->where('type_id', 'place');
            })
            ->first();

        if ($existingPlace) {
            return $existingPlace;
        }

        // Use the processed place data directly (it's already been fetched and extracted)
        $extractedPlace = $placeData;

        // Check if place has a valid name
        if (empty($extractedPlace['name'])) {
            Log::warning('Skipping place with empty name', ['place_data' => $placeData]);
            return null;
        }

        // Create new place span
        $placeType = SpanType::where('type_id', 'place')->first();
        
        if (!$placeType) {
            Log::error('Place span type not found');
            throw new \Exception('Place span type not found in database');
        }
        
        $span = Span::create([
            'name' => $extractedPlace['name'],
            'type_id' => $placeType->type_id,
            'description' => $extractedPlace['description'],
            'metadata' => [
                'smg_id' => $extractedPlace['id'],
                'smg_type' => $extractedPlace['type'],
                'source' => 'Science Museum Group'
            ],
            'sources' => [
                [
                    'type' => 'museum_collection',
                    'name' => 'Science Museum Group',
                    'url' => $extractedPlace['links']['self'] ?? null,
                    'identifier' => $extractedPlace['id']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'published'
        ]);

        return $span;
    }

    /**
     * Import an image
     */
    protected function importImage(array $imageData, User $user, ?array $objectData = null): ?Span
    {
        // Create new image span (using thing type with photo subtype)
        $thingType = SpanType::where('type_id', 'thing')->first();
        
        if (!$thingType) {
            Log::error('Thing span type not found');
            throw new \Exception('Thing span type not found in database');
        }
        
        // Generate a meaningful name for the image
        $objectTitle = $objectData['title'] ?? 'Unknown Object';
        $imageName = $imageData['title'] ?? 
                    ($imageData['credit'] ? "Image: {$imageData['credit']}" : "Image: {$objectTitle}");
        
        // Create description from available metadata
        $description = '';
        if ($imageData['description']) {
            $description = $imageData['description'];
        } elseif ($imageData['credit']) {
            $description = $imageData['credit'];
        } else {
            $description = 'Image from Science Museum Group collection';
        }
        
        // Add photographer info if available
        if ($imageData['photographer']) {
            $description .= " Photographer: {$imageData['photographer']}";
        }
        
        // Determine the date for the photo span
        $startYear = null;
        $startMonth = null;
        $startDay = null;
        $state = 'placeholder';
        
        // First try to use the image's own date if available
        if (!empty($imageData['date'])) {
            // Try to parse the image date
            if (preg_match('/^\d{4}$/', $imageData['date'])) {
                $startYear = (int)$imageData['date'];
                $state = 'complete';
            } elseif (preg_match('/^(\d{4})-(\d{1,2})$/', $imageData['date'], $matches)) {
                $startYear = (int)$matches[1];
                $startMonth = (int)$matches[2];
                $state = 'complete';
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $imageData['date'], $matches)) {
                $startYear = (int)$matches[1];
                $startMonth = (int)$matches[2];
                $startDay = (int)$matches[3];
                $state = 'complete';
            }
        }
        
        // If no image date, try to use the object's creation date as fallback
        if ($startYear === null && $objectData && isset($objectData['creation_date'])) {
            $creationDate = $objectData['creation_date'];
            if (isset($creationDate['from'])) {
                $startYear = (int)$creationDate['from'];
                $state = 'complete';
            } elseif (isset($creationDate['value'])) {
                $startYear = (int)$creationDate['value'];
                $state = 'complete';
            }
        }
        
        // If still no date, set as placeholder
        if ($startYear === null) {
            $state = 'placeholder';
        }
        
        Log::info('Creating photo span', [
            'name' => $imageName,
            'description' => $description,
            'start_year' => $startYear,
            'state' => $state,
            'metadata' => $imageData
        ]);
        
        $span = Span::create([
            'name' => $imageName,
            'type_id' => $thingType->type_id,
            'description' => $description,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $startYear, // Photo spans typically have same start/end date
            'end_month' => $startMonth,
            'end_day' => $startDay,
            'metadata' => [
                'subtype' => 'photo',
                'thumbnail_url' => $imageData['url'], // large_thumbnail from SMG
                'medium_url' => $imageData['alt_url'], // medium from SMG
                'large_url' => $imageData['full_url'], // large from SMG
                'original_url' => $imageData['full_url'], // Use large as original
                'credit' => $imageData['credit'],
                'title' => $imageData['title'],
                'description' => $imageData['description'],
                'date' => $imageData['date'],
                'photographer' => $imageData['photographer'],
                'copyright' => $imageData['copyright'],
                'license' => $imageData['license'],
                'media_type' => $imageData['media_type'],
                'admin_uid' => $imageData['admin_uid'],
                'source' => 'Science Museum Group'
            ],
            'sources' => [
                [
                    'type' => 'museum_collection',
                    'name' => 'Science Museum Group',
                    'url' => $objectData['links']['self'] ?? null,
                    'credit' => $imageData['credit'],
                    'photographer' => $imageData['photographer']
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => $state
        ]);

        return $span;
    }

    /**
     * Create a connection between two spans
     */
    protected function createConnection(Span $subject, Span $object, string $connectionType, User $user): ?\App\Models\Connection
    {
        // Find or create connection type
        $connectionTypeModel = \App\Models\ConnectionType::firstOrCreate([
            'type' => $connectionType
        ], [
            'forward_predicate' => $connectionType,
            'forward_description' => "Connection type for {$connectionType}",
            'inverse_predicate' => 'is_' . $connectionType . '_of',
            'inverse_description' => "Inverse connection type for {$connectionType}",
            'constraint_type' => 'single'
        ]);

        // Create connection span first
        $connectionSpan = \App\Models\Span::create([
            'name' => "{$subject->name} {$connectionType} {$object->name}",
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'placeholder',
            'metadata' => [
                'connection_type' => $connectionType,
                'source' => 'Science Museum Group Import'
            ]
        ]);

        // Create connection with connection_span_id
        $connection = \App\Models\Connection::create([
            'type_id' => $connectionTypeModel->type,
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'connection_span_id' => $connectionSpan->id
        ]);

        return $connection;
    }
}

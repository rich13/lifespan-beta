<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SpanSearchController extends Controller
{
    /**
     * Search for spans based on query and optional type
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $type = $request->get('type');
        $excludeConnected = $request->get('exclude_connected', false);
        $excludeSets = $request->get('exclude_sets', false);
        $user = Auth::user();

        $results = collect();

        // Start with spans the user can see, excluding connections
        $spans = Span::query()->whereNot('type_id', 'connection');
        
        // Exclude sets if requested
        if ($excludeSets) {
            $spans->whereNot('type_id', 'set');
        }
        
        if ($user) {
            // Authenticated user - can see public, owned, and shared spans
            $spans->where(function ($q) use ($user) {
                $q->where('access_level', 'public')
                    ->orWhere('owner_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('access_level', 'shared')
                            ->whereHas('spanPermissions', function ($q) use ($user) {
                                $q->where(function($subQ) use ($user) {
                                    $subQ->where('user_id', $user->id)
                                         ->orWhereHas('group', function($groupQ) use ($user) {
                                             $groupQ->whereHas('users', function($userQ) use ($user) {
                                                 $userQ->where('users.id', $user->id);
                                             });
                                         });
                                });
                            });
                    });
            });
            
            // Exclude people already connected to the current user
            if ($excludeConnected && $user->personalSpan) {
                $connectedPersonIds = collect();
                
                // Get friends
                $friends = $user->personalSpan->friends()->pluck('id');
                $connectedPersonIds = $connectedPersonIds->merge($friends);
                
                // Get relationships
                $relationships = $user->personalSpan->relationships()->pluck('id');
                $connectedPersonIds = $connectedPersonIds->merge($relationships);
                
                if ($connectedPersonIds->isNotEmpty()) {
                    $spans->whereNotIn('id', $connectedPersonIds->unique());
                }
            }
        } else {
            // Unauthenticated user - can only see public spans
            $spans->where('access_level', 'public');
        }

        // Support multiple types (comma-separated) - takes precedence over single type
        $types = $request->get('types');
        $typeArray = [];
        if ($types) {
            $typeArray = explode(',', $types);
            $spans->whereIn('type_id', $typeArray);
        } elseif ($type) {
            // Add type restriction if specified (only if no multiple types)
            $spans->where('type_id', $type);
        }

        // Add subtype restriction if specified
        $subtype = $request->get('subtype');
        if ($subtype) {
            $spans->whereJsonContains('metadata->subtype', $subtype);
        }

        // Add admin_level restriction if specified (for place spans)
        $adminLevel = $request->get('admin_level');
        if ($adminLevel && ($type === 'place' || (!empty($typeArray) && in_array('place', $typeArray)))) {
            $adminLevelInt = (int) $adminLevel;
            
            // Map admin_level to subtype for reverse lookup
            $levelToSubtype = [
                2 => 'country',
                4 => 'state_region',
                6 => 'county_province',
                8 => 'city_district',
                9 => 'city_district', // Boroughs often map to city_district
                10 => 'suburb_area',
                12 => 'neighbourhood',
                14 => 'sub_neighbourhood',
                16 => 'building_property'
            ];
            $matchingSubtype = $levelToSubtype[$adminLevelInt] ?? null;
            
            // Filter by admin_level - check multiple possible locations in metadata
            $spans->where(function ($q) use ($adminLevelInt, $matchingSubtype) {
                // Check osm_data->admin_level (most common location)
                $q->whereRaw("(metadata->'osm_data'->>'admin_level')::int = ?", [$adminLevelInt])
                  // Also check administrative_level (alternative location for auto-created spans)
                  ->orWhereRaw("(metadata->>'administrative_level')::int = ?", [$adminLevelInt]);
                
                // Also check subtype if we have a mapping (subtype is derived from admin_level)
                if ($matchingSubtype) {
                    $q->orWhereJsonContains('metadata->subtype', $matchingSubtype);
                }
            });
        }

        // Add owner restriction if specified
        $ownerId = $request->get('owner_id');
        if ($ownerId) {
            $spans->where('owner_id', $ownerId);
        }

        // Search by name
        if ($query) {
            $spans->where('name', 'ilike', "%{$query}%");
        }

        // Apply temporal filtering if specified
        $temporalRelation = $request->get('temporal_relation');
        $temporalSpanId = $request->get('temporal_span_id');
        
        if ($temporalRelation && $temporalSpanId) {
            $temporalSpan = Span::find($temporalSpanId);
            if ($temporalSpan) {
                // Use the temporal method from the Span model
                $temporalSpans = $temporalSpan->getTemporalSpans($temporalRelation, [
                    'type_id' => $type,
                    'subtype' => $subtype,
                    'owner_id' => $ownerId,
                    'limit' => 100 // Get more results for temporal filtering
                ], $user);
                
                // Get the IDs of temporal spans and filter the main query
                $temporalSpanIds = $temporalSpans->pluck('id');
                if ($temporalSpanIds->isNotEmpty()) {
                    $spans->whereIn('id', $temporalSpanIds);
                } else {
                    // No temporal matches, return empty results
                    $spans->whereRaw('1 = 0');
                }
            }
        }

        // Get limit from request, default to 10
        $limit = (int) $request->get('limit', 10);

        // Get existing results with type information (including placeholders)
        $existingResults = $spans->with('type')
            ->limit($limit)
            ->get()
            ->map(function ($span) {
                return [
                    'id' => $span->id,
                    'name' => $span->name,
                    'type_id' => $span->type_id,
                    'type_name' => $span->type->name,
                    'state' => $span->state,
                    'is_placeholder' => $span->state === 'placeholder',
                    'subtype' => $span->subtype,
                    'metadata' => is_array($span->metadata) ? $span->metadata : ($span->metadata ? (array) $span->metadata : [])
                ];
            });

        $results = $results->merge($existingResults);

        // Note: We no longer add placeholder suggestions with null IDs
        // All spans must have IDs. If a placeholder span exists, it will be in $existingResults

        return response()->json([
            'spans' => $results->take($limit)->values()
        ]);
    }

    /**
     * Create a new placeholder span
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'required|string|exists:span_types,type_id',
            'state' => 'required|in:placeholder',
            'metadata' => 'nullable|array'
        ]);

        $span = new Span($validated);
        $span->owner_id = Auth::id();
        $span->updater_id = Auth::id();
        
        // Ensure metadata is an array
        if (!isset($span->metadata) || !is_array($span->metadata)) {
            $span->metadata = [];
        }
        
        $span->save();

        return response()->json([
            'id' => $span->id,
            'name' => $span->name,
            'type_id' => $span->type_id,
            'state' => $span->state,
            'metadata' => $span->metadata
        ]);
    }

    /**
     * Get spans with a specific temporal relationship to the given span
     */
    public function temporal(Span $span, Request $request)
    {
        $user = Auth::user();
        $isPublic = $span->isPublic();
        $hasPermission = $span->hasPermission($user, 'view');

        // Check access permissions
        if (!$isPublic && (!$user || !$hasPermission)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $relation = $request->get('relation', 'during');
        $filters = $request->only(['type_id', 'subtype', 'owner_id', 'state', 'limit', 'order_by', 'order_direction']);

        // Cache key includes user ID and filters for proper caching
        $cacheKey = "temporal_{$span->id}_{$relation}_" . ($user?->id ?? 'guest') . "_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($span, $relation, $filters, $user) {
            $temporalSpans = $span->getTemporalSpans($relation, $filters, $user);
            
            return response()->json([
                'span_id' => $span->id,
                'span_name' => $span->name,
                'relation' => $relation,
                'spans' => $temporalSpans->map(function ($temporalSpan) {
                    return [
                        'id' => $temporalSpan->id,
                        'name' => $temporalSpan->name,
                        'type_id' => $temporalSpan->type_id,
                        'subtype' => $temporalSpan->subtype,
                        'start_year' => $temporalSpan->start_year,
                        'start_month' => $temporalSpan->start_month,
                        'start_day' => $temporalSpan->start_day,
                        'end_year' => $temporalSpan->end_year,
                        'end_month' => $temporalSpan->end_month,
                        'end_day' => $temporalSpan->end_day,
                        'description' => $temporalSpan->description,
                        'metadata' => $temporalSpan->metadata,
                    ];
                })
            ]);
        });
    }

    /**
     * Get timeline data for a span (all connection types)
     */
    public function timeline(Span $span)
    {
        $user = Auth::user();
        $isPublic = $span->isPublic();
        $hasPermission = $span->hasPermission($user, 'view');

        // Check access permissions
        if (!$isPublic && (!$user || !$hasPermission)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cache key includes user ID for proper access control
        $cacheKey = "timeline_{$span->id}_" . ($user?->id ?? 'guest');

        $data = Cache::remember($cacheKey, 300, function () use ($span, $user) {
            // Get all connections (not just accessible ones) for timeline display
            $connections = $span->connectionsAsSubject()
                ->with([
                    'child:id,name,type_id,start_year,end_year,metadata,access_level,owner_id',
                    'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                    'type:type,forward_predicate'
                ])
                ->whereHas('connectionSpan', function ($query) {
                    $query->whereNotNull('start_year');
                })
                ->get()
                ->map(function ($connection) use ($user) {
                    $connectionSpan = $connection->connectionSpan;
                    
                    // Check if user can access the target span
                    $targetAccessible = $connection->child->isAccessibleBy($user);
                    
                    $connectionData = [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->forward_predicate ?? $connection->type_id,
                        'target_name' => $targetAccessible ? $connection->child->name : 'Private Person',
                        'target_id' => $targetAccessible ? $connection->child->id : null,
                        'target_type' => $connection->child->type_id,
                        'target_metadata' => $targetAccessible ? ($connection->child->metadata ?? []) : [],
                        'target_accessible' => $targetAccessible,
                        'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                        'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                        'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                        'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                        'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                        'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        'metadata' => $connection->metadata ?? []
                    ];
                    
                    // Only load nested connections if this connection has a span
                    if ($connectionSpan) {
                        $connectionData['nested_connections'] = $this->getNestedConnections($connectionSpan);
                    }
                    
                    return $connectionData;
                })
                // Filter out unwanted connections at the source
                ->filter(function ($connection) {
                    if (
                        $connection['type_id'] === 'created' && (
                            $connection['target_type'] === 'set' ||
                            (
                                $connection['target_type'] === 'thing' &&
                                isset($connection['target_metadata']['subtype']) &&
                                ($connection['target_metadata']['subtype'] === 'photo' || $connection['target_metadata']['subtype'] === 'set')
                            )
                        )
                    ) {
                        return false;
                    }
                    return $connection['start_year'] !== null;
                })
                ->sortBy('start_year')
                ->values();

            return [
                'span' => [
                    'id' => $span->id,
                    'name' => $span->name,
                    'start_year' => $span->start_year,
                    'end_year' => $span->end_year
                ],
                'connections' => $connections
            ];
        });

        return response()->json($data)->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Get nested connections for a connection span (optimized)
     */
    private function getNestedConnections(Span $connectionSpan): array
    {
        return $connectionSpan->connectionsAsObjectWithAccess()
            ->where('type_id', 'during')
            ->with([
                'parent:id,name,type_id,metadata',
                'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                'type:type,inverse_predicate'
            ])
            ->whereHas('connectionSpan', function ($query) {
                $query->whereNotNull('start_year');
            })
            ->get()
            ->map(function ($duringConnection) {
                $duringConnectionSpan = $duringConnection->connectionSpan;
                return [
                    'id' => $duringConnection->id,
                    'type_id' => $duringConnection->type_id,
                    'type_name' => $duringConnection->type->inverse_predicate ?? $duringConnection->type_id,
                    'target_name' => $duringConnection->parent->name,
                    'target_id' => $duringConnection->parent->id,
                    'target_type' => $duringConnection->parent->type_id,
                    'target_metadata' => $duringConnection->parent->metadata ?? [],
                    'start_year' => $duringConnectionSpan ? $duringConnectionSpan->start_year : null,
                    'start_month' => $duringConnectionSpan ? $duringConnectionSpan->start_month : null,
                    'start_day' => $duringConnectionSpan ? $duringConnectionSpan->start_day : null,
                    'end_year' => $duringConnectionSpan ? $duringConnectionSpan->end_year : null,
                    'end_month' => $duringConnectionSpan ? $duringConnectionSpan->end_month : null,
                    'end_day' => $duringConnectionSpan ? $duringConnectionSpan->end_day : null,
                    'metadata' => $duringConnection->metadata ?? [],
                    'is_nested' => true,
                    'parent_connection_id' => $duringConnection->parent_id
                ];
            })
            // Filter out unwanted nested connections at the source
            ->filter(function ($connection) {
                if (
                    $connection['type_id'] === 'created' && (
                        $connection['target_type'] === 'set' ||
                        (
                            $connection['target_type'] === 'thing' &&
                            isset($connection['target_metadata']['subtype']) &&
                            ($connection['target_metadata']['subtype'] === 'photo' || $connection['target_metadata']['subtype'] === 'set')
                        )
                    )
                ) {
                    return false;
                }
                return $connection['start_year'] !== null;
            })
            ->values()
            ->toArray();
    }

    /**
     * Get timeline data for object connections (where span is the child/object)
     */
    public function timelineObjectConnections(Span $span)
    {
        // Check access permissions
        $user = Auth::user();
        if (!$span->isPublic() && (!$user || !$span->hasPermission($user, 'view'))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cache key includes user ID for proper access control
        $cacheKey = "timeline_object_{$span->id}_" . ($user?->id ?? 'guest');

        $data = Cache::remember($cacheKey, 300, function () use ($span) {
            // Optimized query with eager loading and joins - with access control
            $connections = $span->connectionsAsObjectWithAccess()
                ->where('type_id', '!=', 'during')
                ->with([
                    'parent:id,name,type_id,start_year,end_year,metadata',
                    'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                    'type:type,inverse_predicate'
                ])
                ->whereHas('connectionSpan', function ($query) {
                    $query->whereNotNull('start_year');
                })
                ->get()
                ->map(function ($connection) {
                    $connectionSpan = $connection->connectionSpan;
                    return [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->inverse_predicate ?? $connection->type_id,
                        'target_name' => $connection->parent->name,
                        'target_id' => $connection->parent->id,
                        'target_type' => $connection->parent->type_id,
                        'target_metadata' => $connection->parent->metadata ?? [],
                        'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                        'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                        'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                        'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                        'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                        'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        'metadata' => $connection->metadata ?? []
                    ];
                })
                ->filter(function ($connection) {
                    return $connection['start_year'] !== null;
                })
                ->sortBy('start_year')
                ->values();

            return [
                'span' => [
                    'id' => $span->id,
                    'name' => $span->name,
                    'start_year' => $span->start_year,
                    'end_year' => $span->end_year
                ],
                'connections' => $connections
            ];
        });

        return response()->json($data)->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Get "during" connections for a span (spans that occur during this span)
     */
    public function timelineDuringConnections(Span $span)
    {
        // Check access permissions
        $user = Auth::user();
        if (!$span->isPublic() && (!$user || !$span->hasPermission($user, 'view'))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cache key includes user ID for proper access control
        $cacheKey = "timeline_during_{$span->id}_" . ($user?->id ?? 'guest');

        $data = Cache::remember($cacheKey, 300, function () use ($span) {
            // Optimized query with eager loading and joins - with access control
            $connections = $span->connectionsAsObjectWithAccess()
                ->where('type_id', 'during')
                ->with([
                    'parent:id,name,type_id,start_year,end_year,metadata',
                    'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                    'type:type,inverse_predicate'
                ])
                ->whereHas('connectionSpan', function ($query) {
                    $query->whereNotNull('start_year');
                })
                ->get()
                ->map(function ($connection) {
                    $connectionSpan = $connection->connectionSpan;
                    return [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->inverse_predicate ?? $connection->type_id,
                        'target_name' => $connection->parent->name,
                        'target_id' => $connection->parent->id,
                        'target_type' => $connection->parent->type_id,
                        'target_metadata' => $connection->parent->metadata ?? [],
                        'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                        'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                        'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                        'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                        'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                        'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        'metadata' => $connection->metadata ?? []
                    ];
                })
                ->filter(function ($connection) {
                    return $connection['start_year'] !== null;
                })
                ->sortBy('start_year')
                ->values();

            return [
                'span' => [
                    'id' => $span->id,
                    'name' => $span->name,
                    'start_year' => $span->start_year,
                    'end_year' => $span->end_year
                ],
                'connections' => $connections
            ];
        });

        return response()->json($data)->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Get timeline data for multiple spans in a single request (batch endpoint)
     * This reduces the number of API calls when loading timelines for many spans
     */
    public function batchTimeline(Request $request)
    {
        $request->validate([
            'span_ids' => ['required', 'array', 'max:100'], // Limit to 100 spans per request
            'span_ids.*' => ['required', 'uuid', 'exists:spans,id'],
        ]);

        $spanIds = $request->input('span_ids');
        $user = Auth::user();
        
        // Fetch all spans and check permissions
        $spans = Span::whereIn('id', $spanIds)->get()->keyBy('id');
        $accessibleSpanIds = [];
        
        foreach ($spanIds as $spanId) {
            if (!isset($spans[$spanId])) {
                continue;
            }
            $span = $spans[$spanId];
            if ($span->isPublic() || ($user && $span->hasPermission($user, 'view'))) {
                $accessibleSpanIds[] = $spanId;
            }
        }

        if (empty($accessibleSpanIds)) {
            return response()->json(['results' => []]);
        }

        // Load all connections for accessible spans in batch
        $subjectConnections = Connection::whereIn('parent_id', $accessibleSpanIds)
            ->whereHas('connectionSpan', function ($q) {
                $q->whereNotNull('start_year');
            })
            ->with([
                'child:id,name,type_id,start_year,end_year,metadata,access_level,owner_id',
                'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                'type:type,forward_predicate'
            ])
            ->get()
            ->groupBy('parent_id');

        $objectConnections = Connection::whereIn('child_id', $accessibleSpanIds)
            ->where('type_id', 'during')
            ->whereHas('connectionSpan', function ($q) {
                $q->whereNotNull('start_year');
            })
            ->with([
                'parent:id,name,type_id,start_year,end_year,metadata',
                'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                'type:type,inverse_predicate'
            ])
            ->get()
            ->groupBy('child_id');

        $results = [];

        foreach ($accessibleSpanIds as $spanId) {
            $span = $spans[$spanId];

            // Process subject connections (timeline data)
            $connections = ($subjectConnections[$spanId] ?? collect())
                ->map(function ($connection) use ($user) {
                    $connectionSpan = $connection->connectionSpan;
                    
                    // Check if user can access the target span
                    $targetAccessible = $connection->child->isAccessibleBy($user);
                    
                    $connectionData = [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->forward_predicate ?? $connection->type_id,
                        'target_name' => $targetAccessible ? $connection->child->name : 'Private Person',
                        'target_id' => $targetAccessible ? $connection->child->id : null,
                        'target_type' => $connection->child->type_id,
                        'target_metadata' => $targetAccessible ? ($connection->child->metadata ?? []) : [],
                        'target_accessible' => $targetAccessible,
                        'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                        'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                        'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                        'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                        'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                        'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        'metadata' => $connection->metadata ?? []
                    ];
                    
                    // Only load nested connections if this connection has a span
                    if ($connectionSpan) {
                        $connectionData['nested_connections'] = $this->getNestedConnections($connectionSpan);
                    }
                    
                    return $connectionData;
                })
                ->filter(function ($connection) {
                    if (
                        $connection['type_id'] === 'created' && (
                            $connection['target_type'] === 'set' ||
                            (
                                $connection['target_type'] === 'thing' &&
                                isset($connection['target_metadata']['subtype']) &&
                                ($connection['target_metadata']['subtype'] === 'photo' || $connection['target_metadata']['subtype'] === 'set')
                            )
                        )
                    ) {
                        return false;
                    }
                    return $connection['start_year'] !== null;
                })
                ->sortBy('start_year')
                ->values();

            // Process object connections (during connections)
            $duringConnections = ($objectConnections[$spanId] ?? collect())
                ->map(function ($connection) {
                    $connectionSpan = $connection->connectionSpan;
                    return [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->inverse_predicate ?? $connection->type_id,
                        'target_name' => $connection->parent->name,
                        'target_id' => $connection->parent->id,
                        'target_type' => $connection->parent->type_id,
                        'target_metadata' => $connection->parent->metadata ?? [],
                        'start_year' => $connectionSpan ? $connectionSpan->start_year : null,
                        'start_month' => $connectionSpan ? $connectionSpan->start_month : null,
                        'start_day' => $connectionSpan ? $connectionSpan->start_day : null,
                        'end_year' => $connectionSpan ? $connectionSpan->end_year : null,
                        'end_month' => $connectionSpan ? $connectionSpan->end_month : null,
                        'end_day' => $connectionSpan ? $connectionSpan->end_day : null,
                        'metadata' => $connection->metadata ?? []
                    ];
                })
                ->filter(function ($connection) {
                    return $connection['start_year'] !== null;
                })
                ->sortBy('start_year')
                ->values();

            $results[$spanId] = [
                'span' => [
                    'id' => $span->id,
                    'name' => $span->name,
                    'start_year' => $span->start_year,
                    'end_year' => $span->end_year
                ],
                'connections' => $connections,
                'during_connections' => $duringConnections
            ];
        }

        return response()->json([
            'results' => $results
        ]);
    }

    /**
     * Get family graph data for a person span
     */
    public function familyGraph(Span $span)
    {
        $user = Auth::user();

        // Check if the user can access this span
        if (!$span->isAccessibleBy($user)) {
            return response()->json([
                'error' => 'You do not have permission to view this span.'
            ], 403);
        }

        // Check if the span is a person
        if ($span->type_id !== 'person') {
            return response()->json([
                'error' => 'Only person spans have family graphs.'
            ], 400);
        }

        // Cache the family graph for 1 hour
        $cacheKey = "family_graph_{$span->id}_" . ($user ? $user->id : 'guest');
        return Cache::remember($cacheKey, 3600, function() use ($span, $user) {
            return $this->generateFamilyGraphData($span, $user);
        });
    }

    /**
     * Generate family graph data
     */
    private function generateFamilyGraphData(Span $span, $user)
    {
        // Get all family relationships
        $ancestors = $span->ancestors(3);
        $descendants = $span->descendants(2);
        $siblings = $span->siblings();
        $unclesAndAunts = $span->unclesAndAunts();
        $cousins = $span->cousins();
        $nephewsAndNieces = $span->nephewsAndNieces();
        $extraNephewsAndNieces = $span->extraNephewsAndNieces();

        // Collect all unique family members
        $nodes = collect();
        $links = collect();
        $nodeIds = collect();

        // Add the current person as a node
        // Use "You" only if this is the user's personal span, otherwise use their name
        $isPersonalSpan = $user && $user->personalSpan && $user->personalSpan->id === $span->id;
        $centerLabel = $isPersonalSpan ? 'You' : $span->name;
        
        $nodes->push([
            'id' => $span->id,
            'name' => $span->name,
            'isCurrent' => true,
            'generation' => 0,
            'dates' => $this->formatDates($span),
            'relationshipLabel' => $centerLabel
        ]);
        $nodeIds->push($span->id);

        // Helper function to add a node
        $addNode = function($member, $generation, $relationshipLabel = null) use (&$nodes, &$nodeIds, $user) {
            if (!$nodeIds->contains($member->id)) {
                // Check if user can access this member
                $accessible = $member->isAccessibleBy($user);
                
                $nodes->push([
                    'id' => $member->id,
                    'name' => $accessible ? $member->name : 'Private Person',
                    'isCurrent' => false,
                    'generation' => $generation,
                    'dates' => $accessible ? $this->formatDates($member) : null,
                    'relationshipLabel' => $relationshipLabel
                ]);
                $nodeIds->push($member->id);
            }
        };

        // Helper function to add a link
        $addLink = function($sourceId, $targetId, $type) use (&$links) {
            // Avoid duplicate links
            $exists = $links->contains(function($link) use ($sourceId, $targetId, $type) {
                return ($link['source'] == $sourceId && $link['target'] == $targetId && $link['type'] == $type) ||
                       ($link['source'] == $targetId && $link['target'] == $sourceId && $link['type'] == $type);
            });
            
            if (!$exists) {
                $links->push([
                    'source' => $sourceId,
                    'target' => $targetId,
                    'type' => $type
                ]);
            }
        };

        // Add ancestors and build a map for connecting generations
        $ancestorMap = [];
        foreach ($ancestors as $ancestor) {
            $member = $ancestor['span'];
            $generation = $ancestor['generation'];
            $label = $generation === 1 ? 'Parent' : ($generation === 2 ? 'Grandparent' : 'Great-Grandparent');
            $addNode($member, $generation, $label);
            
            $ancestorMap[$member->id] = [
                'span' => $member,
                'generation' => $generation
            ];
        }

        // Get parents directly and connect them to current person
        $parents = $span->parents()->get();
        foreach ($parents as $parent) {
            if ($nodeIds->contains($parent->id)) {
                $addLink($parent->id, $span->id, 'parent');
                
                // Connect parents to their parents (grandparents)
                $grandparents = $parent->parents()->get();
                foreach ($grandparents as $grandparent) {
                    if ($nodeIds->contains($grandparent->id)) {
                        $addLink($grandparent->id, $parent->id, 'parent');
                        
                        // Connect grandparents to their parents (great-grandparents)
                        $greatGrandparents = $grandparent->parents()->get();
                        foreach ($greatGrandparents as $greatGrandparent) {
                            if ($nodeIds->contains($greatGrandparent->id)) {
                                $addLink($greatGrandparent->id, $grandparent->id, 'parent');
                            }
                        }
                    }
                }
            }
        }

        // Add siblings
        foreach ($siblings as $sibling) {
            $addNode($sibling, 0, 'Sibling');
            // Note: No sibling-to-sibling link - relationship is shown through shared parents
            
            // Connect siblings to their actual parents (not assuming they share all parents)
            $siblingParents = $sibling->parents()->get();
            foreach ($siblingParents as $siblingParent) {
                // Add the parent if not already in the graph (e.g., step-parent)
                if (!$nodeIds->contains($siblingParent->id)) {
                    $addNode($siblingParent, 1, 'Parent (of sibling)');
                }
                $addLink($siblingParent->id, $sibling->id, 'parent');
            }
        }

        // Add uncles and aunts (and connect them to grandparents)
        foreach ($unclesAndAunts as $member) {
            $addNode($member, 1, 'Uncle/Aunt');
            
            // Connect to grandparents (their parents)
            $uncleAuntParents = $member->parents()->get();
            foreach ($uncleAuntParents as $grandparent) {
                // Add grandparent if not in graph
                if (!$nodeIds->contains($grandparent->id)) {
                    $addNode($grandparent, 2, 'Grandparent');
                }
                $addLink($grandparent->id, $member->id, 'parent');
            }
        }

        // Add cousins (and connect them to uncles/aunts)
        foreach ($cousins as $cousin) {
            $addNode($cousin, 0, 'Cousin');
            
            // Connect to their parents (uncles/aunts and their spouses)
            $cousinParents = $cousin->parents()->get();
            foreach ($cousinParents as $cousinParent) {
                // Add parent if not in graph (uncle/aunt or their spouse)
                if (!$nodeIds->contains($cousinParent->id)) {
                    $isUncleAunt = $unclesAndAunts->contains('id', $cousinParent->id);
                    $label = $isUncleAunt ? 'Uncle/Aunt' : 'Spouse of uncle/aunt';
                    $addNode($cousinParent, 1, $label);
                }
                $addLink($cousinParent->id, $cousin->id, 'parent');
            }
        }

        // Add children
        $children = $descendants->filter(function($item) { return $item['generation'] === 1; })->pluck('span');
        foreach ($children as $child) {
            $addNode($child, -1, 'Child');
            $addLink($span->id, $child->id, 'parent');
            
            // Add the child's other parent (spouse/ex-spouse) if not already in graph
            $childParents = $child->parents()->get();
            foreach ($childParents as $childParent) {
                if ($childParent->id !== $span->id) {
                    // This is the other parent
                    if (!$nodeIds->contains($childParent->id)) {
                        $addNode($childParent, 0, 'Parent of child');
                    }
                    $addLink($childParent->id, $child->id, 'parent');
                    
                    // Create spouse link if not already present
                    $addLink($span->id, $childParent->id, 'spouse');
                }
            }
        }

        // Add nephews and nieces (and connect them to siblings)
        foreach ($nephewsAndNieces as $member) {
            $addNode($member, -1, 'Nephew/Niece');
            
            // Connect to their parents (siblings and siblings' spouses)
            $nephewNieceParents = $member->parents()->get();
            foreach ($nephewNieceParents as $parent) {
                // Add parent if not in graph (e.g., sibling's spouse)
                if (!$nodeIds->contains($parent->id)) {
                    // Determine if this parent is a sibling or spouse of sibling
                    $isSibling = $siblings->contains('id', $parent->id);
                    $label = $isSibling ? 'Sibling' : 'Spouse of sibling';
                    $generation = $isSibling ? 0 : 0;
                    $addNode($parent, $generation, $label);
                }
                $addLink($parent->id, $member->id, 'parent');
            }
        }

        // Add extra nephews and nieces
        foreach ($extraNephewsAndNieces as $member) {
            $addNode($member, -1, 'Nephew/Niece');
            
            // Connect to their parents
            $nephewNieceParents = $member->parents()->get();
            foreach ($nephewNieceParents as $parent) {
                // Add parent if not in graph
                if (!$nodeIds->contains($parent->id)) {
                    $addNode($parent, 0, 'Parent of nephew/niece');
                }
                $addLink($parent->id, $member->id, 'parent');
            }
        }

        // Add grandchildren
        $grandchildren = $descendants->filter(function($item) { return $item['generation'] === 2; })->pluck('span');
        foreach ($grandchildren as $grandchild) {
            $addNode($grandchild, -2, 'Grandchild');
            
            // Connect to their parents (children and their spouses)
            $grandchildParents = $grandchild->parents()->get();
            foreach ($grandchildParents as $parent) {
                // Add parent if not in graph (child's spouse)
                if (!$nodeIds->contains($parent->id)) {
                    $isChild = $children->contains('id', $parent->id);
                    $label = $isChild ? 'Child' : 'Spouse of child';
                    $generation = $isChild ? -1 : -1;
                    $addNode($parent, $generation, $label);
                }
                $addLink($parent->id, $grandchild->id, 'parent');
            }
        }

        // Get spouse connections from the database
        $spouseConnections = Connection::where(function($query) use ($span) {
            $query->where('parent_id', $span->id)
                  ->where('type_id', 'married');
        })->orWhere(function($query) use ($span) {
            $query->where('child_id', $span->id)
                  ->where('type_id', 'married');
        })->with(['parent', 'child'])->get();

        foreach ($spouseConnections as $connection) {
            $spouse = $connection->parent_id === $span->id ? $connection->child : $connection->parent;
            if ($spouse && $spouse->isAccessibleBy($user)) {
                $addNode($spouse, 0, 'Spouse');
                $addLink($span->id, $spouse->id, 'spouse');
            }
        }

        return response()->json([
            'nodes' => $nodes->values(),
            'links' => $links->values()
        ]);
    }

    /**
     * Format dates for display
     */
    private function formatDates(Span $span)
    {
        if (!$span->start_year && !$span->end_year) {
            return null;
        }

        $parts = [];
        
        if ($span->start_year) {
            $parts[] = $span->start_year;
        }
        
        if ($span->end_year) {
            $parts[] = $span->end_year;
        }

        return implode(' – ', $parts);
    }
} 
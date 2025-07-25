<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Get existing results with type information (including placeholders)
        $existingResults = $spans->with('type')
            ->limit(5)
            ->get()
            ->map(function ($span) {
                return [
                    'id' => $span->id,
                    'name' => $span->name,
                    'type_id' => $span->type_id,
                    'type_name' => $span->type->name,
                    'state' => $span->state,
                    'is_placeholder' => $span->state === 'placeholder'
                ];
            });

        $results = $results->merge($existingResults);

        // Add placeholder suggestions if we have a query and types and no exact match exists
        if ($query && ($type || $types)) {
            $placeholderTypes = $types ? explode(',', $types) : [$type];
            
            foreach ($placeholderTypes as $placeholderType) {
                // Check if we already have an exact match for this type (including existing placeholders)
                $hasExactMatch = $existingResults->contains(function ($span) use ($query, $placeholderType) {
                    return strtolower($span['name']) === strtolower($query) && $span['type_id'] === $placeholderType;
                });
                
                if (!$hasExactMatch) {
                    $placeholderData = [
                        'id' => null,
                        'name' => $query,
                        'type_id' => $placeholderType,
                        'type_name' => ucfirst($placeholderType),
                        'state' => 'placeholder',
                        'is_placeholder' => true
                    ];
                    
                    // Add subtype metadata if specified
                    if ($subtype) {
                        $placeholderData['metadata'] = ['subtype' => $subtype];
                    }
                    
                    $results->push($placeholderData);
                }
            }
        }

        return response()->json([
            'spans' => $results->take(5)->values()
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
        
        return Cache::remember($cacheKey, 300, function () use ($span) {
            // Optimized query with eager loading and joins - with access control
            $connections = $span->connectionsAsSubjectWithAccess()
                ->with([
                    'child:id,name,type_id,start_year,end_year,metadata',
                    'connectionSpan:id,start_year,start_month,start_day,end_year,end_month,end_day',
                    'type:type,forward_predicate'
                ])
                ->whereHas('connectionSpan', function ($query) {
                    $query->whereNotNull('start_year');
                })
                ->get()
                ->map(function ($connection) {
                    $connectionSpan = $connection->connectionSpan;
                    $connectionData = [
                        'id' => $connection->id,
                        'type_id' => $connection->type_id,
                        'type_name' => $connection->type->forward_predicate ?? $connection->type_id,
                        'target_name' => $connection->child->name,
                        'target_id' => $connection->child->id,
                        'target_type' => $connection->child->type_id,
                        'target_metadata' => $connection->child->metadata ?? [],
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
        
        return Cache::remember($cacheKey, 300, function () use ($span) {
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
        
        return Cache::remember($cacheKey, 300, function () use ($span) {
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
    }
} 
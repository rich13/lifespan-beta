<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $span->save();

        return response()->json([
            'id' => $span->id,
            'name' => $span->name,
            'type_id' => $span->type_id,
            'state' => $span->state,
            'metadata' => $span->metadata
        ]);
    }
} 
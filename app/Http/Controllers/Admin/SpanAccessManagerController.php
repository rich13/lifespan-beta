<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpanAccessManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Show the centralized span access management page
     */
    public function index(Request $request)
    {
        // Get all users and groups
        $users = User::all();
        $groups = Group::with('users')->get();
        
        // Base query for all spans (including personal spans, but excluding connection spans)
        $baseQuery = Span::with(['owner', 'spanPermissions'])
            // ->where('is_personal_span', false) // Allow personal spans
            ->where('type_id', '!=', 'connection') // Exclude connection spans
            ->whereNull('parent_id'); // Only top-level spans
        
        // Apply type filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            $baseQuery->whereIn('type_id', $types);

            // Apply person subtype filters (for person type)
            if (in_array('person', $types) && $request->filled('person_subtype')) {
                $personSubtypes = explode(',', $request->input('person_subtype'));
                $baseQuery->where(function($q) use ($personSubtypes) {
                    foreach ($personSubtypes as $subtype) {
                        if ($subtype === 'musicians') {
                            // Handle musicians filter (this would need to be implemented based on your musician detection logic)
                            // For now, we'll skip this as it requires additional logic
                        } else {
                            // Handle person subtypes (public_figure, private_individual)
                            $q->orWhere(function($subQ) use ($subtype) {
                                $subQ->where('type_id', 'person')
                                     ->whereRaw("metadata->>'subtype' = ?", [$subtype]);
                            });
                        }
                    }
                });
            }

            // Apply subtype filters for other types
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $subtypes = explode(',', $request->input($type . '_subtype'));
                    $baseQuery->where(function($q) use ($type, $subtypes) {
                        $q->where('type_id', $type)
                          ->whereIn(DB::raw("metadata->>'subtype'"), $subtypes);
                    });
                }
            }
        }
        
        // Apply visibility filter
        if ($request->filled('visibility')) {
            switch ($request->visibility) {
                case 'public':
                    $baseQuery->where('access_level', 'public');
                    break;
                case 'private':
                    $baseQuery->where('access_level', 'private');
                    break;
                case 'shared':
                    $baseQuery->where('access_level', 'shared');
                    break;
            }
        }
        
        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $baseQuery->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }
        
        // Clone queries for different access levels
        $allQuery = clone $baseQuery;
        $publicQuery = clone $baseQuery;
        $privateQuery = clone $baseQuery;
        $sharedQuery = clone $baseQuery;
        
        // Apply access level filters
        $publicQuery->where('access_level', 'public');
        $privateQuery->where('access_level', 'private');
        $sharedQuery->where('access_level', 'shared');
        
        // Get the results with pagination
        $allSpans = $allQuery->orderBy('name')->paginate(500, ['*'], 'all_page');
        $publicSpans = $publicQuery->orderBy('name')->paginate(500, ['*'], 'public_page');
        $privateSpans = $privateQuery->orderBy('name')->paginate(500, ['*'], 'private_page');
        $sharedSpans = $sharedQuery->orderBy('name')->paginate(500, ['*'], 'shared_page');
        
        // Add connection counts to each span
        $allSpans->getCollection()->each(function ($span) {
            $span->connection_counts = $span->getConnectionCountsByAccessLevel();
        });
        $publicSpans->getCollection()->each(function ($span) {
            $span->connection_counts = $span->getConnectionCountsByAccessLevel();
        });
        $privateSpans->getCollection()->each(function ($span) {
            $span->connection_counts = $span->getConnectionCountsByAccessLevel();
        });
        $sharedSpans->getCollection()->each(function ($span) {
            $span->connection_counts = $span->getConnectionCountsByAccessLevel();
        });
        
        // Calculate statistics using fresh queries to avoid filter interference
        $statsQuery = Span::with(['owner', 'spanPermissions'])
            // ->where('is_personal_span', false) // Allow personal spans
            ->where('type_id', '!=', 'connection') // Exclude connection spans
            ->whereNull('parent_id'); // Only top-level spans
        
        $stats = [
            'total' => $statsQuery->count(),
            'public' => (clone $statsQuery)->where('access_level', 'public')->count(),
            'private' => (clone $statsQuery)->where('access_level', 'private')->count(),
            'shared' => (clone $statsQuery)->where('access_level', 'shared')->count(),
        ];
        
        // Get all span types for the filter dropdown (excluding connection type)
        $spanTypes = DB::table('span_types')
            ->where('type_id', '!=', 'connection')
            ->get();
        
        return view('admin.span-access.index', compact(
            'allSpans',
            'publicSpans', 
            'privateSpans',
            'sharedSpans',
            'users', 
            'groups',
            'spanTypes',
            'stats'
        ));
    }

    /**
     * Make a span public
     */
    public function makePublic(Request $request, $spanId)
    {
        $span = Span::findOrFail($spanId);
        
        $span->access_level = 'public';
        $span->save();

        // Remove all existing permissions when making public
        $span->spanPermissions()->delete();
        
        // Clear all timeline caches since access level has changed
        $span->clearAllTimelineCaches();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "Span '{$span->name}' has been made public.");
    }

    /**
     * Make a span private
     */
    public function makePrivate(Request $request, $spanId)
    {
        $span = Span::findOrFail($spanId);
        
        $span->access_level = 'private';
        $span->save();

        // Remove all existing permissions when making private
        $span->spanPermissions()->delete();
        
        // Clear all timeline caches since access level has changed
        $span->clearAllTimelineCaches();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "Span '{$span->name}' has been made private.");
    }

    /**
     * Make all spans of a specific type public
     */
    public function makeTypePublic(Request $request, $typeId)
    {
        // Count spans before update
        $count = Span::where('type_id', $typeId)
            ->where('is_personal_span', false)
            ->whereIn('access_level', ['private', 'shared'])
            ->count();
        
        // Update all spans of the specified type to be public
        Span::where('type_id', $typeId)
            ->where('is_personal_span', false)
            ->whereIn('access_level', ['private', 'shared'])
            ->update(['access_level' => 'public']);
        
        // Remove all permissions for these spans and clear caches
        Span::where('type_id', $typeId)
            ->where('is_personal_span', false)
            ->whereIn('access_level', ['private', 'shared'])
            ->get()
            ->each(function($span) {
                $span->spanPermissions()->delete();
                $span->clearAllTimelineCaches();
            });
        
        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "{$count} spans of type '{$typeId}' have been made public.");
    }

    /**
     * Make multiple spans public
     */
    public function makePublicBulk(Request $request)
    {
        $validated = $request->validate([
            'span_ids' => 'required|string'
        ]);

        $spanIds = explode(',', $validated['span_ids']);
        
        // Update all selected spans to public
        $count = Span::whereIn('id', $spanIds)
            ->where('is_personal_span', false)
            ->whereIn('access_level', ['private', 'shared'])
            ->update(['access_level' => 'public']);

        // Remove all permissions for these spans and clear caches
        $spansToUpdate = Span::whereIn('id', $spanIds)
            ->where('is_personal_span', false)
            ->where('state', '!=', 'placeholder')
            ->whereIn('access_level', ['private', 'shared'])
            ->get();
        
        // Batch process cache clearing for better performance
        if ($spansToUpdate->count() > 50) {
            // For large batches, use bulk cache clearing
            $spanIdsToClear = $spansToUpdate->pluck('id')->toArray();
            $this->bulkClearTimelineCaches($spanIdsToClear);
        } else {
            // For smaller batches, use individual cache clearing
            $spansToUpdate->each(function($span) {
                $span->spanPermissions()->delete();
                $span->clearAllTimelineCaches();
            });
        }

        // Get count of skipped placeholders
        $placeholderCount = Span::whereIn('id', $spanIds)
            ->where('state', 'placeholder')
            ->count();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "{$count} spans have been made public." . 
                ($placeholderCount > 0 ? " {$placeholderCount} placeholder spans were skipped." : ""));
    }

    /**
     * Make multiple spans private
     */
    public function makePrivateBulk(Request $request)
    {
        $validated = $request->validate([
            'span_ids' => 'required|string'
        ]);

        $spanIds = explode(',', $validated['span_ids']);
        
        // Update all selected spans to private
        $count = Span::whereIn('id', $spanIds)
            ->where('is_personal_span', false)
            ->where('state', '!=', 'placeholder')
            ->update(['access_level' => 'private']);

        // Remove all permissions for these spans and clear caches
        $spansToUpdate = Span::whereIn('id', $spanIds)
            ->where('is_personal_span', false)
            ->where('state', '!=', 'placeholder')
            ->get();
        
        // Batch process cache clearing for better performance
        if ($spansToUpdate->count() > 50) {
            // For large batches, use bulk cache clearing
            $spanIdsToClear = $spansToUpdate->pluck('id')->toArray();
            $this->bulkClearTimelineCaches($spanIdsToClear);
        } else {
            // For smaller batches, use individual cache clearing
            $spansToUpdate->each(function($span) {
                $span->spanPermissions()->delete();
                $span->clearAllTimelineCaches();
            });
        }

        // Get count of skipped placeholders
        $placeholderCount = Span::whereIn('id', $spanIds)
            ->where('state', 'placeholder')
            ->count();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "{$count} spans have been made private." . 
                ($placeholderCount > 0 ? " {$placeholderCount} placeholder spans were skipped." : ""));
    }

    /**
     * Share multiple spans with groups
     */
    public function shareWithGroupsBulk(Request $request)
    {
        $validated = $request->validate([
            'span_ids' => 'required|string',
            'group_ids' => 'required|string',
        ]);

        $spanIds = explode(',', $validated['span_ids']);
        $groupIds = explode(',', $validated['group_ids']);

        // Update spans to shared access level (including placeholders)
        $count = Span::whereIn('id', $spanIds)
            // ->where('is_personal_span', false) // Allow personal spans
            ->update(['access_level' => 'shared']);

        // Grant permissions to groups based on span type
        $permissionsCreated = 0;
        $spans = Span::whereIn('id', $spanIds)->get();
        
        foreach ($spans as $span) {
            // Determine permission type based on span type
            $permissionType = $span->type_id === 'person' ? 'view' : 'edit';
            
            foreach ($groupIds as $groupId) {
                \App\Models\SpanPermission::updateOrCreate(
                    [
                        'span_id' => $span->id,
                        'group_id' => $groupId,
                    ],
                    [
                        'permission_type' => $permissionType,
                        'user_id' => null, // Ensure user_id is null for group permissions
                    ]
                );
                $permissionsCreated++;
            }
        }

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'search', 'all_page', 'private_page', 'public_page', 'shared_page']);
        
        // Also preserve person subtype filters
        if ($request->filled('person_subtype')) {
            $queryParams['person_subtype'] = $request->input('person_subtype');
        }
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($type !== 'person' && $request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "{$count} spans have been shared with " . count($groupIds) . " group(s). Personal spans: view only, non-personal spans: full edit access.");
    }

    /**
     * Bulk clear timeline caches for multiple spans
     * This is more efficient than clearing caches individually for large batches
     */
    private function bulkClearTimelineCaches(array $spanIds): void
    {
        // For large batches, use a more efficient cache clearing strategy
        // Instead of clearing individual caches, we can clear broader patterns
        
        // Clear all timeline caches at once if using Redis or file cache
        if (config('cache.default') === 'redis' || config('cache.default') === 'file') {
            \Cache::flush();
            return;
        }
        
        // For database cache, clear in batches
        $chunks = array_chunk($spanIds, 100);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $spanId) {
                // Clear guest caches
                \Cache::forget("timeline_{$spanId}_guest");
                \Cache::forget("timeline_object_{$spanId}_guest");
                \Cache::forget("timeline_during_{$spanId}_guest");
                
                // Clear caches for all users (1-1000)
                for ($userId = 1; $userId <= 1000; $userId++) {
                    \Cache::forget("timeline_{$spanId}_{$userId}");
                    \Cache::forget("timeline_object_{$spanId}_{$userId}");
                    \Cache::forget("timeline_during_{$spanId}_{$userId}");
                }
            }
        }
    }
} 
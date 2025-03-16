<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
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
        // Get all users
        $users = User::all();
        
        // Base query for all spans (excluding personal spans and connection spans)
        $baseQuery = Span::with('owner')
            ->where('is_personal_span', false) // Exclude personal spans
            ->where('type_id', '!=', 'connection') // Exclude connection spans
            ->whereNull('parent_id') // Only top-level spans
            ->where('state', '!=', 'placeholder'); // Exclude placeholders from access management
        
        // Apply type filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            $baseQuery->whereIn('type_id', $types);

            // Apply subtype filters if any
            foreach ($types as $type) {
                if ($request->filled($type . '_subtype')) {
                    $subtypes = explode(',', $request->input($type . '_subtype'));
                    $baseQuery->orWhere(function($q) use ($type, $subtypes) {
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
                case 'group':
                    $baseQuery->where('access_level', 'shared');
                    break;
            }
        }
        
        // Clone the query for public spans
        $publicQuery = clone $baseQuery;
        $publicQuery->where('access_level', 'public');
        
        // Clone the query for private/shared spans
        $privateSharedQuery = clone $baseQuery;
        $privateSharedQuery->whereIn('access_level', ['private', 'shared']);
        
        // Get the results with pagination
        $publicSpans = $publicQuery->orderBy('name')->paginate(50, ['*'], 'public_page');
        $privateSharedSpans = $privateSharedQuery->orderBy('name')->paginate(50, ['*'], 'private_page');
        
        // Get all span types for the filter dropdown (excluding connection type)
        $spanTypes = DB::table('span_types')
            ->where('type_id', '!=', 'connection')
            ->get();
        
        return view('admin.span-access.index', compact(
            'publicSpans', 
            'privateSharedSpans', 
            'users', 
            'spanTypes'
        ));
    }

    /**
     * Make a span public
     */
    public function makePublic(Request $request, $spanId)
    {
        $span = Span::findOrFail($spanId);
        
        // Don't allow making placeholders public
        if ($span->state === 'placeholder') {
            return redirect()->route('admin.span-access.index')
                ->with('error', "Cannot make placeholder span '{$span->name}' public. Complete the span details first.");
        }

        $span->access_level = 'public';
        $span->save();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'private_page', 'public_page']);
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "Span '{$span->name}' has been made public.");
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
        
        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'private_page', 'public_page']);
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($request->filled($type . '_subtype')) {
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
        
        // Update all selected spans to public, excluding placeholders
        $count = Span::whereIn('id', $spanIds)
            ->where('is_personal_span', false)
            ->where('state', '!=', 'placeholder')
            ->whereIn('access_level', ['private', 'shared'])
            ->update(['access_level' => 'public']);

        // Get count of skipped placeholders
        $placeholderCount = Span::whereIn('id', $spanIds)
            ->where('state', 'placeholder')
            ->count();

        // Preserve all query parameters when redirecting
        $queryParams = $request->only(['types', 'visibility', 'private_page', 'public_page']);
        
        // Also preserve any subtype filters
        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            foreach ($types as $type) {
                if ($request->filled($type . '_subtype')) {
                    $queryParams[$type . '_subtype'] = $request->input($type . '_subtype');
                }
            }
        }

        return redirect()->route('admin.span-access.index', $queryParams)
            ->with('status', "{$count} spans have been made public." . 
                ($placeholderCount > 0 ? " {$placeholderCount} placeholder spans were skipped." : ""));
    }
} 
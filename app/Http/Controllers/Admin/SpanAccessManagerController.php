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
        
        // Get query parameters for filtering
        $userId = $request->input('user_id');
        $spanType = $request->input('span_type');
        
        // Base query for all spans (excluding personal spans and connection spans)
        $baseQuery = Span::with('owner')
            ->where('is_personal_span', false) // Exclude personal spans
            ->where('type_id', '!=', 'connection') // Exclude connection spans
            ->whereNull('parent_id'); // Only top-level spans
        
        // Apply common filters if provided
        if ($userId) {
            $baseQuery->where(function($q) use ($userId) {
                $q->where('owner_id', $userId)
                  ->orWhereHas('users', function($query) use ($userId) {
                      $query->where('users.id', $userId);
                  });
            });
        }
        
        if ($spanType) {
            $baseQuery->where('type_id', $spanType);
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
            'spanTypes', 
            'userId', 
            'spanType'
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

        return redirect()->route('admin.span-access.index')
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
        
        return redirect()->route('admin.span-access.index')
            ->with('status', "{$count} spans of type '{$typeId}' have been made public.");
    }
} 
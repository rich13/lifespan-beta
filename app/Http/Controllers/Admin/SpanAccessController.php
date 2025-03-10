<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpanAccessController extends Controller
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
        $accessLevel = $request->input('access_level');
        $spanType = $request->input('span_type');
        
        // Base query for user_spans with joins
        $query = DB::table('user_spans')
            ->join('spans', 'user_spans.span_id', '=', 'spans.id')
            ->join('users', 'user_spans.user_id', '=', 'users.id')
            ->join('span_types', 'spans.type_id', '=', 'span_types.type_id')
            ->select(
                'user_spans.id',
                'user_spans.user_id',
                'user_spans.span_id',
                'user_spans.access_level',
                'spans.name as span_name',
                'spans.slug as span_slug',
                'users.name as user_name',
                'users.email as user_email',
                'span_types.name as span_type'
            );
        
        // Apply filters if provided
        if ($userId) {
            $query->where('user_spans.user_id', $userId);
        }
        
        if ($accessLevel) {
            $query->where('user_spans.access_level', $accessLevel);
        }
        
        if ($spanType) {
            $query->where('spans.type_id', $spanType);
        }
        
        // Get the results with pagination
        $accessEntries = $query->orderBy('spans.name')->paginate(20);
        
        // Get all span types for the filter dropdown
        $spanTypes = DB::table('span_types')->get();
        
        return view('admin.span-access.index', compact('accessEntries', 'users', 'spanTypes', 'userId', 'accessLevel', 'spanType'));
    }

    /**
     * Show the access management page for a span
     */
    public function edit(Span $span)
    {
        $users = User::all();
        $currentAccess = DB::table('user_spans')
            ->where('span_id', $span->id)
            ->get();

        return view('admin.spans.access', compact('span', 'users', 'currentAccess'));
    }

    /**
     * Update access for a span
     */
    public function update(Request $request, Span $span)
    {
        $validated = $request->validate([
            'access' => 'required|array',
            'access.*.user_id' => 'required|exists:users,id',
            'access.*.level' => 'required|in:viewer,editor,owner',
        ]);

        // Start a transaction
        DB::beginTransaction();

        try {
            // Remove all current access
            DB::table('user_spans')
                ->where('span_id', $span->id)
                ->delete();

            // Add new access
            foreach ($validated['access'] as $access) {
                DB::table('user_spans')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $access['user_id'],
                    'span_id' => $span->id,
                    'access_level' => $access['level'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('status', 'Access updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update access');
        }
    }

    /**
     * Update public/system status
     */
    public function updateVisibility(Request $request, Span $span)
    {
        $validated = $request->validate([
            'is_public' => 'boolean',
            'is_system' => 'boolean',
        ]);

        $metadata = $span->metadata ?? [];
        $metadata['is_public'] = $validated['is_public'] ?? false;
        $metadata['is_system'] = $validated['is_system'] ?? false;

        $span->metadata = $metadata;
        $span->save();

        return redirect()->back()->with('status', 'Visibility updated successfully');
    }
} 
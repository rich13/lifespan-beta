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
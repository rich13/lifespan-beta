<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SpanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $query = Span::with(['owner', 'updater', 'type'])
            ->select('spans.*')
            ->selectRaw('EXISTS(SELECT 1 FROM users WHERE users.personal_span_id = spans.id) as is_personal_span')
            ->selectRaw("metadata->>'subtype' as subtype")
            ->where('type_id', '!=', 'connection');

        // Apply type filters
        if (request()->filled('types')) {
            $types = explode(',', request('types'));
            $query->whereIn('type_id', $types);

            // Apply subtype filters if any
            foreach ($types as $type) {
                if (request($type . '_subtype')) {
                    $subtypes = explode(',', request($type . '_subtype'));
                    $query->orWhere(function($q) use ($type, $subtypes) {
                        $q->where('type_id', $type)
                          ->whereIn(DB::raw("metadata->>'subtype'"), $subtypes);
                    });
                }
            }
        }

        // Apply search filter
        if (request('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Apply permission mode filter
        if (request('permission_mode')) {
            $query->where('permission_mode', request('permission_mode'));
        }

        // Apply visibility filter
        if (request('visibility')) {
            switch (request('visibility')) {
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
        }

        // Apply state filter
        if (request('state')) {
            $query->where('state', request('state'));
        }

        $spans = $query->orderBy('created_at', 'desc')->paginate(50);
        $types = SpanType::all();
        return view('admin.spans.index', compact('spans', 'types'));
    }

    public function show(Span $span): View
    {
        return view('admin.spans.show', compact('span'));
    }

    public function edit(Span $span): View
    {
        $types = SpanType::all();
        $users = User::all();
        return view('admin.spans.edit', compact('span', 'types', 'users'));
    }

    public function update(Request $request, Span $span)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type_id' => 'sometimes|required|string|exists:span_types,type_id',
            'start_year' => 'sometimes|required|integer',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
            'metadata' => 'nullable|array',
        ]);

        $span->update($validated);
        return redirect()->route('admin.spans.show', $span)
            ->with('status', 'Span updated successfully');
    }

    public function destroy(Span $span)
    {
        // Clean up any personal_span_id references before deleting the span
        $usersWithPersonalSpan = \App\Models\User::where('personal_span_id', $span->id)->get();
        if ($usersWithPersonalSpan->count() > 0) {
            \Illuminate\Support\Facades\Log::info('Cleaning up personal_span_id references before admin span deletion', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'affected_users' => $usersWithPersonalSpan->pluck('id')->toArray()
            ]);
            
            \App\Models\User::where('personal_span_id', $span->id)
                ->update(['personal_span_id' => null]);
        }
        
        $span->delete();
        return redirect()->route('admin.spans.index')
            ->with('status', 'Span deleted successfully');
    }

    /**
     * Show the person subtype management page
     */
    public function managePersonSubtypes(): View
    {
        $people = Span::where('type_id', 'person')
            ->with(['owner', 'updater'])
            ->select('spans.*')
            ->selectRaw("metadata->>'subtype' as subtype")
            ->selectRaw('EXISTS(SELECT 1 FROM users WHERE users.personal_span_id = spans.id) as is_personal_span')
            ->orderBy('name')
            ->paginate(100);

        $subtypeCounts = Span::where('type_id', 'person')
            ->selectRaw("metadata->>'subtype' as subtype, COUNT(*) as count")
            ->groupBy(DB::raw("metadata->>'subtype'"))
            ->pluck('count', 'subtype')
            ->toArray();

        return view('admin.spans.manage-person-subtypes', compact('people', 'subtypeCounts'));
    }

    /**
     * Update person subtypes in bulk
     */
    public function updatePersonSubtypes(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.span_id' => 'required|string|exists:spans,id',
            'updates.*.subtype' => 'required|string|in:public_figure,private_individual',
        ]);

        $updated = 0;
        $errors = [];

        foreach ($validated['updates'] as $update) {
            try {
                $span = Span::find($update['span_id']);
                
                // Update the subtype in metadata
                $metadata = $span->metadata ?? [];
                $metadata['subtype'] = $update['subtype'];
                $span->metadata = $metadata;
                
                // If changing to public_figure, also set access_level to public
                if ($update['subtype'] === 'public_figure' && $span->access_level === 'private') {
                    $span->access_level = 'public';
                }
                
                $span->save();
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "Failed to update {$span->name}: " . $e->getMessage();
            }
        }

        $message = "Updated {$updated} people successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return redirect()->route('admin.spans.manage-person-subtypes')
            ->with('status', $message);
    }
} 
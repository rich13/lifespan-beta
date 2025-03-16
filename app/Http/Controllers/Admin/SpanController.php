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
        if (request('types')) {
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
        return view('admin.spans.edit', compact('span'));
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
        $span->delete();
        return redirect()->route('admin.spans.index')
            ->with('status', 'Span deleted successfully');
    }
} 
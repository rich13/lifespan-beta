<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $spans = Span::with(['owner', 'updater', 'type'])
            ->select('spans.*')
            ->selectRaw('EXISTS(SELECT 1 FROM users WHERE users.personal_span_id = spans.id) as is_personal_span')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
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
<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // Log the list request with performance tracking
        $startTime = microtime(true);

        // Get spans with pagination
        $spans = Span::orderBy('start_year')
            ->orderBy('start_month')
            ->orderBy('start_day')
            ->paginate(20);

        // Log performance metrics
        Log::channel('performance')->info('Spans list rendered', [
            'count' => $spans->count(),
            'render_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ]);

        return view('spans.index', compact('spans'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('spans.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'required|string|max:255',
            'start_year' => 'required|integer',
            'start_month' => 'nullable|integer|between:1,12',
            'start_day' => 'nullable|integer|between:1,31',
            'end_year' => 'nullable|integer',
            'end_month' => 'nullable|integer|between:1,12',
            'end_day' => 'nullable|integer|between:1,31',
        ]);

        // Add creator_id from authenticated user
        $validated['creator_id'] = auth()->id();
        $validated['updater_id'] = auth()->id();

        $span = Span::create($validated);

        return redirect()->route('spans.show', $span);
    }

    /**
     * Display the specified resource.
     */
    public function show(Span $span)
    {
        return view('spans.show', compact('span'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Span $span)
    {
        return view('spans.edit', compact('span'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Span $span)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Add other validation rules as needed
        ]);

        // Add updater_id from authenticated user
        $validated['updater_id'] = auth()->id();

        $span->update($validated);

        return redirect()->route('spans.show', $span);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Span $span)
    {
        $span->delete();

        return redirect()->route('spans.index');
    }
} 
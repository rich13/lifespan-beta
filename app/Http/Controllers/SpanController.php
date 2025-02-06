<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * Handle span viewing and management
 * This is a core controller that will grow to handle all span operations
 */
class SpanController extends Controller
{
    /**
     * List all spans
     * Shows a paginated list of spans with basic filtering
     *
     * @return View
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
     * Display a span
     * This is our "hello world" endpoint that proves the basic system works
     *
     * @param Span $span The span to display (route model binding)
     * @return View
     */
    public function show(Span $span): View
    {
        // Log the view request with performance tracking
        $startTime = microtime(true);

        // Log access attempt
        Log::channel('security')->info('Span view attempted', [
            'span_id' => $span->id,
            'user_id' => auth()->id(),
            'ip' => request()->ip()
        ]);

        // For now, just return the view with the span
        // Later we'll add:
        // - Access control
        // - Related spans
        // - Type-specific handling
        $view = view('spans.show', compact('span'));

        // Log performance metrics
        Log::channel('performance')->info('Span view rendered', [
            'span_id' => $span->id,
            'render_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ]);

        return $view;
    }
} 
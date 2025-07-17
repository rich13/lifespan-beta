<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FamilyController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $personalSpan = $user->personalSpan;
        
        if (!$personalSpan) {
            return view('family.index', [
                'span' => null,
                'message' => 'No personal span found for your account.'
            ]);
        }

        return view('family.index', [
            'span' => $personalSpan,
            'message' => null
        ]);
    }

    public function show(Span $span)
    {
        // Check if the span is a person
        if ($span->type_id !== 'person') {
            abort(404, 'Family view is only available for people.');
        }

        // Check if user has access to this span
        if (!$span->hasPermission(auth()->user(), 'view')) {
            abort(403, 'You do not have permission to view this person\'s family.');
        }

        return view('family.show', [
            'span' => $span,
            'message' => null
        ]);
    }

    /**
     * API endpoint to create family connections
     */
    public function createConnection(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'required|uuid|exists:spans,id',
            'child_id' => 'required|uuid|exists:spans,id',
            'relationship' => 'required|in:mother,father,parent'
        ]);

        $parent = Span::findOrFail($validated['parent_id']);
        $child = Span::findOrFail($validated['child_id']);

        // Check if both spans are people
        if ($parent->type_id !== 'person' || $child->type_id !== 'person') {
            return response()->json(['error' => 'Both spans must be people'], 400);
        }

        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $parent->id)
            ->where('child_id', $child->id)
            ->where('type_id', 'family')
            ->first();

        if ($existingConnection) {
            return response()->json(['error' => 'Family connection already exists'], 400);
        }

        try {
            // Create the connection span
            $connectionSpan = Span::create([
                'name' => "{$parent->name} - {$child->name} Family Connection",
                'type_id' => 'connection',
                'owner_id' => Auth::id(),
                'updater_id' => Auth::id(),
                'start_year' => $child->start_year ?? null,
                'start_month' => $child->start_month ?? null,
                'start_day' => $child->start_day ?? null,
                'access_level' => 'private',
                'state' => 'placeholder', // Set as placeholder since we don't have exact dates
                'start_precision' => 'year', // Set default precision
                'end_precision' => 'year' // Set default precision
            ]);

            // Create the family connection
            $connection = Connection::create([
                'type_id' => 'family',
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'connection_span_id' => $connectionSpan->id,
                'metadata' => [
                    'relationship_type' => $validated['relationship']
                ]
            ]);

            Log::info('Family connection created', [
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'relationship' => $validated['relationship'],
                'connection_id' => $connection->id
            ]);

            return response()->json([
                'success' => true,
                'connection' => $connection,
                'message' => 'Family connection created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create family connection', [
                'error' => $e->getMessage(),
                'parent_id' => $parent->id,
                'child_id' => $child->id
            ]);

            return response()->json(['error' => 'Failed to create family connection'], 500);
        }
    }
} 
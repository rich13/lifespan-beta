<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Temporal\TemporalService;
use App\Services\Connection\ConnectionConstraintService;

class ConnectionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly TemporalService $temporalService,
        private readonly ConnectionConstraintService $constraintService
    )
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Display a listing of connections.
     */
    public function index(Request $request)
    {
        $query = Connection::query()
            ->with(['parent', 'child', 'connectionSpan']);

        // Apply type filter
        if ($request->filled('type')) {
            $query->where('type_id', $request->type);
        }

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($query) use ($search) {
                $query->whereHas('parent', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('child', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('connectionSpan', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });
        }

        $connections = $query->paginate(20);
        $types = ConnectionType::all();

        return view('admin.connections.index', compact('connections', 'types'));
    }

    /**
     * Store a newly created connection.
     */
    public function store(Request $request)
    {
        try {
            // Log the incoming request data
            Log::info('Creating new connection', [
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'type' => 'required|exists:connection_types,type',
                'parent_id' => 'required|exists:spans,id',
                'child_id' => 'required|exists:spans,id|different:parent_id',
                'direction' => 'required|in:forward,inverse',
                'connection_year' => 'nullable|integer',
                'connection_month' => 'nullable|integer|between:1,12',
                'connection_day' => 'nullable|integer|between:1,31',
                'connection_end_year' => 'nullable|integer',
                'connection_end_month' => 'nullable|integer|between:1,12',
                'connection_end_day' => 'nullable|integer|between:1,31'
            ]);

            // Get the spans and connection type
            $parent = Span::findOrFail($validated['parent_id']);
            $child = Span::findOrFail($validated['child_id']);
            $connectionType = ConnectionType::findOrFail($validated['type']);

            // If direction is inverse, swap parent and child
            if ($validated['direction'] === 'inverse') {
                $temp = $parent;
                $parent = $child;
                $child = $temp;
            }

            // Validate span types
            if (!$connectionType->isSpanTypeAllowed($parent->type_id, 'parent')) {
                throw new \InvalidArgumentException(
                    "Invalid parent span type. Expected one of: " . 
                    implode(', ', $connectionType->getAllowedSpanTypes('parent'))
                );
            }

            if (!$connectionType->isSpanTypeAllowed($child->type_id, 'child')) {
                throw new \InvalidArgumentException(
                    "Invalid child span type. Expected one of: " . 
                    implode(', ', $connectionType->getAllowedSpanTypes('child'))
                );
            }

            // Determine connection span dates based on connection type
            $spanData = [
                'type_id' => 'connection',
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'name' => "{$parent->name} {$connectionType->getPredicate($validated['direction'] === 'inverse')} {$child->name}",
                'metadata' => []
            ];
            
            // Mark connection span as timeless if the connection type is timeless
            if ($connectionType->constraint_type === 'timeless') {
                $spanData['metadata']['timeless'] = true;
            }

            // For family connections, use child's birth date as start and earliest death date as end
            if ($connectionType->type === 'family') {
                $spanData['start_year'] = $child->start_year;
                $spanData['start_month'] = $child->start_month;
                $spanData['start_day'] = $child->start_day;

                if ($parent->end_year || $child->end_year) {
                    if ($parent->end_year && $child->end_year) {
                        if ($parent->end_year < $child->end_year) {
                            $spanData['end_year'] = $parent->end_year;
                            $spanData['end_month'] = $parent->end_month;
                            $spanData['end_day'] = $parent->end_day;
                        } else {
                            $spanData['end_year'] = $child->end_year;
                            $spanData['end_month'] = $child->end_month;
                            $spanData['end_day'] = $child->end_day;
                        }
                    } else {
                        $endSpan = $parent->end_year ? $parent : $child;
                        $spanData['end_year'] = $endSpan->end_year;
                        $spanData['end_month'] = $endSpan->end_month;
                        $spanData['end_day'] = $endSpan->end_day;
                    }
                }
            } else {
                // For other connections, use provided dates
                $spanData['start_year'] = $validated['connection_year'] ?? null;
                $spanData['start_month'] = $validated['connection_month'] ?? null;
                $spanData['start_day'] = $validated['connection_day'] ?? null;
                $spanData['end_year'] = $validated['connection_end_year'] ?? null;
                $spanData['end_month'] = $validated['connection_end_month'] ?? null;
                $spanData['end_day'] = $validated['connection_end_day'] ?? null;
            }

            // Create connection span
            $connectionSpan = Span::create($spanData);

            // Validate span dates using temporal service (only if not placeholder)
            if ($connectionSpan->state !== 'placeholder') {
                if (!$this->temporalService->validateSpanDates($connectionSpan)) {
                    $connectionSpan->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'End date cannot be before start date'
                    ], 422);
                }
            }

            // Create the connection
            $connection = new Connection([
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'type_id' => $validated['type'],
                'connection_span_id' => $connectionSpan->id
            ]);

            // Validate connection constraints
            $constraintResult = $this->constraintService->validateConstraint(
                $connection,
                $connectionType->constraint_type
            );

            if (!$constraintResult->isValid()) {
                $connectionSpan->delete();
                return response()->json([
                    'success' => false,
                    'message' => $constraintResult->getError()
                ], 422);
            }

            // Save the connection
            $connection->save();

            return response()->json([
                'success' => true,
                'message' => 'Connection created successfully',
                'data' => $connection
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating connection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified connection.
     */
    public function edit(Connection $connection)
    {
        $types = ConnectionType::all();
        $spans = Span::where('type_id', '!=', 'connection')
            ->orderBy('name')
            ->get();

        return view('admin.connections.edit', compact('connection', 'types', 'spans'));
    }

    /**
     * Update the specified connection.
     */
    public function update(Request $request, Connection $connection)
    {
        try {
            $validated = $request->validate([
                'type_id' => 'required|exists:connection_types,type',
                'parent_id' => 'required|exists:spans,id',
                'child_id' => 'required|exists:spans,id|different:parent_id',
                'connection_span_id' => 'required|exists:spans,id'
            ]);

            // Verify the connection span is of type 'connection'
            $connectionSpan = Span::findOrFail($validated['connection_span_id']);
            if ($connectionSpan->type_id !== 'connection') {
                throw new \InvalidArgumentException('connection_span_id must reference a span with type=connection');
            }

            // Log the update attempt
            Log::info('Updating connection', [
                'connection_id' => $connection->id,
                'old_values' => $connection->toArray(),
                'new_values' => $validated
            ]);

            $connection->update($validated);

            return redirect()
                ->route('admin.connections.index')
                ->with('status', 'Connection updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating connection', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while updating the connection.']);
        }
    }

    /**
     * Remove the specified connection.
     */
    public function destroy(Connection $connection)
    {
        try {
            // Log the deletion attempt
            Log::info('Deleting connection', [
                'connection_id' => $connection->id,
                'connection_data' => $connection->toArray()
            ]);

            $connection->delete();

            return redirect()
                ->route('admin.connections.index')
                ->with('status', 'Connection deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting connection', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'An error occurred while deleting the connection.']);
        }
    }
} 
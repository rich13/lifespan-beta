<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConnectionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
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
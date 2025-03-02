<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConnectionType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $types = ConnectionType::withCount('connections')
            ->orderBy('type')
            ->get();
            
        return view('admin.connection-types.index', compact('types'));
    }

    public function show(ConnectionType $connectionType): View
    {
        $connectionType->load(['connections' => function ($query) {
            $query->latest()->limit(5);
        }]);
        
        return view('admin.connection-types.show', compact('connectionType'));
    }

    public function edit(ConnectionType $connectionType): View
    {
        return view('admin.connection-types.edit', compact('connectionType'));
    }

    public function update(Request $request, ConnectionType $connectionType)
    {
        $validated = $request->validate([
            'forward_predicate' => 'required|string|max:255',
            'forward_description' => 'required|string',
            'inverse_predicate' => 'required|string|max:255',
            'inverse_description' => 'required|string',
        ]);

        $connectionType->update($validated);

        return redirect()
            ->route('admin.connection-types.show', $connectionType)
            ->with('status', 'Connection type updated successfully');
    }

    public function create(): View
    {
        return view('admin.connection-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:connection_types,type',
            'forward_predicate' => 'required|string|max:255',
            'forward_description' => 'required|string',
            'inverse_predicate' => 'required|string|max:255',
            'inverse_description' => 'required|string',
        ]);

        $connectionType = ConnectionType::create($validated);

        return redirect()
            ->route('admin.connection-types.show', $connectionType)
            ->with('status', 'Connection type created successfully');
    }

    public function destroy(ConnectionType $connectionType)
    {
        // Check if there are any connections using this type
        if ($connectionType->connections()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete connection type that is in use']);
        }

        $connectionType->delete();

        return redirect()
            ->route('admin.connection-types.index')
            ->with('status', 'Connection type deleted successfully');
    }
} 
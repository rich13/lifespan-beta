<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CollectionsController extends Controller
{
    /**
     * Display a listing of all collections (public)
     */
    public function index(Request $request)
    {
        $collections = Span::where('type_id', 'collection')
            ->where('access_level', 'public')
            ->orderBy('name')
            ->get();

        return view('collections.index', compact('collections'));
    }

    /**
     * Display the specified collection (public)
     */
    public function show(\App\Models\Span $collection)
    {
        // Ensure this is a collection
        if (!$collection->isCollection()) {
            abort(404);
        }

        // Collections are public, so anyone can view them
        $contents = $collection->getCollectionContents();
        
        // Get featured/cover photo if one is connected via "features" connection
        $coverPhoto = Connection::where('type_id', 'features')
            ->where('child_id', $collection->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with('parent')
            ->first();
        
        return view('collections.show', compact('collection', 'contents', 'coverPhoto'));
    }

    /**
     * Store a newly created collection (admin only)
     */
    public function store(Request $request)
    {
        // Check if user is admin
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, 'Only administrators can create collections.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $collection = Span::create([
            'name' => $validated['name'],
            'type_id' => 'collection',
            'description' => $validated['description'] ?? null,
            'owner_id' => Auth::id(),
            'updater_id' => Auth::id(),
            'state' => 'complete',
            'access_level' => 'public'
        ]);

        return redirect()->route('collections.show', $collection->slug)
            ->with('success', 'Collection created successfully.');
    }

    /**
     * Add an item to a collection (admin only)
     */
    public function addItem(Request $request, Span $collection)
    {
        // Check if user is admin
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, 'Only administrators can modify collections.');
        }

        // Ensure this is a collection
        if (!$collection->isCollection()) {
            abort(403);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:spans,id',
        ]);

        $item = Span::findOrFail($validated['item_id']);

        if ($collection->addToCollection($item)) {
            return response()->json([
                'success' => true,
                'message' => 'Item added to collection successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Item is already in this collection.'
            ], 422);
        }
    }

    /**
     * Remove an item from a collection (admin only)
     */
    public function removeItem(Request $request, Span $collection)
    {
        // Check if user is admin
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, 'Only administrators can modify collections.');
        }

        // Ensure this is a collection
        if (!$collection->isCollection()) {
            abort(403);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:spans,id',
        ]);

        $item = Span::findOrFail($validated['item_id']);

        if ($collection->removeFromCollection($item)) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from collection successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Item was not in this collection.'
            ], 422);
        }
    }

    /**
     * Get collections containing a specific item (public)
     */
    public function getContainingCollections(Span $item)
    {
        $collections = $item->getContainingCollections();

        return response()->json([
            'success' => true,
            'collections' => $collections->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'name' => $collection->name,
                    'description' => $collection->description,
                    'slug' => $collection->slug
                ];
            })
        ]);
    }

    /**
     * Add or remove an item from a collection (admin only)
     */
    public function toggleItem(Request $request, Span $collection)
    {
        // Check if user is admin
        if (!Auth::check() || !Auth::user()->is_admin) {
            abort(403, 'Only administrators can modify collections.');
        }

        // Ensure this is a collection
        if (!$collection->isCollection()) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:add,remove',
            'model_id' => 'required',
            'model_class' => 'required|in:App\Models\Span,App\Models\Connection'
        ]);

        $action = $validated['action'];
        $modelId = $validated['model_id'];
        $modelClass = $validated['model_class'];

        // Get the model instance
        $model = null;
        if ($modelClass === 'App\\Models\\Span' || $modelClass === 'App\Models\Span') {
            $model = Span::find($modelId);
        } elseif ($modelClass === 'App\\Models\\Connection' || $modelClass === 'App\Models\Connection') {
            $model = Connection::find($modelId);
        }

        if (!$model) {
            abort(404);
        }

        $success = false;
        $message = '';

        if ($action === 'add') {
            if ($model instanceof Span) {
                $success = $collection->addToCollection($model);
                $message = $success ? 'Item added to collection successfully.' : 'Item is already in this collection.';
            } elseif ($model instanceof Connection && $model->connectionSpan) {
                $success = $collection->addToCollection($model->connectionSpan);
                $message = $success ? 'Item added to collection successfully.' : 'Item is already in this collection.';
            }
        } else {
            if ($model instanceof Span) {
                $success = $collection->removeFromCollection($model);
                $message = $success ? 'Item removed from collection successfully.' : 'Item was not in this collection.';
            } elseif ($model instanceof Connection && $model->connectionSpan) {
                $success = $collection->removeFromCollection($model->connectionSpan);
                $message = $success ? 'Item removed from collection successfully.' : 'Item was not in this collection.';
            }
        }

        return response()->json([
            'success' => $success,
            'message' => $message
        ]);
    }
}


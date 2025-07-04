<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetsController extends Controller
{
    /**
     * Display a listing of the user's sets
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Require authentication to view sets
        if (!$user) {
            abort(403, 'You must be logged in to view sets.');
        }
        
        $allSets = collect();
        
        // Ensure default sets exist for the user
        $starredSet = Span::getOrCreateStarredSet($user);
        $desertIslandDiscsSet = Span::getOrCreateDesertIslandDiscsSet($user);
        
        // Add smart sets (predefined sets that belong to the user)
        $smartSets = Span::getPredefinedSets($user);
        $allSets = $allSets->merge($smartSets);
        
        // Add default sets (Starred, Desert Island Discs) - these belong to the user
        $defaultSets = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->get();
        $allSets = $allSets->merge($defaultSets);
        
        // Add user-created sets (excluding default sets) - these belong to the user
        $userSets = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->where('is_predefined', false)
            ->whereNotIn('id', $defaultSets->pluck('id')) // Exclude default sets
            ->orderBy('name')
            ->get();
        $allSets = $allSets->merge($userSets);
        
        // Sort all sets by name
        $allSets = $allSets->sortBy('name');

        return view('sets.index', compact('allSets'));
    }

    /**
     * Display the specified set
     */
    public function show(\App\Models\Span $set)
    {
        $user = Auth::user();

        // Smart set logic (only for authenticated users)
        if ($user) {
            $predefinedSets = \App\Models\Span::getPredefinedSets($user);
            $smartSet = $predefinedSets->where('slug', $set->slug)->first();
            if ($smartSet) {
                // This is a smart set - create a virtual set object for the view
                $set = (object) [
                    'id' => 'smart_' . $smartSet->slug,
                    'name' => $smartSet->name,
                    'description' => $smartSet->description,
                    'slug' => $smartSet->slug,
                    'is_predefined' => true,
                    'is_smart_set' => true,
                    'filter_type' => $smartSet->filter_type,
                    'criteria' => $smartSet->criteria,
                    'icon' => $smartSet->icon,
                    'owner_id' => $user->id, // Smart sets belong to the current user
                    'type_id' => 'set',
                    'access_level' => 'private',
                    'isSet' => function() { return true; },
                    'hasPermission' => function($user, $permission) { return true; },
                    'isEditableBy' => function($user) { return false; }, // Smart sets are not editable
                ];
                $contents = $smartSet->getSetContents();
                return view('sets.show', compact('set', 'contents'));
            }
        }

        // Ensure user can access this set
        if (!$set->isSet() || !$set->hasPermission($user, 'view')) {
            abort(403);
        }

        $contents = $set->getSetContents();
        return view('sets.show', compact('set', 'contents'));
    }

    /**
     * Store a newly created set
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $set = Span::create([
            'name' => $validated['name'],
            'type_id' => 'set',
            'description' => $validated['description'] ?? null,
            'owner_id' => Auth::id(),
            'updater_id' => Auth::id(),
            'state' => 'complete',
            'access_level' => 'private'
        ]);

        return redirect()->route('sets.show', $set->slug)
            ->with('success', 'Set created successfully.');
    }

    /**
     * Add an item to a set
     */
    public function addItem(Request $request, Span $set)
    {
        // Ensure user can modify this set
        if (!$set->isSet() || !$set->isEditableBy(Auth::user())) {
            abort(403);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:spans,id',
        ]);

        $item = Span::findOrFail($validated['item_id']);

        // Ensure user can access the item
        if (!$item->hasPermission(Auth::user(), 'view')) {
            abort(403);
        }

        if ($set->addToSet($item)) {
            return response()->json([
                'success' => true,
                'message' => 'Item added to set successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Item is already in this set.'
            ], 422);
        }
    }

    /**
     * Remove an item from a set
     */
    public function removeItem(Request $request, Span $set)
    {
        // Ensure user can modify this set
        if (!$set->isSet() || !$set->isEditableBy(Auth::user())) {
            abort(403);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:spans,id',
        ]);

        $item = Span::findOrFail($validated['item_id']);

        if ($set->removeFromSet($item)) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from set successfully.'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Item was not in this set.'
            ], 422);
        }
    }

    /**
     * Get sets containing a specific item
     */
    public function getContainingSets(Span $item)
    {
        // Ensure user can access the item
        if (!$item->hasPermission(Auth::user(), 'view')) {
            abort(403);
        }

        $sets = $item->getContainingSets()
            ->filter(function ($set) {
                return $set->hasPermission(Auth::user(), 'view');
            });

        return response()->json([
            'success' => true,
            'sets' => $sets->map(function ($set) {
                return [
                    'id' => $set->id,
                    'name' => $set->name,
                    'description' => $set->description
                ];
            })
        ]);
    }

    /**
     * Check if an item is in a specific set
     */
    public function checkMembership(Span $set, Span $item)
    {
        // Ensure user can access both the set and item
        if (!$set->hasPermission(Auth::user(), 'view') || !$item->hasPermission(Auth::user(), 'view')) {
            abort(403);
        }

        $isMember = $set->containsItem($item);

        return response()->json([
            'success' => true,
            'is_member' => $isMember
        ]);
    }

    /**
     * Get modal data for adding items to sets
     */
    public function getModalData(Request $request)
    {
        $user = Auth::user();
        $modelId = $request->get('model_id');
        $modelClass = $request->get('model_class');

        // Get the model instance
        $model = null;
        if ($modelClass === 'App\Models\Span') {
            $model = Span::find($modelId);
        } elseif ($modelClass === 'App\Models\Connection') {
            $model = Connection::find($modelId);
        }

        if (!$model) {
            abort(404);
        }

        // Check access based on model type
        if ($model instanceof Span) {
            if (!$model->hasPermission($user, 'view')) {
                abort(403);
            }
        } elseif ($model instanceof Connection) {
            if (!$model->isAccessibleBy($user)) {
                abort(403);
            }
        }

        // Get user's sets (exclude smart sets since they can't be toggled)
        $sets = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->where('is_predefined', false) // Only user-created sets
            ->orderBy('name')
            ->get();

        // Get current memberships for all possible items
        $currentMemberships = [];
        $membershipDetails = [];
        
        if ($model instanceof Span) {
            $currentMemberships = $model->getContainingSets()
                ->where('owner_id', $user->id)
                ->pluck('id')
                ->toArray();
        } elseif ($model instanceof Connection) {
            // For connections, check all possible items that could be added
            $membershipSets = collect();
            
            // Check connection span
            if ($model->connectionSpan) {
                $connectionMemberships = $model->connectionSpan->getContainingSets()->where('owner_id', $user->id);
                $membershipSets = $membershipSets->merge($connectionMemberships);
                $membershipDetails['connection_' . $model->id] = $connectionMemberships->pluck('id')->toArray();
            }
            
            // Check subject (parent)
            if ($model->parent) {
                $subjectMemberships = $model->parent->getContainingSets()->where('owner_id', $user->id);
                $membershipSets = $membershipSets->merge($subjectMemberships);
                $membershipDetails['subject_' . $model->parent->id] = $subjectMemberships->pluck('id')->toArray();
            }
            
            // Check object (child)
            if ($model->child) {
                $objectMemberships = $model->child->getContainingSets()->where('owner_id', $user->id);
                $membershipSets = $membershipSets->merge($objectMemberships);
                $membershipDetails['object_' . $model->child->id] = $objectMemberships->pluck('id')->toArray();
            }
            
            $currentMemberships = $membershipSets->pluck('id')->unique()->toArray();
        }

        // Prepare the item summary and options
        $itemSummary = [];
        $addOptions = [];
        
        if ($model instanceof Span) {
            $itemSummary = [
                'type' => 'span',
                'name' => $model->name,
                'type_name' => $model->type->name ?? 'Unknown Type'
            ];
            $addOptions = [
                [
                    'id' => 'span_' . $model->id,
                    'label' => $model->name,
                    'type' => 'span',
                    'model_id' => $model->id,
                    'model_class' => 'App\Models\Span'
                ]
            ];
        } elseif ($model instanceof Connection) {
            // Load the connection with its relationships
            $model->load(['parent', 'child', 'type', 'connectionSpan']);
            
            $itemSummary = [
                'type' => 'connection',
                'name' => $model->connectionSpan?->name ?? 'Connection',
                'type_name' => $model->type?->name ?? 'Unknown Type',
                'subject' => $model->parent?->name ?? 'Unknown',
                'object' => $model->child?->name ?? 'Unknown'
            ];
            
            $addOptions = [];
            
            // Option to add the connection span
            if ($model->connectionSpan) {
                $addOptions[] = [
                    'id' => 'connection_' . $model->id,
                    'label' => $model->connectionSpan->name,
                    'type' => 'connection',
                    'model_id' => $model->connectionSpan->id,
                    'model_class' => 'App\Models\Span'
                ];
            }
            
            // Option to add the subject (parent)
            if ($model->parent) {
                $addOptions[] = [
                    'id' => 'subject_' . $model->parent->id,
                    'label' => $model->parent->name,
                    'type' => 'subject',
                    'model_id' => $model->parent->id,
                    'model_class' => 'App\Models\Span'
                ];
            }
            
            // Option to add the object (child)
            if ($model->child) {
                $addOptions[] = [
                    'id' => 'object_' . $model->child->id,
                    'label' => $model->child->name,
                    'type' => 'object',
                    'model_id' => $model->child->id,
                    'model_class' => 'App\Models\Span'
                ];
            }
        }

        return response()->json([
            'itemSummary' => $itemSummary,
            'addOptions' => $addOptions,
            'sets' => $sets->map(function ($set) {
                return [
                    'id' => $set->id,
                    'name' => $set->name,
                    'description' => $set->description
                ];
            }),
            'currentMemberships' => $currentMemberships,
            'membershipDetails' => $membershipDetails
        ]);
    }

    /**
     * Add or remove an item from a set
     */
    public function toggleItem(Request $request, Span $set)
    {
        // Ensure user can modify this set
        if (!$set->isSet() || !$set->isEditableBy(Auth::user())) {
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
        if ($modelClass === 'App\Models\Span') {
            $model = Span::find($modelId);
        } elseif ($modelClass === 'App\Models\Connection') {
            $model = Connection::find($modelId);
        }

        if (!$model || !$model->hasPermission(Auth::user(), 'view')) {
            abort(403);
        }

        $success = false;
        $message = '';

        if ($action === 'add') {
            if ($model instanceof Span) {
                $success = $set->addToSet($model);
                $message = $success ? 'Item added to set successfully.' : 'Item is already in this set.';
            } elseif ($model instanceof Connection && $model->connectionSpan) {
                $success = $set->addToSet($model->connectionSpan);
                $message = $success ? 'Item added to set successfully.' : 'Item is already in this set.';
            }
        } else {
            if ($model instanceof Span) {
                $success = $set->removeFromSet($model);
                $message = $success ? 'Item removed from set successfully.' : 'Item was not in this set.';
            } elseif ($model instanceof Connection && $model->connectionSpan) {
                $success = $set->removeFromSet($model->connectionSpan);
                $message = $success ? 'Item removed from set successfully.' : 'Item was not in this set.';
            }
        }

        return response()->json([
            'success' => $success,
            'message' => $message
        ]);
    }
}

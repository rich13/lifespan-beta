<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * Display a listing of all notes
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $tab = $request->query('tab', 'my'); // Default to 'my' for authenticated users, 'all' for guests
        
        // For guests, default to 'all'
        if (!$user) {
            $tab = 'all';
        }
        
        $allNotes = [];
        $myNotes = [];
        
        if ($user && $user->personalSpan) {
            // Get notes created by the current user
            $myNotesQuery = Span::where('type_id', 'note')
                ->where('owner_id', $user->id)
                ->orderBy('start_year', 'desc')
                ->orderBy('start_month', 'desc')
                ->orderBy('start_day', 'desc')
                ->with('owner');
            
            $myNotes = $myNotesQuery->get();
        }
        
        // Get all viewable notes (public + shared with user + created by user)
        $allNotesQuery = Span::where('type_id', 'note')
            ->where(function ($query) use ($user) {
                // Public notes
                $query->where('access_level', 'public');
                
                // Private notes created by this user
                if ($user) {
                    $query->orWhere(function ($q) use ($user) {
                        $q->where('access_level', 'private')
                          ->where('owner_id', $user->id);
                    });
                }
                
                // Shared notes visible to this user
                if ($user) {
                    $query->orWhereHas('spanPermissions', function ($q) use ($user) {
                        $q->whereHas('group', function ($gq) use ($user) {
                            $gq->whereHas('users', function ($uq) use ($user) {
                                $uq->where('users.id', $user->id);
                            });
                        });
                    });
                }
            })
            ->orderBy('start_year', 'desc')
            ->orderBy('start_month', 'desc')
            ->orderBy('start_day', 'desc')
            ->with('owner');
        
        $allNotes = $allNotesQuery->get();
        
        return view('notes.index', compact('allNotes', 'myNotes', 'tab', 'user'));
    }
}

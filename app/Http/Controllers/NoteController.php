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
        $myNotes = collect();
        $annotatingNotes = collect();
        
        if ($user && $user->personalSpan) {
            // Get notes created by the current user
            // Load annotation connections with annotated spans for sorting
            $myNotesQuery = Span::where('type_id', 'note')
                ->where('owner_id', $user->id)
                ->with(['owner', 'connectionsAsSubject' => function($q) {
                    $q->where('type_id', 'annotates')
                      ->with(['child:id,name,start_year,start_month,start_day,end_year,end_month,end_day,start_precision,end_precision']);
                }, 'connectionsAsSubject.child']);
            
            $myNotes = $myNotesQuery->get();
            
            // Filter out notes that annotate other spans from "My Notes"
            $myNotes = $myNotes->filter(function($note) {
                $annotatedSpans = $note->connectionsAsSubject
                    ->where('type_id', 'annotates')
                    ->pluck('child')
                    ->filter();
                // Only include notes that don't annotate other spans
                return $annotatedSpans->isEmpty();
            });
            
            // Sort by note's own date
            $myNotes = $myNotes->sortBy(function($note) {
                return [
                    -($note->start_year ?? -9999), // Negate for descending
                    -($note->start_month ?? -12),
                    -($note->start_day ?? -31),
                    $note->id
                ];
            })->values();
        }
        
        // Get all viewable notes (public + shared with user + created by user) for the annotating tab
        // Load annotation connections with annotated spans for sorting
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
            ->with(['owner', 'connectionsAsSubject' => function($q) {
                $q->where('type_id', 'annotates')
                  ->with(['child:id,name,start_year,start_month,start_day,end_year,end_month,end_day,start_precision,end_precision']);
            }, 'connectionsAsSubject.child']);
        
        $allNotes = $allNotesQuery->get();
        
        // Get notes that annotate other spans (filter from allNotes)
        $annotatingNotes = $allNotes->filter(function($note) {
            $annotatedSpans = $note->connectionsAsSubject
                ->where('type_id', 'annotates')
                ->pluck('child')
                ->filter();
            return $annotatedSpans->isNotEmpty();
        });
        
        // Sort annotating notes by annotated span's date
        $annotatingNotes = $annotatingNotes->sortBy(function($note) {
            $annotatedSpans = $note->connectionsAsSubject
                ->where('type_id', 'annotates')
                ->pluck('child')
                ->filter();
            
            // Use the first annotated span's date for sorting
            // Negate year/month/day for descending sort
            $firstSpan = $annotatedSpans->first();
            return [
                -($firstSpan->start_year ?? -9999), // Negate for descending
                -($firstSpan->start_month ?? -12),
                -($firstSpan->start_day ?? -31),
                $note->id
            ];
        })->values();
        
        return view('notes.index', compact('myNotes', 'annotatingNotes', 'tab', 'user'));
    }
}

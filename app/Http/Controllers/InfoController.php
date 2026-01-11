<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class InfoController extends Controller
{
    /**
     * Display the info page with summary and stats.
     */
    public function index(): View
    {
        $user = auth()->user();
        $personalSpan = $user->personalSpan;
        
        // Load connections once for all components to avoid duplicate queries
        $userConnectionsAsSubject = collect();
        $userConnectionsAsObject = collect();
        $allUserConnections = collect();
        
        if ($personalSpan) {
            // Load connections as subject (outgoing) with eager loading
            $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', function($query) {
                    $query->whereNotNull('start_year');
                })
                ->where('child_id', '!=', $personalSpan->id)
                ->with(['connectionSpan', 'child', 'type'])
                ->get();
            
            // Load connections as object (incoming) with eager loading
            $userConnectionsAsObject = $personalSpan->connectionsAsObject()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', function($query) {
                    $query->whereNotNull('start_year');
                })
                ->where('parent_id', '!=', $personalSpan->id)
                ->with(['connectionSpan', 'parent', 'type'])
                ->get();
            
            // Combine for components that need all connections
            $allUserConnections = $userConnectionsAsSubject->concat($userConnectionsAsObject);
        }
        
        return view('info', compact('personalSpan', 'userConnectionsAsSubject', 'userConnectionsAsObject', 'allUserConnections'));
    }
}

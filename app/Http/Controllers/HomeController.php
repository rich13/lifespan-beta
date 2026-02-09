<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the home page. For authenticated users, pre-load personal span and
     * connection collections so the view and components avoid duplicate queries.
     * For guests, pass null/empty so home-guest renders without errors.
     */
    public function __invoke(): View
    {
        $personalSpan = null;
        $userConnectionsAsSubject = collect();
        $userConnectionsAsObject = collect();
        $allUserConnections = collect();

        if (Auth::check()) {
            $user = Auth::user();
            $personalSpan = $user->personalSpan;

            if ($personalSpan) {
                $userConnectionsAsSubject = $personalSpan->connectionsAsSubject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan', function ($query) {
                        $query->whereNotNull('start_year');
                    })
                    ->where('child_id', '!=', $personalSpan->id)
                    ->with(['connectionSpan', 'child', 'type'])
                    ->get();

                $userConnectionsAsObject = $personalSpan->connectionsAsObject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan', function ($query) {
                        $query->whereNotNull('start_year');
                    })
                    ->where('parent_id', '!=', $personalSpan->id)
                    ->with(['connectionSpan', 'parent', 'type'])
                    ->get();

                $allUserConnections = $userConnectionsAsSubject->concat($userConnectionsAsObject);
            }
        }

        return view('home', compact(
            'personalSpan',
            'userConnectionsAsSubject',
            'userConnectionsAsObject',
            'allUserConnections'
        ));
    }
}

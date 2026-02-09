<?php

namespace App\Http\Controllers;

use App\Services\MicroStoryService;
use Illuminate\View\View;

class MeController extends Controller
{
    /**
     * Display the Me page (personal homepage mode) with pre-loaded connections
     * so life-heatmap-card and related components avoid duplicate heavy queries.
     */
    public function __invoke(): View
    {
        $user = auth()->user();
        $personalSpan = $user->personalSpan;

        $userConnectionsAsSubject = collect();
        $userConnectionsAsObject = collect();
        $allUserConnections = collect();
        $biography = ['title' => 'Life sentences', 'sentences' => []];

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
            $biography = app(MicroStoryService::class)->generateBiography($personalSpan, $allUserConnections);
        }

        return view('me', compact(
            'personalSpan',
            'userConnectionsAsSubject',
            'userConnectionsAsObject',
            'allUserConnections',
            'biography'
        ));
    }
}

@props(['span'])

@php
    // Only show for organisation spans
    if ($span->type_id !== 'organisation') {
        return;
    }

    // Get all people who have employment connections to this organisation
    $employmentConnections = \App\Models\Connection::where('type_id', 'employment')
        ->where('child_id', $span->id) // Organisation is the child in employment connections
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent'])
        ->get();

    // Get all people who have has_role connections with at_organisation connections to this organisation
    $roleConnections = \App\Models\Connection::where('type_id', 'at_organisation')
        ->where('child_id', $span->id) // Organisation is the child in at_organisation connections
        ->whereHas('parent', function($q) {
            $q->whereHas('connectionsAsSubject', function($q2) {
                $q2->where('type_id', 'has_role');
            });
        })
        ->whereHas('parent.connectionsAsSubject', function($q) {
            $q->where('type_id', 'has_role')
              ->whereHas('parent', function($q2) { $q2->where('type_id', 'person'); });
        })
        ->with(['parent.connectionsAsSubject.parent'])
        ->get();

    // Also get people who have has_role connections where the connection span has at_organisation connections to this organisation
    // This covers the case: Person -> has_role -> Role (creates connection span) -> at_organisation -> Organisation
    $roleToOrgConnections = \App\Models\Connection::where('type_id', 'has_role')
        ->whereHas('connectionSpan', function($q) use ($span) {
            $q->whereHas('connectionsAsSubject', function($q2) use ($span) {
                $q2->where('type_id', 'at_organisation')
                   ->where('child_id', $span->id);
            });
        })
        ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
        ->with(['parent', 'connectionSpan.connectionsAsSubject'])
        ->get();

    // Collect all unique people
    $allEmployees = collect();
    
    // Add people from employment connections
    foreach ($employmentConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $allEmployees->put($connection->parent->id, [
                'person' => $connection->parent,
                'connection_type' => 'employment',
                'connection' => $connection
            ]);
        }
    }
    
    // Add people from role connections (at_organisation -> has_role -> person)
    foreach ($roleConnections as $connection) {
        // Find the has_role connection that connects to a person
        foreach ($connection->parent->connectionsAsSubject as $roleConnection) {
            if ($roleConnection->type_id === 'has_role' && $roleConnection->parent && $roleConnection->parent->type_id === 'person') {
                $allEmployees->put($roleConnection->parent->id, [
                    'person' => $roleConnection->parent,
                    'connection_type' => 'has_role',
                    'connection' => $roleConnection
                ]);
                break; // Only need one connection per person
            }
        }
    }
    
    // Add people from role-to-organisation connections (person -> has_role -> role -> at_organisation -> organisation)
    foreach ($roleToOrgConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $allEmployees->put($connection->parent->id, [
                'person' => $connection->parent,
                'connection_type' => 'has_role_via_role',
                'connection' => $connection
            ]);
        }
    }

    // Sort employees by name
    $allEmployees = $allEmployees->sortBy(function($item) {
        return $item['person']->name;
    })->values();
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/worked-at') }}" class="text-decoration-none">
                Employees
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        @if($allEmployees->isEmpty())
            <div class="text-center text-muted py-3">
                <i class="bi bi-people me-2"></i>No employees recorded
            </div>
        @else
            <div class="d-flex flex-wrap gap-1">
                @foreach($allEmployees as $employee)
                    <a href="{{ route('spans.show', $employee['person']) }}" 
                       class="badge bg-primary text-decoration-none" 
                       title="{{ $employee['person']->name }}">
                        {{ $employee['person']->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

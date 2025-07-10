@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>{{ $group->name }}</h1>
                <div>
                    <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-secondary me-2">
                        <i class="bi bi-pencil"></i> Edit Group
                    </a>
                    <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Groups
                    </a>
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Group Details</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Name:</dt>
                                <dd class="col-sm-8">{{ $group->name }}</dd>

                                <dt class="col-sm-4">Description:</dt>
                                <dd class="col-sm-8">{{ $group->description ?: 'No description' }}</dd>

                                <dt class="col-sm-4">Owner:</dt>
                                <dd class="col-sm-8">{{ $group->owner->name }}</dd>

                                <dt class="col-sm-4">Members:</dt>
                                <dd class="col-sm-8">{{ $group->users->count() }} users, {{ $group->spanPermissions->count() }} spans</dd>

                                <dt class="col-sm-4">Created:</dt>
                                <dd class="col-sm-8">{{ $group->created_at->format('Y-m-d H:i') }}</dd>

                                <dt class="col-sm-4">Last Updated:</dt>
                                <dd class="col-sm-8">{{ $group->updated_at->format('Y-m-d H:i') }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Member</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.groups.add-member', $group) }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-8">
                                        <select name="user_id" class="form-select" required>
                                            <option value="">Select a user...</option>
                                            @foreach($allUsers as $user)
                                                @if(!$group->hasMember($user))
                                                    <option value="{{ $user->id }}">
                                                        {{ $user->name }} ({{ $user->email }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <button type="submit" class="btn btn-primary w-100">Add Member</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">In this Group</h5>
                        </div>
                        <div class="card-body">
                            @if($group->users->count() > 0 || $group->spanPermissions->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name/Span</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                // Create a unified list of all items in the group
                                                $groupItems = [];
                                                $processedUserIds = [];
                                                
                                                // Debug: Let's see what we're working with
                                                $memberIds = $group->users->pluck('id')->toArray();
                                                
                                                // Process all spans first
                                                foreach($group->spanPermissions as $permission) {
                                                    // Debug: Check span ownership
                                                    $spanOwnerId = $permission->span->owner_id;
                                                    $isGroupMember = in_array($spanOwnerId, $memberIds);
                                                    
                                                    // Debug: Log this span
                                                    $groupItems[] = [
                                                        'type' => 'debug',
                                                        'span_name' => $permission->span->name,
                                                        'span_owner_id' => $spanOwnerId,
                                                        'is_member' => $isGroupMember ? 'yes' : 'no'
                                                    ];
                                                    
                                                    // Check if this span belongs to a group member (using owner_id)
                                                    
                                                    if ($isGroupMember) {
                                                        // This is a member's span - add to unified member entry
                                                        if (!in_array($permission->span->owner_id, $processedUserIds)) {
                                                            $processedUserIds[] = $permission->span->owner_id;
                                                            $user = $group->users->where('id', $permission->span->owner_id)->first();
                                                            
                                                            $groupItems[] = [
                                                                'type' => 'member',
                                                                'user' => $user,
                                                                'spans' => [$permission]
                                                            ];
                                                        } else {
                                                            // Add additional span to existing member entry
                                                            foreach($groupItems as &$item) {
                                                                if ($item['type'] === 'member' && $item['user']->id === $permission->span->owner_id) {
                                                                    $item['spans'][] = $permission;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    } else {
                                                        // This is a standalone span (not owned by a group member)
                                                        $groupItems[] = [
                                                            'type' => 'span',
                                                            'permission' => $permission,
                                                            'user' => $permission->span->owner
                                                        ];
                                                    }
                                                }
                                                
                                                // Add any members who don't have spans in the group
                                                foreach($group->users as $user) {
                                                    if (!in_array($user->id, $processedUserIds)) {
                                                        $groupItems[] = [
                                                            'type' => 'member',
                                                            'user' => $user,
                                                            'spans' => []
                                                        ];
                                                    }
                                                }
                                            @endphp
                                            
                                            <!-- Debug: Group has {{ count($groupItems) }} items -->
                                            <!-- Debug: Member IDs: {{ implode(', ', $memberIds) }} -->
                                            @foreach($groupItems as $item)
                                                @if($item['type'] === 'debug')
                                                    <tr>
                                                        <td colspan="2" class="text-muted small">
                                                            Debug: {{ $item['span_name'] }} (owner: {{ $item['span_owner_id'] }}, is member: {{ $item['is_member'] }})
                                                        </td>
                                                    </tr>
                                                @else
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            @if($item['type'] === 'member')
                                                                <i class="bi bi-person me-2 text-primary"></i>
                                                                <div>
                                                                    <div>{{ $item['user']->name }}</div>
                                                                    <small class="text-muted">{{ $item['user']->email }}</small>
                                                                </div>
                                                                <div class="ms-2">
                                                                    <span class="badge bg-primary">Member</span>
                                                                    @foreach($item['spans'] as $permission)
                                                                        <span class="badge bg-secondary ms-1">{{ $permission->span->type->name }}</span>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <i class="bi bi-diagram-3 me-2 text-warning"></i>
                                                                <div>
                                                                    <div>
                                                                        <a href="{{ route('spans.show', $item['permission']->span) }}" 
                                                                           class="text-decoration-none">
                                                                            {{ $item['permission']->span->name }}
                                                                        </a>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        {{ ucfirst($item['permission']->permission_type) }} permission
                                                                    </small>
                                                                </div>
                                                                <span class="badge bg-secondary ms-2">{{ $item['permission']->span->type->name }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($item['type'] === 'member')
                                                            @if($item['user']->id !== $group->owner_id)
                                                                <form action="{{ route('admin.groups.remove-member', [$group, $item['user']]) }}" 
                                                                      method="POST" 
                                                                      class="d-inline"
                                                                      onsubmit="return confirm('Are you sure you want to remove {{ $item['user']->name }} from this group?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                        <i class="bi bi-person-x"></i> Remove
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="badge bg-success">Owner</span>
                                                            @endif
                                                        @else
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <a href="{{ route('spans.show', $item['permission']->span) }}" 
                                                                   class="btn btn-outline-primary">
                                                                    <i class="bi bi-eye"></i> View
                                                                </a>
                                                                <form action="{{ route('admin.spans.permissions.revoke-group', [$item['permission']->span, $group, $item['permission']->permission_type]) }}" 
                                                                      method="POST" 
                                                                      class="d-inline"
                                                                      onsubmit="return confirm('Are you sure you want to remove this span from the group?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger">
                                                                        <i class="bi bi-x"></i> Remove
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted">No members or spans in this group.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
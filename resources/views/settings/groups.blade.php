@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Settings',
                'url' => route('settings.index'),
                'icon' => 'gear',
                'icon_category' => 'action'
            ],
            [
                'text' => 'Groups',
                'url' => route('settings.groups'),
                'icon' => 'people',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar Menu -->
            <div class="col-md-3">
                <x-settings-nav active="groups" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">

                <!-- Groups You Own -->
                @if($ownedGroups->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-crown me-2"></i>Groups You Own
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($ownedGroups as $group)
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">{{ $group->name }}</h6>
                                            <span class="badge bg-success">Owner</span>
                                        </div>
                                        @if($group->description)
                                            <p class="card-text text-muted small mb-2">{{ $group->description }}</p>
                                        @endif
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-people me-1"></i>
                                            {{ $group->users->count() }} member{{ $group->users->count() !== 1 ? 's' : '' }}
                                        </div>
                                        <div class="small text-muted">
                                            Created {{ $group->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- Groups You're In -->
                @if($memberGroups->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-person-check me-2"></i>Groups You're In
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($memberGroups as $group)
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">{{ $group->name }}</h6>
                                            <span class="badge bg-info">Member</span>
                                        </div>
                                        @if($group->description)
                                            <p class="card-text text-muted small mb-2">{{ $group->description }}</p>
                                        @endif
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-person me-1"></i>
                                            Owner: {{ $group->owner->personalSpan?->name ?? $group->owner->email }}
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-people me-1"></i>
                                            {{ $group->users->count() }} member{{ $group->users->count() !== 1 ? 's' : '' }}
                                        </div>
                                        <div class="small text-muted">
                                            Joined {{ $group->pivot->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- No Groups Message -->
                @if($memberGroups->count() === 0 && $ownedGroups->count() === 0)
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-people text-muted mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title text-muted">No Groups Yet</h5>
                        <p class="card-text text-muted">
                            You're not a member of any groups yet. Groups help you share spans with specific people.
                        </p>
                        @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-primary">
                            <i class="bi bi-gear me-2"></i>Manage Groups (Admin)
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection 
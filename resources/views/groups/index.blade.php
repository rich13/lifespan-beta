@extends('layouts.app')

@php
    use Illuminate\Support\Str;
@endphp

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Groups',
            'icon' => 'people-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <i class="bi bi-people-fill me-2"></i>Groups
                </h1>
            </div>
            
            @if($groups->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-people text-muted mb-3" style="font-size: 3rem;"></i>
                        <h3 class="text-muted">No Groups Yet</h3>
                        <p class="text-muted">You're not a member of any groups yet. Groups help you see combined timelines of all members.</p>
                    </div>
                </div>
            @else
                <div class="row">
                    @foreach($groups as $group)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="{{ route('groups.show', ['group' => Str::slug($group->name)]) }}" class="text-decoration-none">
                                            {{ $group->name }}
                                        </a>
                                    </h5>
                                    @if($group->description)
                                        <p class="card-text text-muted small">{{ Str::limit($group->description, 100) }}</p>
                                    @endif
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="small text-muted">
                                            <i class="bi bi-people me-1"></i>
                                            {{ $group->users->count() }} member{{ $group->users->count() !== 1 ? 's' : '' }}
                                        </div>
                                        <a href="{{ route('groups.show', ['group' => Str::slug($group->name)]) }}" class="btn btn-sm btn-primary">
                                            View Timeline
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Import Preview: {{ isset($yaml['name']) ? $yaml['name'] : 'Unknown' }}</h1>
            <div>
                <a href="{{ route('admin.import.index') }}" class="btn btn-outline-secondary me-2">Back</a>
                @if(!isset($report))
                    <form action="{{ route('admin.import.simulate', $id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">Simulate Import</button>
                    </form>
                @elseif(empty($report['errors']))
                    <form action="{{ route('admin.import.import', $id) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            {{ isset($report['main_span']['existing']) && $report['main_span']['existing'] ? 'Update' : 'Import' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if(isset($report))
                @if(!empty($report['errors']))
                    <div class="alert alert-danger">
                        <h5 class="alert-heading">Errors Found</h5>
                        <ul class="mb-0">
                            @foreach($report['errors'] as $error)
                                <li>{{ is_array($error) ? $error['message'] : $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($report['warnings']))
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Warnings</h5>
                        <ul class="mb-0">
                            @foreach($report['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Main Span -->
                @if(isset($report['main_span']))
                    <div class="mb-4">
                        <h5>Main Span</h5>
                        <div class="card">
                            <div class="card-body">
                                @if(isset($report['main_span']['existing']) && $report['main_span']['existing'])
                                    <div class="alert alert-info">
                                        This span already exists and will be updated with any new information.
                                    </div>
                                @endif
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Name</dt>
                                    <dd class="col-sm-9">{{ $report['main_span']['name'] ?? $yaml['name'] ?? 'Unknown' }}</dd>
                                    
                                    <dt class="col-sm-3">Type</dt>
                                    <dd class="col-sm-9">{{ $report['main_span']['type'] ?? $yaml['type'] ?? 'Unknown' }}</dd>
                                    
                                    @if(isset($report['main_span']['dates']))
                                        <dt class="col-sm-3">Dates</dt>
                                        <dd class="col-sm-9">{{ $report['main_span']['dates'] }}</dd>
                                    @endif
                                </dl>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Related Data -->
                @php
                    $sections = [
                        'family' => 'Family Connections',
                        'education' => 'Education',
                        'work' => 'Work History',
                        'residences' => 'Places of Residence',
                        'relationships' => 'Relationships'
                    ];
                @endphp

                @foreach($sections as $key => $title)
                    @if(isset($report[$key]) && isset($report[$key]['total']) && $report[$key]['total'] > 0)
                        <div class="mb-4">
                            <h5>{{ $title }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    <p class="card-text">
                                        Total: {{ $report[$key]['total'] }}
                                        @if(isset($report[$key]['existing']) && $report[$key]['existing'] > 0)
                                            <br>
                                            <span class="text-info">
                                                {{ $report[$key]['existing'] }} existing items will be updated
                                            </span>
                                        @endif
                                        @if(isset($report[$key]['will_create']) && $report[$key]['will_create'] > 0)
                                            <br>
                                            <span class="text-success">
                                                {{ $report[$key]['will_create'] }} new items will be created
                                            </span>
                                        @endif
                                    </p>
                                    
                                    @if(!empty($report[$key]['details']))
                                        <div class="mt-3">
                                            <h6>Details:</h6>
                                            <ul class="list-unstyled mb-0">
                                                @foreach($report[$key]['details'] as $detail)
                                                    <li class="mb-2">
                                                        {{ $detail['name'] ?? $detail['person'] ?? 'Unknown' }}
                                                        @if(isset($detail['role']))
                                                            ({{ $detail['role'] }})
                                                        @endif
                                                        - 
                                                        @php
                                                            $actionKey = isset($detail['person_action']) ? 'person_action' : 'action';
                                                            $action = $detail[$actionKey] ?? 'unknown';
                                                        @endphp
                                                        @if($action === 'will_create')
                                                            <span class="text-success">Will be created</span>
                                                        @elseif($action === 'will_update' || $action === 'will_use_existing')
                                                            <span class="text-info">Will use existing</span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @else
                <div class="alert alert-info">
                    Click "Import" to simulate the import process and see what changes will be made.
                </div>
                
                <div class="card">
                    <div class="card-header">YAML Content</div>
                    <div class="card-body">
                        <pre class="mb-0"><code>{{ $formatted }}</code></pre>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 
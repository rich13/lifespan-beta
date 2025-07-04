@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Span Details</h2>
        
        <div class="d-flex flex-column gap-3">
            <!-- Type and Subtype -->
            <x-spans.partials.type :span="$span" />

            <!-- Description -->
            @if($span->description)
                <x-spans.partials.description :span="$span" />
            @endif

            <!-- Date Range -->
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar3 text-muted"></i>
                <x-spans.partials.date-range :span="$span" />
            </div>
            
            <!-- Age -->
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clock text-muted"></i>
                <x-spans.partials.age :span="$span" />
            </div>

            <!-- Person-specific connections -->
            @if($span->type_id === 'person')
                @foreach($span->connections as $connection)
                    @if($connection->type_id === 'birth_place')
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-muted"></i>
                            <div>
                                <small class="text-muted d-block">Birth Place</small>
                                <x-spans.display.micro-card :span="$connection->object" />
                            </div>
                        </div>
                    @endif
                    
                    @if($connection->type_id === 'death_place')
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-muted"></i>
                            <div>
                                <small class="text-muted d-block">Death Place</small>
                                <x-spans.display.micro-card :span="$connection->object" />
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
</div> 
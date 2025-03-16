@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Person Information</h2>
        
        <dl class="row mb-0">
            @if($span->metadata && isset($span->metadata['gender']))
                <dt class="col-sm-3">Gender</dt>
                <dd class="col-sm-9">{{ ucfirst($span->metadata['gender']) }}</dd>
            @endif
            
            @if($span->metadata && isset($span->metadata['nationality']))
                <dt class="col-sm-3">Nationality</dt>
                <dd class="col-sm-9">{{ $span->metadata['nationality'] }}</dd>
            @endif
            
            {{-- Birth and death places should be handled through connections --}}
            @foreach($span->connections as $connection)
                @if($connection->type_id === 'birth_place')
                    <dt class="col-sm-3">Birth Place</dt>
                    <dd class="col-sm-9">
                        <x-spans.display.micro-card :span="$connection->object" />
                    </dd>
                @endif
                
                @if($connection->type_id === 'death_place')
                    <dt class="col-sm-3">Death Place</dt>
                    <dd class="col-sm-9">
                        <x-spans.display.micro-card :span="$connection->object" />
                    </dd>
                @endif
            @endforeach
        </dl>
    </div>
</div> 
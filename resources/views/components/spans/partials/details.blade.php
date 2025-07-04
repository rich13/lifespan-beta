@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Details</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Type</dt>
            <dd class="col-sm-9">
                <div class="d-flex gap-2 align-items-center">
                    <x-spans.partials.type :span="$span" />
                    @if(isset($span->metadata['subtype']))
                        <a href="{{ route('spans.types.subtypes.show', ['type' => $span->type_id, 'subtype' => $span->metadata['subtype']]) }}" class="text-decoration-none badge bg-secondary">
                            {{ ucfirst($span->metadata['subtype']) }}
                        </a>
                    @endif
                </div>
            </dd>

            @if($span->description)
                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">
                    <div class="mb-2">
                        <x-spans.partials.description :span="$span" />
                    </div>
                </dd>
            @endif

            <dt class="col-sm-3">Dates</dt>
            <dd class="col-sm-9">
                <div class="mb-2">
                    <x-spans.partials.date-range :span="$span" />
                </div>
            </dd>

            <dt class="col-sm-3">Age</dt>
            <dd class="col-sm-9">
                <div class="mb-2">
                    <x-spans.partials.age :span="$span" />
                </div>         
            </dd>

            @if($span->type_id === 'person')
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
            @endif
        </dl>
    </div>
</div> 
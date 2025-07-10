@props(['span'])

@if(!empty($span->metadata) || ($span->type_id === 'person' && $span->connections->isNotEmpty()))
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title h5 mb-3">Additional Information</h2>
            <dl class="row mb-0">
                @foreach($span->metadata as $key => $value)
                    <dt class="col-sm-3">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-sm-9">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                @endforeach

                @if($span->type_id === 'person')
                    @foreach($span->connectionsAsObjectWithAccess() as $connection)
                        @if($connection->type_id === 'birth_place')
                            <dt class="col-sm-3">Birth Place</dt>
                            <dd class="col-sm-9">
                                <x-spans.display.micro-card :span="$connection->parent" />
                            </dd>
                        @endif
                        
                        @if($connection->type_id === 'death_place')
                            <dt class="col-sm-3">Death Place</dt>
                            <dd class="col-sm-9">
                                <x-spans.display.micro-card :span="$connection->parent" />
                            </dd>
                        @endif
                    @endforeach
                @endif
            </dl>
        </div>
    </div>
@endif 
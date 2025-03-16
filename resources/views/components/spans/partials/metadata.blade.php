@props(['span'])

@if(!empty($span->metadata))
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title h5 mb-3">Additional Information</h2>
            <dl class="row mb-0">
                @foreach($span->metadata as $key => $value)
                    <dt class="col-sm-3">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                    <dd class="col-sm-9">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                @endforeach
            </dl>
        </div>
    </div>
@endif 
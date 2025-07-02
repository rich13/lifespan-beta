@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Details</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Type</dt>
            <dd class="col-sm-9">
                <x-spans.partials.type :span="$span" />
            </dd>

            <dt class="col-sm-3">Time</dt>
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
        </dl>
    </div>
</div> 
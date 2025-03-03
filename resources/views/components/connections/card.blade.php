@props(['connection'])

<div class="card mb-3 connection-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <x-connections.micro-card :connection="$connection" />               
                    <x-spans.partials.date-range :span="$connection->connectionSpan" />
                </div>

                @if($connection->metadata)
                    @foreach($connection->metadata as $key => $value)
                        <div class="text-muted small">
                            <strong>{{ Str::title($key) }}:</strong> {{ $value }}
                        </div>
                    @endforeach
                @endif

                @if($connection->connectionSpan->description)
                    <p class="card-text">{{ Str::limit($connection->connectionSpan->description, 150) }}</p>
                @endif
            </div>

            <div class="ms-3">
                <x-spans.partials.actions :span="$connection->connectionSpan" />
            </div>
        </div>
    </div>
</div> 
@props(['span'])

<div class="card mb-3 span-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div>
                        <x-spans.display.micro-card :span="$span" />
                    </div>
                </div>
                
                <div class="mb-2">
                    <x-spans.partials.date-range :span="$span" />
                </div>

                @if($span->description)
                    <p class="card-text">{{ Str::limit($span->description, 150) }}</p>
                @endif
            </div>
        </div>
    </div>
</div> 
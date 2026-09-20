@if(($physicalPlaque['plaque'] ?? null))
    @php
        $physicalPlaqueSpan = $physicalPlaque['plaque'];
        $physicalPlaquePhotoUrl = $physicalPlaque['photoUrl'] ?? null;
    @endphp
    <div class="plaque-physical">
        @if($physicalPlaquePhotoUrl)
            <a href="{{ route('spans.show', $physicalPlaqueSpan) }}" class="plaque-physical-photo">
                <img src="{{ $physicalPlaquePhotoUrl }}" alt="{{ $physicalPlaqueSpan->getDisplayTitle() }}" loading="lazy">
            </a>
        @endif
        <a href="{{ route('spans.show', $physicalPlaqueSpan) }}" class="plaque-physical-name">
            {{ $physicalPlaqueSpan->getDisplayTitle() }}
        </a>
    </div>
@endif

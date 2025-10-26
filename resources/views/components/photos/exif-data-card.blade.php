@props(['photo'])

@php
    // Collect available EXIF data
    $exifData = [];
    
    if ($photo->metadata['camera_make'] ?? null) {
        $exifData['Camera Make'] = $photo->metadata['camera_make'];
    }
    
    if ($photo->metadata['camera_model'] ?? null) {
        $exifData['Camera Model'] = $photo->metadata['camera_model'];
    }
    
    if ($photo->metadata['software'] ?? null) {
        $exifData['Software'] = $photo->metadata['software'];
    }
    
    if ($photo->metadata['date_taken'] ?? null) {
        $exifData['Date Taken'] = \Carbon\Carbon::parse($photo->metadata['date_taken'])->format('M d, Y H:i');
    }
    
    if ($photo->metadata['coordinates'] ?? null) {
        $exifData['Coordinates'] = $photo->metadata['coordinates'];
    }
    
    if ($photo->metadata['coordinate_source'] ?? null) {
        $exifData['Coordinate Source'] = $photo->metadata['coordinate_source'];
    }
    
    if ($photo->metadata['image_description'] ?? null) {
        $exifData['Description'] = $photo->metadata['image_description'];
    }
@endphp

@if($exifData)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-camera me-2"></i>EXIF Data
            </h5>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                @foreach($exifData as $label => $value)
                    <dt class="col-sm-5 text-truncate" title="{{ $label }}">{{ $label }}:</dt>
                    <dd class="col-sm-7">
                        @if($label === 'Coordinates')
                            <a href="https://maps.google.com/?q={{ urlencode($value) }}" 
                               target="_blank" 
                               class="text-decoration-none">
                                {{ $value }}
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                            </a>
                        @else
                            <span class="text-break">{{ $value }}</span>
                        @endif
                    </dd>
                @endforeach
            </dl>
        </div>
    </div>
@endif

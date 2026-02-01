@props(['photo'])

@php
    $meta = $photo->metadata ?? [];
    $hasLicence = !empty($meta['license']) || !empty($meta['license_url']);
    $hasAttribution = isset($meta['requires_attribution']) || !empty($meta['author']);
    $hasSourceMeta = !empty($meta['source']) || !empty($meta['data_source']) || !empty($meta['external_id']);
    $sources = $photo->sources ?? [];
    $hasSources = !empty($sources);
    $showCard = $hasLicence || $hasAttribution || $hasSourceMeta || $hasSources;
@endphp

@if($showCard)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-journal-text me-2"></i>Licence &amp; sources
            </h5>
        </div>
        <div class="card-body">
            <dl class="row mb-0">

                @if(!empty($meta['license']) || !empty($meta['license_url']))
                    <dt class="col-sm-5">Licence</dt>
                    <dd class="col-sm-7">
                        @if(!empty($meta['license_url']))
                            <a href="{{ $meta['license_url'] }}" target="_blank" rel="noopener noreferrer" class="text-break">
                                {{ $meta['license'] ?? 'View licence' }}
                                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                            </a>
                        @else
                            <span class="text-break">{{ $meta['license'] }}</span>
                        @endif
                    </dd>
                @endif

                @if(isset($meta['requires_attribution']))
                    <dt class="col-sm-5">Attribution</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-{{ $meta['requires_attribution'] ? 'warning' : 'secondary' }}">
                            {{ $meta['requires_attribution'] ? 'Required' : 'Not required' }}
                        </span>
                    </dd>
                @endif

                @if(!empty($meta['author']))
                    <dt class="col-sm-5">Author / creator</dt>
                    <dd class="col-sm-7"><span class="text-break">{{ $meta['author'] }}</span></dd>
                @endif

                @if(!empty($meta['source']))
                    <dt class="col-sm-5">Source</dt>
                    <dd class="col-sm-7"><span class="text-break">{{ $meta['source'] }}</span></dd>
                @endif

                @if(!empty($meta['data_source']))
                    <dt class="col-sm-5">Data source</dt>
                    <dd class="col-sm-7"><span class="text-break">{{ $meta['data_source'] }}</span></dd>
                @endif

                @if(!empty($meta['external_id']))
                    <dt class="col-sm-5">External ID</dt>
                    <dd class="col-sm-7"><span class="text-break">{{ $meta['external_id'] }}</span></dd>
                @endif

                @if(!empty($sources))
                    <dt class="col-sm-5">Source links</dt>
                    <dd class="col-sm-7">
                        <ul class="list-unstyled mb-0">
                            @foreach($sources as $source)
                                @php
                                    $url = is_array($source) ? ($source['url'] ?? null) : $source;
                                    $title = is_array($source) ? ($source['title'] ?? $source['label'] ?? null) : null;
                                @endphp
                                @if($url)
                                    <li class="mb-1">
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-break">
                                            {{ $title ?: $url }}
                                            <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.75rem;"></i>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </dd>
                @endif

            </dl>
        </div>
    </div>
@endif

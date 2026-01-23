<div class="mb-4">
    <div class="alert alert-success">
        <h6 class="fw-semibold mb-2"><i class="bi bi-emoji-smile me-2"></i>Hello!</h6>
        <ul>
            <li>Lifespan is a platform for mapping and exploring time.</li>
            <li>This is a <strong>proto-prototype</strong>, built in a very random and exploratory way...</li>
        </ul>
    </div>
</div>

<div class="mb-4">
    <div class="alert alert-primary">
        <h6 class="fw-semibold mb-2"><i class="bi bi-megaphone me-2"></i>Latest Features</h6>
        @php
            $latestFeaturesPath = resource_path('markdown/latest-features.md');
            $latestFeatures = file_exists($latestFeaturesPath) 
                ? Illuminate\Support\Str::markdown(file_get_contents($latestFeaturesPath))
                : '<ul><li>No features to display</li></ul>';
        @endphp
        {!! $latestFeatures !!}
    </div>
</div>

<div class="mb-4">
    <div class="alert alert-danger">
        <div class="row">
            <div class="col-sm-4"><strong>Version:</strong></div>
            <div class="col-sm-8">{{ \App\Helpers\GitVersionHelper::getVersion() }}</div>
        </div>
        @php
            $versionDetails = \App\Helpers\GitVersionHelper::getDetailedVersion();
        @endphp
        @if($versionDetails)
            @if(isset($versionDetails['commit']))
                <div class="row mt-1">
                    <div class="col-sm-4"><strong>Commit:</strong></div>
                    <div class="col-sm-8"><code>{{ substr($versionDetails['commit'], 0, 8) }}</code></div>
                </div>
            @endif
            @if(isset($versionDetails['date']))
                <div class="row mt-1">
                    <div class="col-sm-4"><strong>Build Date:</strong></div>
                    <div class="col-sm-8">{{ $versionDetails['date'] }}</div>
                </div>
            @endif
        @endif
    </div>
</div>

<div class="mb-4">
    <div class="alert alert-warning">
        <p class="mb-0">
            Coming soon: <a href="https://info.lifespan.dev" target="_blank" class="alert-link">https://info.lifespan.dev</a>
        </p>
    </div>
</div>

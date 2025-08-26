@props(['span'])

@php
use Illuminate\Support\Facades\Auth;
use App\Services\JourneyService;

$user = Auth::user();
$journey = null;

// Only show this card if user is authenticated and has a personal span
if ($user && $user->personalSpan) {
    // Don't show if it's the user's own span
    if ($user->personalSpan->id !== $span->id) {
        try {
            $journeyService = app(JourneyService::class);
            $journey = $journeyService->findPathToSpan($user->personalSpan, $span, 6);
        } catch (\Exception $e) {
            // Silently fail - don't show the card if there's an error
            $journey = null;
        }
    }
}
@endphp

@if($journey)
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="card-title mb-0">
                <i class="bi bi-arrow-right-circle me-2"></i>
                Your Connection to {{ $span->name }}
            </h6>
        </div>
        <div class="card-body">
            
            <div class="bg-light p-3 rounded">
                @php
                    $path = $journey['path'];
                    $connections = $journey['connections'];
                    $journeySteps = [];
                    
                    for ($i = 0; $i < count($path) - 1; $i++) {
                        $currentSpan = $path[$i];
                        $nextSpan = $path[$i + 1];
                        $connection = $connections[$i] ?? null;
                        
                        if ($connection) {
                            // Determine if we should use forward or reverse predicate
                            $isForward = $connection->parent_id === $currentSpan->id;
                            $predicate = $isForward ? $connection->type->forward_predicate : $connection->type->inverse_predicate;
                            
                            // Create natural sentence with links
                            $currentLink = '<a href="' . route('spans.show', $currentSpan) . '" class="text-decoration-none fw-bold">' . $currentSpan->name . '</a>';
                            $nextLink = '<a href="' . route('spans.show', $nextSpan) . '" class="text-decoration-none fw-bold">' . $nextSpan->name . '</a>';
                            $step = $currentLink . ' ' . $predicate . ' ' . $nextLink;
                            $journeySteps[] = $step;
                        }
                    }
                @endphp
                
                @foreach($journeySteps as $index => $step)
                    <div class="mb-2">
                        {!! $step !!}
                        @if($index < count($journeySteps) - 1)
                            <i class="bi bi-arrow-down text-muted ms-2"></i>
                        @endif
                    </div>
                @endforeach
            </div>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Imagine if this worked with time as well... needs a bit more work...
                </small>
            </div>
        </div>
    </div>
@endif


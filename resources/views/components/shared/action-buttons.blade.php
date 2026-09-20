@props(['span' => null, 'variant' => 'desktop'])

@php
use Illuminate\Support\Facades\Auth;
use App\Services\AiYamlCreatorService;
@endphp

<!-- Shared Action Buttons Component -->
@auth
    @if($variant === 'mobile')
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary" 
                    data-bs-dismiss="offcanvas"
                    id="mobile-new-span-btn">
                <i class="bi bi-plus-circle me-2"></i>Create New Span
            </button>
            
            @if(request()->routeIs('spans.show') && $span)
                <a href="{{ route('research.show', $span) }}" 
                   class="btn btn-info"
                   data-bs-dismiss="offcanvas">
                    <i class="bi bi-search me-2"></i>Research This Span
                </a>
            @endif
            
            @if(request()->routeIs('spans.show') && $span && AiYamlCreatorService::supportsAiImprovement($span->type_id))
                <a href="{{ route('spans.improve', $span) }}"
                   class="btn btn-success"
                   data-bs-dismiss="offcanvas"
                   id="mobile-improve-span-btn">
                    <i class="bi bi-magic me-2"></i>Improve This Span
                </a>
            @endif
        </div>
    @else
        <div class="d-flex align-items-center">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-primary" 
                        id="new-span-btn"
                        title="Create a new span (⌘K)">
                    <i class="bi bi-plus-circle me-1"></i>New
                </button>
                
                @if(request()->routeIs('spans.show') && $span)
                    <a href="{{ route('research.show', $span) }}" 
                       class="btn btn-sm btn-info"
                       title="Research this span">
                        <i class="bi bi-search me-1"></i>Research
                    </a>
                @endif
                
                @if(request()->routeIs('spans.show') && $span && AiYamlCreatorService::supportsAiImprovement($span->type_id))
                    <a href="{{ route('spans.improve', $span) }}"
                       class="btn btn-sm btn-success"
                       id="improve-span-btn"
                       title="Improve this span with AI data (⌘I)">
                        <i class="bi bi-magic me-1"></i>Improve
                    </a>
                @endif
            </div>
        </div>
    @endif
@endauth

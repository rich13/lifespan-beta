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
                    data-bs-toggle="modal" data-bs-target="#newSpanModal" 
                    data-bs-dismiss="offcanvas"
                    id="mobile-new-span-btn">
                <i class="bi bi-plus-circle me-2"></i>Create New Span
            </button>
            
            @if(request()->routeIs('spans.show') && $span && AiYamlCreatorService::supportsAiImprovement($span->type_id))
                <button type="button" class="btn btn-success" 
                        data-bs-toggle="modal" data-bs-target="#newSpanModal" 
                        data-bs-dismiss="offcanvas"
                        id="mobile-improve-span-btn"
                        data-span-name="{{ $span->name }}"
                        data-span-type="{{ $span->type_id }}"
                        data-span-id="{{ $span->id }}">
                    <i class="bi bi-magic me-2"></i>Improve This Span
                </button>
            @endif
        </div>
    @else
        <div class="d-flex align-items-center me-3">
            <div class="me-3" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Create a new span (⌘K)">
                <button type="button" class="btn btn-sm btn-primary" 
                        data-bs-toggle="modal" data-bs-target="#newSpanModal" 
                        id="new-span-btn">
                    <i class="bi bi-plus-circle me-1"></i>New
                </button>
            </div>
            
            @if(request()->routeIs('spans.show') && $span && AiYamlCreatorService::supportsAiImprovement($span->type_id))
                <div class="me-3" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Improve this span with AI data (⌘I)">
                    <button type="button" class="btn btn-sm btn-success" 
                        data-bs-toggle="modal" data-bs-target="#newSpanModal" 
                        id="improve-span-btn"
                        data-span-name="{{ $span->name }}"
                        data-span-type="{{ $span->type_id }}"
                        data-span-id="{{ $span->id }}">
                        <i class="bi bi-magic me-1"></i>Improve
                    </button>
                </div>
            @endif
        </div>
    @endif
@endauth 
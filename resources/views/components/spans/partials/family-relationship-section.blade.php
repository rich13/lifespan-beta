@props(['title', 'members', 'isLegacy' => false, 'interactive' => false, 'colClass' => 'col-md-6'])

@php
    // Sort members by start date (birth year) - oldest first, null dates last
    $sortedMembers = $members->sortBy(function($member) {
        return $member->start_year ?? PHP_INT_MAX; // Put null dates at the end
    });
@endphp

@if($sortedMembers->isNotEmpty())
    <div class="{{ $colClass }}">
        <div class="border rounded p-3 bg-light h-100">
            <h4 class="h6 mb-2 text-muted">{{ $title }}</h4>
            <ul class="list-unstyled mb-0">
                @foreach($sortedMembers as $member)
                    <li class="mb-2">
                        @if($isLegacy)
                            <i class="bi bi-person-fill me-1"></i>
                            {{ $member }}
                        @elseif($interactive)
                            <x-spans.display.interactive-card :span="$member" />
                        @else
                            <x-spans.display.micro-card :span="$member" />
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif 
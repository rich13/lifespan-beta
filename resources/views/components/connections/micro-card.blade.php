@props(['connection'])

<span class="d-inline-flex align-items-center gap-1">
    @switch($connection->type_id)
        @case('education')
            <i class="bi bi-mortarboard-fill"></i>
            @break
        @case('work')
            <i class="bi bi-briefcase-fill"></i>
            @break
        @case('member_of')
            <i class="bi bi-people-fill"></i>
            @break
        @case('residence')
            <i class="bi bi-house-fill"></i>
            @break
        @case('family')
            <i class="bi bi-heart-fill"></i>
            @break
        @default
            <i class="bi bi-link-45deg"></i>
    @endswitch
    <span>
        <x-spans.display.micro-card :span="$connection->parent" />
        @if($connection->type_id === 'family')
            @php
                $parentGender = $connection->parent->getMeta('gender');
                $childGender = $connection->child->getMeta('gender');
                
                // Use the database relationship - parent_id and child_id define the relationship
                // The parent is always the parent, regardless of birth years
                if ($parentGender === 'male') {
                    $relation = 'is father of';
                } elseif ($parentGender === 'female') {
                    $relation = 'is mother of';
                } else {
                    $relation = 'is parent of';
                }
            @endphp
            <span class="text-muted">{{ $relation }}</span>
        @else
            <span class="text-muted">{{ strtolower($connection->type->forward_predicate) }}</span>
        @endif
        <x-spans.display.micro-card :span="$connection->child" />
    </span>
</span> 
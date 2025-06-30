@props(['connection'])

<span class="d-inline-flex align-items-center gap-1">
    <x-icon type="{{ $connection->type_id }}" category="connection" class="text-{{ $connection->type_id }}" />
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
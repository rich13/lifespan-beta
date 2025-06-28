@props(['connection'])

<span class="d-inline-flex align-items-center gap-1">
    @switch($connection->type_id)
        @case('education')
            <i class="bi bi-mortarboard-fill text-education"></i>
            @break
        @case('work')
            <i class="bi bi-briefcase-fill text-employment"></i>
            @break
        @case('member_of')
            <i class="bi bi-people-fill text-membership"></i>
            @break
        @case('residence')
            <i class="bi bi-house-fill text-residence"></i>
            @break
        @case('family')
            <i class="bi bi-heart-fill text-family"></i>
            @break
        @case('friend')
            <i class="bi bi-person-heart text-friend"></i>
            @break
        @case('relationship')
            <i class="bi bi-people text-relationship"></i>
            @break
        @case('created')
            <i class="bi bi-palette-fill text-created"></i>
            @break
        @case('contains')
            <i class="bi bi-box-seam text-contains"></i>
            @break
        @case('travel')
            <i class="bi bi-airplane text-travel"></i>
            @break
        @case('participation')
            <i class="bi bi-calendar-event text-participation"></i>
            @break
        @case('ownership')
            <i class="bi bi-key-fill text-ownership"></i>
            @break
        @case('has_role')
            <i class="bi bi-person-badge text-role"></i>
            @break
        @case('at_organisation')
            <i class="bi bi-building text-organisation"></i>
            @break
        @default
            <i class="bi bi-link-45deg text-secondary"></i>
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
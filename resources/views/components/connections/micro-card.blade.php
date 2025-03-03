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
        <span class="text-muted">{{ strtolower($connection->type->forward_predicate) }}</span>
        <x-spans.display.micro-card :span="$connection->child" />
    </span>
</span> 
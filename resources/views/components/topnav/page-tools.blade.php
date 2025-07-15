@props(['group' => true, 'class' => ''])

<!-- Page Tools Section -->
<div class="d-flex align-items-center {{ $class }}">
    @if(trim($__env->yieldContent('page_tools')))
        <div class="me-3">
            @if($group)
                <div class="btn-group">
                    @yield('page_tools')
                </div>
            @else
                @yield('page_tools')
            @endif
        </div>
    @elseif($slot && $slot->isNotEmpty())
        <div class="me-3">
            @if($group)
                <div class="btn-group">
                    {{ $slot }}
                </div>
            @else
                {{ $slot }}
            @endif
        </div>
    @endif
</div> 
@props(['group' => true, 'class' => ''])

<!-- Page Tools Section -->
<div class="d-flex align-items-center me-3 {{ $class }}">
    @if(trim($__env->yieldContent('page_tools')))
        <div>
            @if($group)
                <div class="btn-group">
                    @yield('page_tools')
                </div>
            @else
                @yield('page_tools')
            @endif
        </div>
    @elseif($slot && $slot->isNotEmpty())
        <div>
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
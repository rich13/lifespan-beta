<!-- Page Tools Section -->
<div class="px-3 d-flex align-items-center">
    @if(trim($__env->yieldContent('page_tools')))
        <div class="btn-group">
            @yield('page_tools')
        </div>
    @endif
</div> 
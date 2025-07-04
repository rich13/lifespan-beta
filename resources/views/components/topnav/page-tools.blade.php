<!-- Page Tools Section -->
<div class="d-flex align-items-center">
    @if(trim($__env->yieldContent('page_tools')))
        <div class="me-3">
            <div class="btn-group">
                @yield('page_tools')
            </div>
        </div>
    @endif
</div> 
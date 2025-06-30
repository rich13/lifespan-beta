<!-- Sidebar Footer -->
<div class="border-top border-light mt-3 sidebar-footer">
    <div class="p-3">
        <a href="https://info.lifespan.dev" target="_blank" class="text-decoration-none text-light small d-block mb-2">
            <i class="bi bi-info-circle me-1"></i> <span>About Lifespan</span>
        </a>
        <div class="text-light small opacity-75" 
             data-bs-toggle="tooltip" 
             data-bs-placement="top" 
             title="{{ json_encode(\App\Helpers\GitVersionHelper::getDetailedVersion(), JSON_PRETTY_PRINT) }}">
            <i class="bi bi-code-square me-1"></i> <span>{{ \App\Helpers\GitVersionHelper::getVersion() }}</span>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
@endpush 
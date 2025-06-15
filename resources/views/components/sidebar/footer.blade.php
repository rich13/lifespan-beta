<!-- Sidebar Footer -->
<div class="border-top border-light mt-3">
    <div class="p-3">
        <a href="https://info.lifespan.dev" target="_blank" class="text-decoration-none text-light small d-block mb-2">
            <i class="bi bi-info-circle me-1"></i> About Lifespan
        </a>
        <div class="text-light small opacity-75" 
             data-bs-toggle="tooltip" 
             data-bs-placement="top" 
             title="{{ json_encode(\App\Helpers\GitVersionHelper::getDetailedVersion(), JSON_PRETTY_PRINT) }}">
            <i class="bi bi-code-square me-1"></i> {{ \App\Helpers\GitVersionHelper::getVersion() }}
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
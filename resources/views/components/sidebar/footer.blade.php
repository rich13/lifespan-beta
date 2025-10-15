<!-- Sidebar Footer -->
<div class="border-top border-light mt-3 sidebar-footer">
    <div class="p-3">
        <a href="#" class="nav-link text-light" 
           data-bs-toggle="modal" data-bs-target="#aboutLifespanModal"
           onclick="
               console.log('About link clicked!');
               console.log('Modal element:', document.getElementById('aboutLifespanModal'));
               console.log('Modal visible:', document.getElementById('aboutLifespanModal')?.style.display);
               console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
               
               const modalEl = document.getElementById('aboutLifespanModal');
               if (modalEl) {
                   console.log('Modal found, trying to show...');
                   if (typeof bootstrap !== 'undefined') {
                       const modal = new bootstrap.Modal(modalEl);
                       modal.show();
                   } else if (typeof $ !== 'undefined') {
                       $(modalEl).modal('show');
                   } else {
                       console.log('No modal library available');
                   }
               } else {
                   console.log('Modal element not found!');
               }
               return false;
           "
           style="cursor: pointer;">
            <i class="bi bi-info-circle me-1"></i> <span>About Lifespan</span>
        </a>
    </div>
</div>


@push('scripts')
<script>
    $(document).ready(function() {
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Debug click events
        $('a[data-bs-target="#aboutLifespanModal"]').on('click', function(e) {
            console.log('jQuery click handler triggered');
            console.log('Event:', e);
            console.log('Element:', this);
            console.log('Modal exists:', $('#aboutLifespanModal').length);
            
            // Try to show modal manually
            e.preventDefault();
            $('#aboutLifespanModal').modal('show');
        });
        
        // Also try mousedown to see if element is receiving events
        $('a[data-bs-target="#aboutLifespanModal"]').on('mousedown', function() {
            console.log('Mouse down detected on About link');
        });
    });
</script>
@endpush 
@push('styles')
<style>
    /* Interactive card base styling */
    .interactive-card-base {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        background-color: #fff;
        transition: all 0.2s ease-in-out;
    }
    
    .interactive-card-base:hover {
        border-color: #adb5bd;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    /* Inactive connector button styling */
    .interactive-card-base .btn.inactive {
        background-color: #e9ecef;
        color: #6c757d;
        border-color: #ced4da;
        font-weight: 500;
        opacity: 0.8;
        cursor: default;
    }
    
    .interactive-card-base .btn.inactive:hover {
        background-color: #e9ecef;
        color: #6c757d;
        border-color: #ced4da;
    }
    
    /* Date button styling */
    .interactive-card-base .btn-group .btn.btn-outline-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
    }
    
    .interactive-card-base .btn-group .btn.btn-outline-info:hover {
        background-color: #c1e7ea;
        color: #0a3d44;
        border-color: #a8d8dd;
    }
</style>
@endpush 
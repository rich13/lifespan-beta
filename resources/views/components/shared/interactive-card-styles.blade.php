@push('styles')
<style>
    /* Interactive card base styling */
    .interactive-card-base {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        background-color: #fff;
        transition: all 0.2s ease-in-out;
        overflow: hidden; /* Prevent container overflow */
    }
    
    .interactive-card-base:hover {
        border-color: #adb5bd;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        cursor: default;
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
    
    /* Mini tooltip styling */
    .tooltip-mini .tooltip-inner {
        background-color: #000;
        color: #fff;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    /* Responsive button group styling */
    .interactive-card-base .btn-group {
        /* Container constraints */
        max-width: 100%;
        width: 100%;
        position: relative;
        display: flex;
        flex-wrap: nowrap;
        
        /* Prevent text wrapping in buttons */
        .btn {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: fit-content;
            max-width: none;
            
            /* Ensure consistent sizing */
            flex-shrink: 0;
            flex-grow: 0;
        }
    }

    /* All screen sizes - enable horizontal scrolling when needed */
    .interactive-card-base .btn-group {
        /* Create a scrollable container */
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
        
        /* Hide scrollbar for webkit browsers */
        &::-webkit-scrollbar {
            display: none;
        }
        
        /* Smooth scrolling */
        scroll-behavior: smooth;
        
        /* Ensure buttons don't wrap */
        flex-wrap: nowrap;
        
        /* Add some padding to show scroll indication */
        padding-bottom: 2px;
        
        /* Position relative for pseudo-elements */
        position: relative;
        
        .btn {
            /* Prevent buttons from shrinking */
            flex-shrink: 0;
            
            /* Ensure minimum readable width */
            min-width: max-content;
            
            /* Add subtle transition for hover effect */
            transition: transform 0.2s ease-in-out;
        }
        

    }

    /* Additional responsive adjustments for different screen sizes */
    @media (max-width: 767.98px) {
        .interactive-card-base .btn-group {
            .btn {
                /* Slightly reduce padding on very small screens */
                padding-left: 0.375rem;
                padding-right: 0.375rem;
            }
        }
    }
    
    @media (min-width: 768px) and (max-width: 991.98px) {
        .interactive-card-base .btn-group {
            .btn {
                /* Slightly larger minimum width for better readability */
                min-width: 60px;
            }
        }
    }

    /* Enhanced hover effects for better UX */
    .interactive-card-base .btn-group .btn {
        /* Smooth transitions for all interactions */
        transition: all 0.2s ease-in-out;
        
        /* Subtle scale effect on hover */
        &:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Active state */
        &:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
    }

    /* Special handling for icon-only buttons */
    .interactive-card-base .btn-group .btn[style*="min-width: 40px"] {
        /* Ensure icon buttons maintain their size */
        min-width: 40px !important;
        max-width: 40px;
        padding-left: 0.25rem;
        padding-right: 0.25rem;
        
        /* Center icons */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Tools button hover behavior */
    .tools-button {
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    .interactive-card-base:hover .tools-button {
        opacity: 1;
    }
    
    /* Tools expansion behavior */
    .tools-button .tools-expanded {
        visibility: hidden !important;
        position: absolute !important;
        transition: none; /* Remove transition for immediate response */
    }
    
    /* CSS fallback for showing tools on hover */
    .tools-button:hover .tools-expanded {
        visibility: visible !important;
        position: relative !important;
    }
    
    /* Ensure the toggle button stays visible */
    .tools-button .tools-toggle {
        visibility: visible !important;
        position: relative !important;
    }

    /* Micro story component styling */
    .micro-story {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        line-height: 1.4;
        color: #495057;
    }

    .micro-story-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        flex-shrink: 0;
    }

    .micro-story-content {
        flex: 1;
        min-width: 0; /* Allow text to wrap */
    }

    /* Micro story hover effects */
    .micro-story:hover {
        color: #212529;
    }

    .micro-story:hover .micro-story-icon {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }

    /* Micro story link styling */
    .micro-story .lead {
        font-size: 1.1rem;
        font-weight: 400;
        text-decoration: underline;
        transition: all 0.2s ease-in-out;
    }
    
    .micro-story .lead:hover {
        text-decoration: none;
    }
    
    /* Ensure proper spacing between elements */
    .micro-story-content {
        display: inline;
    }
    
    .micro-story-content .lead {
        display: inline;
        vertical-align: baseline;
    }
    
    /* Responsive micro story */
    @media (max-width: 767.98px) {
        .micro-story {
            font-size: 0.85rem;
            gap: 0.375rem;
        }
        
        .micro-story-icon {
            width: 20px;
            height: 20px;
        }
        
        .micro-story .lead {
            font-size: 1rem;
        }
    }
</style>
@endpush 
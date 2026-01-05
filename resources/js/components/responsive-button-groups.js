/**
 * Responsive Button Groups Enhancement
 * 
 * This script enhances the responsive behavior of button groups in interactive cards
 * by adding touch scrolling support and improved hover interactions.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all interactive card button groups
    const buttonGroups = document.querySelectorAll('.interactive-card-base .btn-group');
    
    buttonGroups.forEach(function(btnGroup) {
        // Add touch scrolling support for mobile devices
        let isScrolling = false;
        let startX = 0;
        let scrollLeft = 0;
        
        // Touch events for mobile scrolling
        btnGroup.addEventListener('touchstart', function(e) {
            isScrolling = true;
            startX = e.touches[0].pageX - btnGroup.offsetLeft;
            scrollLeft = btnGroup.scrollLeft;
        });
        
        btnGroup.addEventListener('touchmove', function(e) {
            if (!isScrolling) return;
            e.preventDefault();
            const x = e.touches[0].pageX - btnGroup.offsetLeft;
            const walk = (x - startX) * 2; // Scroll speed multiplier
            btnGroup.scrollLeft = scrollLeft - walk;
        });
        
        btnGroup.addEventListener('touchend', function() {
            isScrolling = false;
        });
        
        // Add visual feedback for scrollable content
        function updateScrollIndicators() {
            const isScrollable = btnGroup.scrollWidth > btnGroup.clientWidth;
            const isAtStart = btnGroup.scrollLeft <= 0;
            const isAtEnd = btnGroup.scrollLeft >= btnGroup.scrollWidth - btnGroup.clientWidth;
            
            // Add/remove scroll indicator classes
            btnGroup.classList.toggle('scrollable', isScrollable);
            btnGroup.classList.toggle('scroll-start', isAtStart);
            btnGroup.classList.toggle('scroll-end', isAtEnd);
        }
        
        // Update indicators on scroll
        btnGroup.addEventListener('scroll', updateScrollIndicators);
        
        // Initial check
        updateScrollIndicators();
        
        // Update on window resize
        window.addEventListener('resize', function() {
            setTimeout(updateScrollIndicators, 100);
        });
        
        // Add keyboard navigation support
        btnGroup.addEventListener('keydown', function(e) {
            const buttons = btnGroup.querySelectorAll('.btn');
            const currentIndex = Array.from(buttons).findIndex(btn => btn === document.activeElement);
            
            if (currentIndex === -1) return;
            
            let nextIndex = currentIndex;
            
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    nextIndex = Math.max(0, currentIndex - 1);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    nextIndex = Math.min(buttons.length - 1, currentIndex + 1);
                    break;
                case 'Home':
                    e.preventDefault();
                    nextIndex = 0;
                    break;
                case 'End':
                    e.preventDefault();
                    nextIndex = buttons.length - 1;
                    break;
            }
            
            if (nextIndex !== currentIndex) {
                buttons[nextIndex].focus();
                
                // Scroll the button into view if needed
                const nextButton = buttons[nextIndex];
                const buttonRect = nextButton.getBoundingClientRect();
                const groupRect = btnGroup.getBoundingClientRect();
                
                if (buttonRect.left < groupRect.left) {
                    btnGroup.scrollLeft -= groupRect.left - buttonRect.left + 10;
                } else if (buttonRect.right > groupRect.right) {
                    btnGroup.scrollLeft += buttonRect.right - groupRect.right + 10;
                }
            }
        });
    });
    
    // Add smooth scroll-to-center behavior for focused buttons
    document.addEventListener('focusin', function(e) {
        if (e.target.classList.contains('btn') && e.target.closest('.interactive-card-base .btn-group')) {
            const btnGroup = e.target.closest('.btn-group');
            const buttonRect = e.target.getBoundingClientRect();
            const groupRect = btnGroup.getBoundingClientRect();
            const groupCenter = groupRect.left + groupRect.width / 2;
            const buttonCenter = buttonRect.left + buttonRect.width / 2;
            
            // Only scroll if the button is significantly off-center
            if (Math.abs(buttonCenter - groupCenter) > groupRect.width / 4) {
                const scrollOffset = buttonCenter - groupCenter;
                btnGroup.scrollLeft += scrollOffset;
            }
        }
    });
});

// Add CSS classes for enhanced accessibility and scrollable content indicators
const style = document.createElement('style');
style.textContent = `
    /* Enhanced focus styles for better accessibility */
    .interactive-card-base .btn-group .btn:focus {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
        z-index: 3;
    }
    
    /* Smooth scrolling for all button groups */
    .interactive-card-base .btn-group {
        scroll-behavior: smooth;
    }
    
    /* Scrollable content hover effects */
    .interactive-card-base .btn-group.scrollable {
        cursor: ew-resize;
    }
    
    /* Buttons inside scrollable groups should have pointer cursor */
    .interactive-card-base .btn-group.scrollable .btn {
        cursor: pointer;
    }
    
    /* Nudge scrollable content slightly to the right on hover to show it's scrollable */
    /* Only nudge if at the start (scrollLeft is 0 or very close) */
    .interactive-card-base:hover .btn-group.scrollable.scroll-start {
        transform: translateX(2px);
        transition: transform 0.2s ease-in-out;
    }
    
    /* Don't nudge if already scrolled */
    .interactive-card-base:hover .btn-group.scrollable:not(.scroll-start) {
        transform: translateX(0);
        transition: transform 0.2s ease-in-out;
    }
    
    /* Subtle transition for hover state */
    .interactive-card-base {
        transition: all 0.2s ease-in-out;
    }
    
    .interactive-card-base:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
`;
document.head.appendChild(style); 
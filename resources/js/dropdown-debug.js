/**
 * This file is intentionally left empty.
 * We're keeping it to avoid breaking imports, but we no longer need the debugging code.
 */

// Debug script to check Bootstrap dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dropdown debug script loaded');
    
    // Check if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        console.log('✅ Bootstrap is available:', bootstrap);
        
        // Check if Dropdown class exists
        if (bootstrap.Dropdown) {
            console.log('✅ Bootstrap.Dropdown is available');
        } else {
            console.log('❌ Bootstrap.Dropdown is NOT available');
        }
    } else {
        console.log('❌ Bootstrap is NOT available');
    }
    
    // Find all tools buttons
    const toolsButtons = document.querySelectorAll('.tools-button .dropdown-toggle');
    console.log('Found tools buttons:', toolsButtons.length);
    
    toolsButtons.forEach((button, index) => {
        console.log(`Tools button ${index + 1}:`, button);
        
        // Check if it has the correct attributes
        console.log(`  - data-bs-toggle: ${button.getAttribute('data-bs-toggle')}`);
        console.log(`  - aria-expanded: ${button.getAttribute('aria-expanded')}`);
        
        // Check if it has a dropdown menu
        const dropdownMenu = button.nextElementSibling;
        if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
            console.log(`  - Has dropdown menu: ✅`);
        } else {
            console.log(`  - Has dropdown menu: ❌`);
        }
        
        // Try to manually initialize the dropdown
        try {
            if (bootstrap && bootstrap.Dropdown) {
                const dropdown = new bootstrap.Dropdown(button);
                console.log(`  - Manually initialized dropdown: ✅`);
                
                // Test if it responds to clicks
                button.addEventListener('click', function(e) {
                    console.log(`Tools button ${index + 1} clicked!`);
                });
            }
        } catch (error) {
            console.log(`  - Error initializing dropdown:`, error);
        }
    });
    
    // Check for any existing event listeners on dropdown toggles
    const allDropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    console.log('All dropdown toggles found:', allDropdownToggles.length);
    
    allDropdownToggles.forEach((toggle, index) => {
        console.log(`Dropdown toggle ${index + 1}:`, toggle);
        
        // Check if it's in a tools button
        const isToolsButton = toggle.closest('.tools-button');
        console.log(`  - Is tools button: ${isToolsButton ? '✅' : '❌'}`);
        
        // Check for conflicting data-bs-toggle attributes
        const toggleValue = toggle.getAttribute('data-bs-toggle');
        if (toggleValue !== 'dropdown') {
            console.log(`  - ⚠️ Conflicting data-bs-toggle: ${toggleValue}`);
        }
    });
}); 
// Check if Bootstrap is loaded
window.addEventListener('DOMContentLoaded', function() {
    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error caught:', e.message);
        console.error('Error source:', e.filename, 'line:', e.lineno);
        return false;
    });
}); 
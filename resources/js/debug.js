// Check if Bootstrap is loaded
window.addEventListener('DOMContentLoaded', function() {
    // Global error handler
    window.addEventListener('error', function(e) {
        console.error('Global error caught:', e.message);
        console.error('Error source:', e.filename, 'line:', e.lineno);
        console.error('Error details:', {
            message: e.message,
            filename: e.filename,
            lineno: e.lineno,
            colno: e.colno,
            error: e.error,
            stack: e.error ? e.error.stack : 'No stack available'
        });
        
        // Try to get the problematic line
        if (e.filename && e.lineno) {
            console.error('Attempting to identify problematic code...');
        }
        
        return false;
    });
    
    // Also catch unhandled promise rejections
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
        console.error('Promise rejection details:', {
            reason: e.reason,
            promise: e.promise
        });
    });
}); 
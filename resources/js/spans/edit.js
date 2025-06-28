$(document).ready(function() {
    // Only run this code on the spans edit page
    if (!window.location.pathname.includes('/spans/') || !window.location.pathname.includes('/edit')) {
        return;
    }

    // Handle type change to dynamically update metadata fields
    $('#type_id').change(function() {
        // Reload the page with the new type
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('type_id', $(this).val());
        window.location.href = currentUrl.toString();
    });
}); 
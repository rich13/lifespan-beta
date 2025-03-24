$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip({
        html: true
    });

    // Position timeline elements
    $('[data-left]').each(function() {
        const left = $(this).data('left');
        $(this).css('left', `${left}%`);
    });

    $('[data-width]').each(function() {
        const width = $(this).data('width');
        $(this).css('width', `${width}%`);
    });
}); 
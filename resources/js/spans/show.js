// Delete span confirmation
document.addEventListener('DOMContentLoaded', function() {
    const deleteBtn = document.getElementById('delete-span-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this span?')) {
                document.getElementById('delete-span-form').submit();
            }
        });
    }
}); 
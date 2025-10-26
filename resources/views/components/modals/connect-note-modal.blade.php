<div class="modal fade" id="connectNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-link-45deg me-2"></i>Connect Note to <span id="connectNoteSpanName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="connectNotesList" class="list-group">
                    <!-- Notes will be loaded here -->
                </div>
                <div id="noNotesMessage" class="alert alert-info" style="display: none;">
                    <i class="bi bi-info-circle me-2"></i>
                    No notes found that fall within this span's date range.
                </div>
                <div id="connectNoteStatus" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('connectNoteModal').addEventListener('show.bs.modal', async (e) => {
    const button = e.relatedTarget;
    const spanId = button.dataset.spanId;
    const spanName = button.dataset.spanName;
    const spanStartYear = parseInt(button.dataset.spanStartYear) || null;
    const spanStartMonth = parseInt(button.dataset.spanStartMonth) || null;
    const spanStartDay = parseInt(button.dataset.spanStartDay) || null;
    const spanEndYear = parseInt(button.dataset.spanEndYear) || null;
    const spanEndMonth = parseInt(button.dataset.spanEndMonth) || null;
    const spanEndDay = parseInt(button.dataset.spanEndDay) || null;

    document.getElementById('connectNoteSpanName').textContent = spanName;
    document.getElementById('connectNoteStatus').innerHTML = '';

    // Fetch notes that fall within this span's date range
    try {
        const response = await fetch(`/spans/api/notes-in-date-range`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                span_id: spanId,
                start_year: spanStartYear,
                start_month: spanStartMonth,
                start_day: spanStartDay,
                end_year: spanEndYear,
                end_month: spanEndMonth,
                end_day: spanEndDay
            })
        });

        // Read response as text first, then parse as JSON
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError, 'Response:', responseText);
            throw new Error(`Server error: ${responseText}`);
        }

        if (data.success && data.notes.length > 0) {
            document.getElementById('noNotesMessage').style.display = 'none';
            const notesList = document.getElementById('connectNotesList');
            notesList.innerHTML = '';

            data.notes.forEach(note => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action text-start';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <p class="mb-1 small">
                                <i class="bi bi-chat-square-text me-1"></i>
                                ${note.description ? note.description.substring(0, 100) : 'Untitled note'}
                            </p>
                            <small class="text-muted">
                                ${note.formatted_date} • ${note.author_name}
                            </small>
                        </div>
                        <span class="badge bg-secondary ms-2">Connect</span>
                    </div>
                `;
                item.onclick = () => connectNote(spanId, note.id);
                notesList.appendChild(item);
            });
        } else {
            document.getElementById('noNotesMessage').style.display = 'block';
            document.getElementById('connectNotesList').innerHTML = '';
        }
    } catch (error) {
        document.getElementById('connectNoteStatus').innerHTML = `
            <div class="alert alert-danger small mb-0">
                <i class="bi bi-exclamation-circle me-1"></i>
                Error loading notes: ${error.message}
            </div>
        `;
    }
});

async function connectNote(spanId, noteId) {
    const statusDiv = document.getElementById('connectNoteStatus');
    statusDiv.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Connecting...';

    try {
        const response = await fetch('/spans/api/connections/create-annotates', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                note_id: noteId,
                span_id: spanId
            })
        });

        const data = await response.json();

        if (data.success) {
            statusDiv.innerHTML = `
                <div class="alert alert-success small mb-0">
                    <i class="bi bi-check-circle me-1"></i>
                    Note connected successfully!
                </div>
            `;
            
            // Reload page after 1 second
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-danger small mb-0">
                    ${data.message}
                </div>
            `;
        }
    } catch (error) {
        statusDiv.innerHTML = `
            <div class="alert alert-danger small mb-0">
                Error: ${error.message}
            </div>
        `;
    }
}
</script>

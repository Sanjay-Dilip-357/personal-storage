/**
 * PERSONAL STORAGE — Notes Module Controller
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const app = window.PersonalStorage;
    if (!app) return;

    // ── Elements ─────────────────────────────
    const container    = document.getElementById('notes-container');
    const emptyState   = document.getElementById('notes-empty');
    const loadingState = document.getElementById('notes-loading');
    const searchInput  = document.getElementById('notes-search');
    const sidebarCount = document.getElementById('sidebar-note-count');

    const modal        = document.getElementById('note-modal');
    const modalTitle   = document.getElementById('modal-title');
    const noteTitle    = document.getElementById('note-title');
    const noteContent  = document.getElementById('note-content');
    const noteError    = document.getElementById('note-error');
    const btnSave      = document.getElementById('modal-save');
    const btnCancel    = document.getElementById('modal-cancel');
    const btnClose     = document.getElementById('modal-close');
    const btnNew       = document.getElementById('btn-new-note');
    const btnNewEmpty  = document.getElementById('btn-new-note-empty');

    let editingNoteId = null;
    let searchTimeout = null;

    // ── Load Notes ───────────────────────────
    const loadNotes = async (search = '') => {
        loadingState.style.display = 'block';
        container.style.display = 'none';
        emptyState.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'list');
            formData.append('search', search);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/notes.php', {
                method: 'POST',
                body: formData
            });

            loadingState.style.display = 'none';

            if (response.success && response.notes && response.notes.length > 0) {
                container.style.display = 'grid';
                renderNotes(response.notes);
                if (sidebarCount) sidebarCount.textContent = response.total;
            } else {
                container.style.display = 'none';
                emptyState.style.display = 'block';
                if (sidebarCount) sidebarCount.textContent = '0';
            }
        } catch (err) {
            loadingState.style.display = 'none';
            emptyState.style.display = 'block';
            app.toast.error(err.message || 'Failed to load notes.');
        }
    };

    // ── Render Notes ─────────────────────────
    const renderNotes = (notes) => {
        container.innerHTML = '';

        notes.forEach(note => {
            const card = document.createElement('div');
            card.className = 'note-card';
            card.dataset.noteId = note.id;

            const title = note.title || 'Untitled Note';
            const preview = note.preview || 'No content';
            const date = new Date(note.updated_at).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
            });

            card.innerHTML = `
                <div class="note-card-title">${app.escapeHtml(title)}</div>
                <div class="note-card-preview">${app.escapeHtml(preview)}</div>
                <div class="note-card-footer">
                    <span class="note-card-date">${app.escapeHtml(date)}</span>
                    <div class="note-card-actions">
                        <button class="note-edit-btn" data-id="${note.id}" title="Edit">✏️</button>
                        <button class="note-delete-btn" data-id="${note.id}" title="Delete">🗑️</button>
                    </div>
                </div>
            `;

            // Click card to edit (but not on action buttons)
            card.addEventListener('click', (e) => {
                if (e.target.closest('.note-card-actions')) return;
                openEditModal(note.id);
            });

            container.appendChild(card);
        });

        // Attach action listeners
        container.querySelectorAll('.note-edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openEditModal(parseInt(btn.dataset.id));
            });
        });

        container.querySelectorAll('.note-delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteNote(parseInt(btn.dataset.id));
            });
        });
    };

    // ── Modal Controls ───────────────────────
    const openModal = () => {
        modal.classList.add('active');
        noteError.textContent = '';
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.classList.remove('active');
        editingNoteId = null;
        noteTitle.value = '';
        noteContent.value = '';
        noteError.textContent = '';
        document.body.style.overflow = '';
    };

    const openCreateModal = () => {
        editingNoteId = null;
        modalTitle.textContent = 'New Note';
        noteTitle.value = '';
        noteContent.value = '';
        openModal();
        noteTitle.focus();
    };

    const openEditModal = async (noteId) => {
        try {
            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('note_id', noteId);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/notes.php', {
                method: 'POST',
                body: formData
            });

            if (response.success && response.note) {
                editingNoteId = noteId;
                modalTitle.textContent = 'Edit Note';
                noteTitle.value = response.note.title || '';
                noteContent.value = response.note.content || '';
                openModal();
                noteContent.focus();
            } else {
                app.toast.error(response.message || 'Note not found.');
            }
        } catch (err) {
            app.toast.error(err.message || 'Failed to load note.');
        }
    };

    // ── Save Note ────────────────────────────
    const saveNote = async () => {
        const title = noteTitle.value.trim();
        const content = noteContent.value.trim();

        if (content === '') {
            noteError.textContent = 'Note content cannot be empty.';
            return;
        }

        app.setLoading(btnSave, true);

        try {
            const formData = new FormData();
            formData.append('csrf_token', app.getCsrfToken());

            if (editingNoteId) {
                formData.append('action', 'update');
                formData.append('note_id', editingNoteId);
            } else {
                formData.append('action', 'create');
            }
            formData.append('title', title);
            formData.append('content', content);

            const response = await app.api('api/notes.php', {
                method: 'POST',
                body: formData
            });

            if (response.success) {
                app.toast.success(response.message);
                closeModal();
                loadNotes(searchInput ? searchInput.value : '');
            } else {
                noteError.textContent = response.message || 'Failed to save note.';
            }
        } catch (err) {
            noteError.textContent = err.message || 'An error occurred.';
        } finally {
            app.setLoading(btnSave, false);
        }
    };

    // ── Delete Note ──────────────────────────
    const deleteNote = (noteId) => {
        PersonalStorage.confirm(
            'Are you sure you want to delete this note? It will be moved to the Recycle Bin.',
            async () => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('note_id', noteId);
                    formData.append('csrf_token', app.getCsrfToken());

                    const response = await app.api('api/notes.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        app.toast.success(response.message);
                        loadNotes(searchInput ? searchInput.value : '');
                    } else {
                        app.toast.error(response.message || 'Failed to delete note.');
                    }
                } catch (err) {
                    app.toast.error(err.message || 'An error occurred.');
                }
            }
        );
    };

    // ── Search (Debounced) ───────────────────
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadNotes(searchInput.value.trim());
            }, 350);
        });
    }

    // ── Event Listeners ──────────────────────
    if (btnNew) btnNew.addEventListener('click', openCreateModal);
    if (btnNewEmpty) btnNewEmpty.addEventListener('click', openCreateModal);
    if (btnSave) btnSave.addEventListener('click', saveNote);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);

    // Close modal on overlay click
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // ── Initial Load ─────────────────────────
    loadNotes();
});
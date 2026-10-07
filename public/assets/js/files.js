/**
 * PERSONAL STORAGE — Combined File Manager Controller
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const app = window.PersonalStorage;
    if (!app) return;

    // Elements
    const container     = document.getElementById('files-container');
    const emptyState    = document.getElementById('files-empty');
    const loadingState  = document.getElementById('files-loading');
    const searchInput   = document.getElementById('files-search');
    const uploadInput   = document.getElementById('file-upload-input');
    const dropZone      = document.getElementById('drop-zone');
    const uploadProgress = document.getElementById('upload-progress');
    const uploadFill    = document.getElementById('upload-fill');
    const uploadFilename = document.getElementById('upload-filename');
    const uploadPercent = document.getElementById('upload-percent');

    const renameModal   = document.getElementById('rename-modal');
    const renameInput   = document.getElementById('rename-input');
    const renameError   = document.getElementById('rename-error');
    const renameSave    = document.getElementById('rename-save');
    const renameCancel  = document.getElementById('rename-cancel');
    const renameClose   = document.getElementById('rename-close');

    const activeCategory = window.PS_ACTIVE_CATEGORY || '';
    let currentView = 'grid';
    let searchTimeout = null;
    let renamingFileId = null;
    let renamingItemType = 'file';

    // ── Load Items ───────────────────────────
    const loadFiles = async (search = '') => {
        loadingState.style.display = 'block';
        container.style.display = 'none';
        emptyState.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'list');
            formData.append('search', search);
            formData.append('category', activeCategory);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/files.php', {
                method: 'POST',
                body: formData
            });

            loadingState.style.display = 'none';

            if (response.success && response.files && response.files.length > 0) {
                container.style.display = '';
                renderFiles(response.files);
            } else {
                container.style.display = 'none';
                emptyState.style.display = 'block';
            }
        } catch (err) {
            loadingState.style.display = 'none';
            emptyState.style.display = 'block';
            app.toast.error(err.message || 'Failed to load assets.');
        }
    };

    // ── Render Items ─────────────────────────
    const renderFiles = (files) => {
        container.innerHTML = '';

        files.forEach(file => {
            const card = document.createElement('div');
            card.className = 'file-card';
            card.dataset.fileId = file.id;
            card.dataset.itemType = file.item_type;

            // Use memoized visual layout mapping
            const icon = file.item_type === 'note' ? '📝' : (file.category === 'document' ? '📄' : (file.category === 'image' ? '🖼️' : (file.category === 'video' ? '🎥' : '📦')));
            const sizeLabel = file.item_type === 'note' ? `${file.size_bytes} chars` : app.formatBytes(parseInt(file.size_bytes));
            const dateLabel = new Date(file.created_at).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
            });

            card.innerHTML = `
                <div class="file-card-icon file-card-icon--${app.escapeHtml(file.category)}">${icon}</div>
                <div class="file-card-name" title="${app.escapeHtml(file.original_name)}">${app.escapeHtml(file.original_name)}</div>
                <div class="file-card-meta">${app.escapeHtml(sizeLabel)} • ${app.escapeHtml(dateLabel)}</div>
                <div class="file-card-actions">
                    <a href="download.php?id=${file.id}&type=${file.item_type}" class="btn btn--ghost btn--sm" title="Download">⬇️</a>
                    <button class="file-rename-btn" data-id="${file.id}" data-type="${file.item_type}" data-name="${app.escapeHtml(file.original_name)}" title="Rename">✏️</button>
                    <button class="file-delete-btn" data-id="${file.id}" data-type="${file.item_type}" title="Delete">🗑️</button>
                </div>
            `;

            container.appendChild(card);
        });

        // Attach action click listeners
        container.querySelectorAll('.file-rename-btn').forEach(btn => {
            btn.addEventListener('click', () => openRenameModal(parseInt(btn.dataset.id), btn.dataset.type, btn.dataset.name));
        });

        container.querySelectorAll('.file-delete-btn').forEach(btn => {
            btn.addEventListener('click', () => deleteItem(parseInt(btn.dataset.id), btn.dataset.type));
        });
    };

    // ── Upload File ──────────────────────────
    const uploadFile = async (file) => {
        if (!file) return;

        uploadProgress.style.display = 'block';
        uploadFilename.textContent = file.name;
        uploadFill.style.width = '0%';
        uploadPercent.textContent = '0%';

        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('file', file);
        formData.append('csrf_token', app.getCsrfToken());

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    uploadFill.style.width = pct + '%';
                    uploadPercent.textContent = pct + '%';
                }
            });

            const response = await new Promise((resolve, reject) => {
                xhr.onload = () => {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch {
                        reject({ message: 'Invalid server response.' });
                    }
                };
                xhr.onerror = () => reject({ message: 'Upload failed.' });

                const path = window.location.pathname;
                const publicIndex = path.indexOf('/public');
                let url = '/api/files.php';
                if (publicIndex !== -1) {
                    url = path.substring(0, publicIndex + 7) + '/api/files.php';
                }
                xhr.open('POST', url);
                xhr.setRequestHeader('X-CSRF-TOKEN', app.getCsrfToken());
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
            });

            uploadProgress.style.display = 'none';

            if (response.success) {
                app.toast.success(response.message);
                loadFiles(searchInput ? searchInput.value : '');
            } else {
                app.toast.error(response.message || 'Upload failed.');
            }
        } catch (err) {
            uploadProgress.style.display = 'none';
            app.toast.error(err.message || 'Upload failed.');
        }
    };

    if (uploadInput) {
        uploadInput.addEventListener('change', () => {
            if (uploadInput.files.length > 0) {
                uploadFile(uploadInput.files[0]);
                uploadInput.value = '';
            }
        });
    }

    // ── Drag & Drop ──────────────────────────
    const mainContent = document.querySelector('.content-body');
    if (mainContent) {
        mainContent.addEventListener('dragenter', (e) => {
            e.preventDefault();
            dropZone.style.display = 'block';
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
            dropZone.style.display = 'none';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            dropZone.style.display = 'none';
            if (e.dataTransfer.files.length > 0) {
                uploadFile(e.dataTransfer.files[0]);
            }
        });
    }

    // ── Delete Item ──────────────────────────
    const deleteItem = (itemId, itemType) => {
        const itemLabel = itemType === 'note' ? 'note' : 'file';
        app.confirm(
            `Are you sure you want to delete this ${itemLabel}? It will be moved to the Recycle Bin.`,
            async () => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('file_id', itemId);
                    formData.append('item_type', itemType);
                    formData.append('csrf_token', app.getCsrfToken());

                    const response = await app.api('api/files.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        app.toast.success(response.message);
                        loadFiles(searchInput ? searchInput.value : '');
                    } else {
                        app.toast.error(response.message);
                    }
                } catch (err) {
                    app.toast.error(err.message || 'Delete failed.');
                }
            }
        );
    };

    // ── Rename Modal ─────────────────────────
    const openRenameModal = (itemId, itemType, currentName) => {
        renamingFileId = itemId;
        renamingItemType = itemType;
        const nameWithoutExt = itemType === 'note' ? currentName : currentName.replace(/\.[^.]+$/, '');
        renameInput.value = nameWithoutExt;
        renameError.textContent = '';
        renameModal.classList.add('active');
        renameInput.focus();
        renameInput.select();
    };

    const closeRenameModal = () => {
        renameModal.classList.remove('active');
        renamingFileId = null;
    };

    const saveRename = async () => {
        const newName = renameInput.value.trim();
        if (!newName) {
            renameError.textContent = 'Name cannot be empty.';
            return;
        }

        app.setLoading(renameSave, true);

        try {
            const formData = new FormData();
            formData.append('action', 'rename');
            formData.append('file_id', renamingFileId);
            formData.append('item_type', renamingItemType);
            formData.append('new_name', newName);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/files.php', {
                method: 'POST',
                body: formData
            });

            if (response.success) {
                app.toast.success(response.message);
                closeRenameModal();
                loadFiles(searchInput ? searchInput.value : '');
            } else {
                renameError.textContent = response.message;
            }
        } catch (err) {
            renameError.textContent = err.message || 'Rename failed.';
        } finally {
            app.setLoading(renameSave, false);
        }
    };

    if (renameSave) renameSave.addEventListener('click', saveRename);
    if (renameCancel) renameCancel.addEventListener('click', closeRenameModal);
    if (renameClose) renameClose.addEventListener('click', closeRenameModal);
    if (renameModal) {
        renameModal.addEventListener('click', (e) => {
            if (e.target === renameModal) closeRenameModal();
        });
    }

    // ── View Toggle ──────────────────────────
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentView = btn.dataset.view;

            if (currentView === 'list') {
                container.classList.add('list-view');
            } else {
                container.classList.remove('list-view');
            }
        });
    });

    // ── Search ───────────────────────────────
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadFiles(searchInput.value.trim());
            }, 350);
        });
    }

    // ── Initial Load ─────────────────────────
    loadFiles();
});
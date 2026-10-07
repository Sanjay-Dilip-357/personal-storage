/**
 * PERSONAL STORAGE — Recycle Bin Client Controller
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const app = window.PersonalStorage;
    if (!app) return;

    // Elements
    const tableWrapper = document.getElementById('recycle-table-wrapper');
    const tbody        = document.getElementById('recycle-tbody');
    const emptyState   = document.getElementById('recycle-empty');
    const loadingState = document.getElementById('recycle-loading');
    const sidebarCount = document.getElementById('sidebar-recycle-count');
    const tabs         = document.querySelectorAll('.recycle-tab-btn');

    let currentType = 'file'; // 'file' or 'note'

    // ── Load Bin Content ──────────────────────
    const loadBin = async () => {
        loadingState.style.display = 'block';
        tableWrapper.style.display = 'none';
        emptyState.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('action', 'list');
            formData.append('type', currentType);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/recycle-bin.php', {
                method: 'POST',
                body: formData
            });

            loadingState.style.display = 'none';

            if (response.success && response.items && response.items.length > 0) {
                tableWrapper.style.display = 'block';
                renderItems(response.items);
            } else {
                emptyState.style.display = 'block';
            }
        } catch (err) {
            loadingState.style.display = 'none';
            emptyState.style.display = 'block';
            app.toast.error(err.message || 'Failed to scan Recycle Bin.');
        }
    };

    // ── Render Row Items ──────────────────────
    const renderItems = (items) => {
        tbody.innerHTML = '';

        items.forEach(item => {
            const tr = document.createElement('tr');

            const icon = currentType === 'file' ? '📁' : '📝';
            const sizeFormatted = currentType === 'file' 
                ? app.formatBytes(parseInt(item.size)) 
                : item.size + ' chars';

            const deletedDate = new Date(item.deleted_at).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            // Calculate countdown threshold remaining
            const expiryTime = new Date(item.deletion_expiry_at).getTime();
            const now = new Date().getTime();
            const hrsRemaining = Math.max(0, Math.round((expiryTime - now) / 3600000));
            
            let countdownHtml = '';
            if (hrsRemaining > 24) {
                const days = Math.round(hrsRemaining / 24);
                countdownHtml = `<span class="countdown-badge countdown--safe">⏳ ${days} days left</span>`;
            } else {
                countdownHtml = `<span class="countdown-badge">⚠️ ${hrsRemaining} hours left</span>`;
            }

            tr.innerHTML = `
                <td>
                    <div class="item-name-cell">
                        <span class="item-icon">${icon}</span>
                        <span>${app.escapeHtml(item.name)}</span>
                    </div>
                </td>
                <td>${sizeFormatted}</td>
                <td>${deletedDate}</td>
                <td>${countdownHtml}</td>
                <td style="text-align:right;">
                    <div class="recycle-actions">
                        <button class="recycle-btn recycle-btn--restore" data-id="${item.id}">Restore</button>
                        <button class="recycle-btn recycle-btn--purge" data-id="${item.id}">Purge</button>
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });

        // Event listeners for actions
        tbody.querySelectorAll('.recycle-btn--restore').forEach(btn => {
            btn.addEventListener('click', () => restoreItem(parseInt(btn.dataset.id)));
        });

        tbody.querySelectorAll('.recycle-btn--purge').forEach(btn => {
            btn.addEventListener('click', () => purgeItem(parseInt(btn.dataset.id)));
        });
    };

    // ── Restore Item ──────────────────────────
    const restoreItem = async (itemId) => {
        try {
            const formData = new FormData();
            formData.append('action', 'restore');
            formData.append('type', currentType);
            formData.append('item_id', itemId);
            formData.append('csrf_token', app.getCsrfToken());

            const response = await app.api('api/recycle-bin.php', {
                method: 'POST',
                body: formData
            });

            if (response.success) {
                app.toast.success(response.message);
                loadBin();
                updateCounts();
            } else {
                app.toast.error(response.message);
            }
        } catch (err) {
            app.toast.error(err.message || 'Restoration failed.');
        }
    };

    // ── Permanent Purge Item ──────────────────
    const purgeItem = (itemId) => {
        app.confirm(
            'Warning: This action cannot be undone. This item will be permanently erased from storage disk and databases.',
            async () => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'permanent_delete');
                    formData.append('type', currentType);
                    formData.append('item_id', itemId);
                    formData.append('csrf_token', app.getCsrfToken());

                    const response = await app.api('api/recycle-bin.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        app.toast.success(response.message);
                        loadBin();
                        updateCounts();
                    } else {
                        app.toast.error(response.message);
                    }
                } catch (err) {
                    app.toast.error(err.message || 'Erase failed.');
                }
            }
        );
    };

    // ── Tab Switch Control ─────────────────────
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentType = tab.dataset.type;
            loadBin();
        });
    });

    // ── Sync Counts ──────────────────────────
    const updateCounts = async () => {
        try {
            // Re-fetch storage metrics to update sidebar badges cleanly
            const response = await app.api('api/files.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'list', csrf_token: app.getCsrfToken() })
            });
            if (response.success) {
                // Approximate counts update dynamically without full-page reloads
                const count = parseInt(sidebarCount.textContent || '0');
                if (count > 0 && sidebarCount) {
                    sidebarCount.textContent = count - 1;
                    if (count - 1 === 0) sidebarCount.style.display = 'none';
                }
            }
        } catch (e) {}
    };

    // Initial Load
    loadBin();
});
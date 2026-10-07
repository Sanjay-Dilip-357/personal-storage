/**
 * ==============================================
 * PERSONAL STORAGE — Master JavaScript
 * Complete Utilities: API, Toast, Confirm Modal,
 * Formatters, Loading States & CSRF Helper.
 * ==============================================
 */

'use strict';

window.PersonalStorage = (() => {

    // ── CSRF Token Helper ────────────────────
    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // ── Dynamic Base API URL Detection ──────
    const getApiUrl = (endpoint) => {
        const cleanEndpoint = endpoint.replace(/^\/+/, '');
        const path = window.location.pathname;
        const publicIndex = path.indexOf('/public');
        
        if (publicIndex !== -1) {
            const basePath = path.substring(0, publicIndex + 7); // includes '/public'
            return `${basePath}/${cleanEndpoint}`;
        }
        return `/${cleanEndpoint}`;
    };

    // ── API Helper ───────────────────────────
    const api = async (url, options = {}) => {
        const fullUrl = url.startsWith('http') ? url : getApiUrl(url);

        const defaults = {
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        };

        if (options.headers) {
            defaults.headers = { ...defaults.headers, ...options.headers };
        }

        if (!(options.body instanceof FormData)) {
            if (options.body && typeof options.body === 'object') {
                defaults.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
        }

        const config = { 
            ...defaults, 
            ...options, 
            headers: { ...defaults.headers, ...(options.headers || {}) } 
        };

        try {
            const response = await fetch(fullUrl, config);
            const contentType = response.headers.get('content-type') || '';

            let data;
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                throw {
                    status: response.status,
                    message: (typeof data === 'object' && data.message) ? data.message : 'Request failed',
                    data: data,
                };
            }

            return data;
        } catch (error) {
            if (error.status !== undefined) {
                throw error;
            }
            throw {
                status: 0,
                message: error.message || 'Network error. Please check your connection.',
                data: null,
            };
        }
    };

    // ── HTML Escaper ─────────────────────────
    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    };

    // ── Format File Size ─────────────────────
    const formatBytes = (bytes, precision = 2) => {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const index = Math.min(i, units.length - 1);
        return parseFloat((bytes / Math.pow(1024, index)).toFixed(precision)) + ' ' + units[index];
    };

    // ── Toast Notification System ────────────
    const toast = (() => {
        let container = null;

        const getContainer = () => {
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.setAttribute('role', 'alert');
                container.setAttribute('aria-live', 'polite');
                container.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    max-width: 400px;
                    width: calc(100% - 40px);
                    pointer-events: none;
                `;
                document.body.appendChild(container);
            }
            return container;
        };

        const iconMap = {
            success: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" fill="currentColor"/></svg>`,
            error:   `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" fill="currentColor"/></svg>`,
            warning: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" fill="currentColor"/></svg>`,
            info:    `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" fill="currentColor"/></svg>`,
        };

        const colorMap = {
            success: { bg: 'rgba(34, 197, 94, 0.15)', border: 'rgba(34, 197, 94, 0.4)', text: '#4ade80' },
            error:   { bg: 'rgba(239, 68, 68, 0.15)', border: 'rgba(239, 68, 68, 0.4)', text: '#f87171' },
            warning: { bg: 'rgba(245, 158, 11, 0.15)', border: 'rgba(245, 158, 11, 0.4)', text: '#fbbf24' },
            info:    { bg: 'rgba(14, 165, 233, 0.15)', border: 'rgba(14, 165, 233, 0.4)', text: '#38bdf8' },
        };

        const show = (message, type = 'info', duration = 4000) => {
            const cont = getContainer();
            const colors = colorMap[type] || colorMap.info;
            const icon = iconMap[type] || iconMap.info;

            const el = document.createElement('div');
            el.style.cssText = `
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 14px 18px;
                background: ${colors.bg};
                border: 1px solid ${colors.border};
                border-radius: 12px;
                backdrop-filter: blur(20px);
                color: ${colors.text};
                font-size: 0.875rem;
                font-weight: 500;
                pointer-events: auto;
                opacity: 0;
                transform: translateX(20px);
                transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 10px 25px rgba(0,0,0,0.5);
                cursor: pointer;
            `;

            el.innerHTML = `
                <span style="flex-shrink:0; margin-top:1px;">${icon}</span>
                <span style="flex:1;">${escapeHtml(message)}</span>
            `;

            el.addEventListener('click', () => dismiss(el));
            cont.appendChild(el);

            requestAnimationFrame(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateX(0)';
            });

            if (duration > 0) {
                setTimeout(() => dismiss(el), duration);
            }

            return el;
        };

        const dismiss = (el) => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            setTimeout(() => el.remove(), 300);
        };

        return {
            success: (msg, dur) => show(msg, 'success', dur),
            error:   (msg, dur) => show(msg, 'error', dur),
            warning: (msg, dur) => show(msg, 'warning', dur),
            info:    (msg, dur) => show(msg, 'info', dur),
        };
    })();

    // ── Custom Confirmation Modal ────────────
    const confirm = (message, onConfirm, onCancel = null) => {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            z-index: 9998;
            padding: 20px;
            animation: modalFadeIn 200ms ease-out;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: #111827;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 16px;
            padding: 28px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5), 0 0 40px rgba(14, 165, 233, 0.08);
            animation: modalScaleUp 200ms ease-out;
        `;

        modal.innerHTML = `
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <div style="width:40px; height:40px; border-radius:50%; background:rgba(239, 68, 68, 0.15); display:flex; align-items:center; justify-content:center; color:#f87171; font-size:1.2rem; flex-shrink:0;">
                    ⚠️
                </div>
                <h3 style="color:#f1f5f9; font-size:1.1rem; font-weight:600; margin:0;">Confirmation Required</h3>
            </div>
            <p style="color:#94a3b8; font-size:0.9rem; margin-bottom:24px; line-height:1.5;">${escapeHtml(message)}</p>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button class="modal-btn-cancel" style="
                    padding:10px 18px; border-radius:10px;
                    background:#1e293b; color:#94a3b8; border:1px solid rgba(148,163,184,0.15);
                    font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 150ms;
                ">Cancel</button>
                <button class="modal-btn-confirm" style="
                    padding:10px 18px; border-radius:10px;
                    background:linear-gradient(135deg, #ef4444, #dc2626); color:white; border:none;
                    font-size:0.875rem; font-weight:600; cursor:pointer; transition:all 150ms;
                    box-shadow: 0 4px 12px rgba(239,68,68,0.3);
                ">Confirm</button>
            </div>
        `;

        const close = () => {
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 150);
        };

        modal.querySelector('.modal-btn-cancel').addEventListener('click', () => {
            close();
            if (typeof onCancel === 'function') onCancel();
        });

        modal.querySelector('.modal-btn-confirm').addEventListener('click', () => {
            close();
            if (typeof onConfirm === 'function') onConfirm();
        });

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close();
                if (typeof onCancel === 'function') onCancel();
            }
        });

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        modal.querySelector('.modal-btn-confirm').focus();
    };

    // ── Loading Button Helper ────────────────
    const setLoading = (button, loading = true) => {
        if (!button) return;
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" style="animation:spin 1s linear infinite; display:inline-block; margin-right:8px; vertical-align:middle;">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/>
                </svg>
                Processing...
            `;
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || 'Submit';
        }
    };

    // Keyframe animations injection
    if (!document.getElementById('ps-keyframes')) {
        const style = document.createElement('style');
        style.id = 'ps-keyframes';
        style.textContent = `
            @keyframes spin { to { transform: rotate(360deg); } }
            @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes modalScaleUp { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        `;
        document.head.appendChild(style);
    }

    return {
        api,
        toast,
        confirm,
        escapeHtml,
        formatBytes,
        getCsrfToken,
        setLoading,
    };
})();
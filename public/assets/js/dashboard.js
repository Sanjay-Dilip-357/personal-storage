/**
 * PERSONAL STORAGE — Dashboard & Service Worker Controller
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Responsive Sidebar Toggles ─────────
    const sidebar = document.getElementById('sidebar');
    const sidebarOpenBtn = document.getElementById('sidebar-open');
    const sidebarCloseBtn = document.getElementById('sidebar-close');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    const openSidebar = () => {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    const closeSidebar = () => {
        if (sidebar && sidebarOverlay) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    if (sidebarOpenBtn) sidebarOpenBtn.addEventListener('click', openSidebar);
    if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);


    // ── 2. Service Worker Registrar (PWA) ─────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            // Register service-worker.js at the base scope
            navigator.serviceWorker.register('/personal-storage/public/service-worker.js')
                .then((registration) => {
                    console.log('PWA Service Worker registered cleanly scope:', registration.scope);
                })
                .catch((error) => {
                    console.warn('PWA Service Worker registration omitted:', error);
                });
        });
    }

});
/**
 * PERSONAL STORAGE — User Settings Controller
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const app = window.PersonalStorage;
    if (!app) return;

    // Elements
    const formProfile    = document.getElementById('form-profile');
    const btnProfile     = document.getElementById('btn-save-profile');
    const formPassword   = document.getElementById('form-password');
    const btnPassword    = document.getElementById('btn-save-password');

    const modalDelete    = document.getElementById('delete-account-modal');
    const btnOpenDelete  = document.getElementById('btn-open-delete-modal');
    const btnCloseDelete = document.getElementById('delete-modal-close');
    const btnCancelDelete= document.getElementById('delete-modal-cancel');
    const btnConfirmDelete = document.getElementById('btn-confirm-delete');
    const deletePasswordInput = document.getElementById('delete-password-input');
    const deleteError    = document.getElementById('delete-error');

    // ── 1. Save Profile ──────────────────────
    if (formProfile) {
        formProfile.addEventListener('submit', async (e) => {
            e.preventDefault();
            app.setLoading(btnProfile, true);

            const formData = new FormData(formProfile);
            formData.append('action', 'update_profile');
            formData.append('csrf_token', app.getCsrfToken());

            try {
                const response = await app.api('api/settings.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    app.toast.success(response.message);
                    // Update header and sidebar display names
                    const sidebarName = document.getElementById('sidebar-username');
                    const sidebarAvatar = document.getElementById('sidebar-avatar');
                    if (sidebarName && response.name) sidebarName.textContent = response.name;
                    if (sidebarAvatar && response.name) sidebarAvatar.textContent = response.name.charAt(0).toUpperCase();
                } else {
                    app.toast.error(response.message || 'Failed to update profile.');
                }
            } catch (err) {
                app.toast.error(err.message || 'Update failed.');
            } finally {
                app.setLoading(btnProfile, false);
            }
        });
    }

    // ── 2. Change Password ───────────────────
    if (formPassword) {
        formPassword.addEventListener('submit', async (e) => {
            e.preventDefault();
            app.setLoading(btnPassword, true);

            const formData = new FormData(formPassword);
            formData.append('action', 'change_password');
            formData.append('csrf_token', app.getCsrfToken());

            try {
                const response = await app.api('api/settings.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    app.toast.success(response.message);
                    formPassword.reset();
                } else {
                    app.toast.error(response.message || 'Password update failed.');
                }
            } catch (err) {
                app.toast.error(err.message || 'Password update failed.');
            } finally {
                app.setLoading(btnPassword, false);
            }
        });
    }

    // ── 3. Delete Account Modal ──────────────
    const openDeleteModal = () => {
        if (modalDelete) {
            modalDelete.classList.add('active');
            deletePasswordInput.value = '';
            deleteError.textContent = '';
            deletePasswordInput.focus();
        }
    };

    const closeDeleteModal = () => {
        if (modalDelete) {
            modalDelete.classList.remove('active');
            deletePasswordInput.value = '';
            deleteError.textContent = '';
        }
    };

    if (btnOpenDelete) btnOpenDelete.addEventListener('click', openDeleteModal);
    if (btnCloseDelete) btnCloseDelete.addEventListener('click', closeDeleteModal);
    if (btnCancelDelete) btnCancelDelete.addEventListener('click', closeDeleteModal);

    if (modalDelete) {
        modalDelete.addEventListener('click', (e) => {
            if (e.target === modalDelete) closeDeleteModal();
        });
    }

    if (btnConfirmDelete) {
        btnConfirmDelete.addEventListener('click', async () => {
            const password = deletePasswordInput.value.trim();

            if (!password) {
                deleteError.textContent = 'Please enter your password.';
                return;
            }

            app.setLoading(btnConfirmDelete, true);
            deleteError.textContent = '';

            try {
                const formData = new FormData();
                formData.append('action', 'delete_account');
                formData.append('password', password);
                formData.append('csrf_token', app.getCsrfToken());

                const response = await app.api('api/settings.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.success) {
                    app.toast.success(response.message);
                    setTimeout(() => {
                        window.location.href = response.redirect || 'auth.php';
                    }, 1200);
                } else {
                    deleteError.textContent = response.message || 'Deletion failed.';
                }
            } catch (err) {
                deleteError.textContent = err.message || 'Deletion failed.';
            } finally {
                app.setLoading(btnConfirmDelete, false);
            }
        });
    }
});
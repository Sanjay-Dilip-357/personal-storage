/**
 * PERSONAL STORAGE — Authentication Client Controller
 */

'use strict';

(function initAuth() {
    const run = () => {
        const app = window.PersonalStorage;
        if (!app) {
            console.error('PersonalStorage core app.js is not loaded.');
            return;
        }

        const authSubtitle = document.getElementById('auth-subtitle');
        const passwordToggles = document.querySelectorAll('.password-toggle');
        const views = document.querySelectorAll('.auth-view');

        const viewMeta = {
            'login': 'Sign in to your account',
            'register': 'Create your secure account',
            'otp': 'Verify your email address',
            'forgot': 'Recover your password',
            'reset-otp': 'Verify your password reset',
            'new-password': 'Set your new secure password'
        };

        // ── View Switching ──────────────────
        window.switchAuthView = (targetViewId, extraData = {}) => {
            views.forEach(v => v.classList.remove('active'));

            const targetView = document.getElementById(`view-${targetViewId}`);
            if (targetView) {
                targetView.classList.add('active');
                if (authSubtitle) {
                    authSubtitle.textContent = viewMeta[targetViewId] || 'Private Storage Space';
                }

                targetView.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                targetView.querySelectorAll('.form-input').forEach(el => el.classList.remove('form-input--error'));

                if (targetViewId === 'otp') {
                    setupOtpFields('otp-inputs', 'otp-value');
                    if (extraData.email) {
                        const emailDisplay = document.getElementById('otp-email-display');
                        if (emailDisplay) emailDisplay.textContent = extraData.email;
                    }
                    if (extraData.userId) {
                        const uidEl = document.getElementById('otp-user-id');
                        if (uidEl) uidEl.value = extraData.userId;
                    }
                    startResendTimer();
                }

                if (targetViewId === 'reset-otp') {
                    setupOtpFields('reset-otp-inputs', 'reset-otp-value');
                    if (extraData.userId) {
                        const rUidEl = document.getElementById('reset-otp-user-id');
                        if (rUidEl) rUidEl.value = extraData.userId;
                    }
                }

                const firstInput = targetView.querySelector('input:not([type="hidden"])');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 50);
                }
            } else {
                console.warn(`Target view "view-${targetViewId}" not found.`);
            }
        };

        // Attach clicks to all [data-view] links
        document.querySelectorAll('[data-view]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                window.switchAuthView(trigger.dataset.view);
            });
        });

        // ── Password Toggles ─────────────────
        passwordToggles.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const inputId = btn.dataset.target;
                const input = document.getElementById(inputId);
                const openEye = btn.querySelector('.eye-open');
                const closedEye = btn.querySelector('.eye-closed');

                if (input && input.type === 'password') {
                    input.type = 'text';
                    if (openEye) openEye.style.display = 'none';
                    if (closedEye) closedEye.style.display = 'block';
                } else if (input) {
                    input.type = 'password';
                    if (openEye) openEye.style.display = 'block';
                    if (closedEye) closedEye.style.display = 'none';
                }
            });
        });

        // ── Password Strength Bar ────────────
        const regPassword = document.getElementById('reg-password');
        const strengthFill = document.getElementById('strength-fill');
        const strengthText = document.getElementById('strength-text');

        if (regPassword && strengthFill && strengthText) {
            regPassword.addEventListener('input', () => {
                const val = regPassword.value;
                let score = 0;

                if (val.length >= 8) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;

                let color = '#ef4444';
                let label = 'Weak';
                let width = '25%';

                if (val.length === 0) {
                    width = '0%';
                    label = '';
                } else if (score === 2) {
                    color = '#f59e0b';
                    label = 'Fair';
                    width = '50%';
                } else if (score === 3) {
                    color = '#0ea5e9';
                    label = 'Good';
                    width = '75%';
                } else if (score === 4) {
                    color = '#22c55e';
                    label = 'Strong';
                    width = '100%';
                }

                strengthFill.style.width = width;
                strengthFill.style.backgroundColor = color;
                strengthText.textContent = label;
                strengthText.style.color = color;
            });
        }

        // ── OTP Boxes ────────────────────────
        const setupOtpFields = (containerId, outputId) => {
            const container = document.getElementById(containerId);
            const output = document.getElementById(outputId);
            if (!container || !output) return;

            const inputs = container.querySelectorAll('.otp-box');
            inputs.forEach(input => input.value = '');
            output.value = '';

            inputs.forEach((input, index) => {
                input.oninput = () => {
                    input.value = input.value.replace(/[^0-9]/g, '');
                    if (input.value && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                    collect();
                };

                input.onkeydown = (e) => {
                    if (e.key === 'Backspace' && !input.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                };
            });

            const collect = () => {
                let val = '';
                inputs.forEach(input => val += input.value);
                output.value = val;
            };
        };

        // ── OTP Resend Timer ─────────────────
        let countdownInterval;
        const startResendTimer = () => {
            const timerContainer = document.getElementById('resend-timer');
            const countdownEl = document.getElementById('resend-countdown');
            const resendBtn = document.getElementById('btn-resend-otp');

            if (!timerContainer || !countdownEl || !resendBtn) return;

            clearInterval(countdownInterval);
            timerContainer.style.display = 'block';
            resendBtn.style.display = 'none';

            let count = 60;
            countdownEl.textContent = count;

            countdownInterval = setInterval(() => {
                count--;
                countdownEl.textContent = count;

                if (count <= 0) {
                    clearInterval(countdownInterval);
                    timerContainer.style.display = 'none';
                    resendBtn.style.display = 'inline-block';
                }
            }, 1000);
        };

        // ── Async Submissions ────────────────
        const handleFormSubmit = (form, submitBtn, successCallback) => {
            if (!form || !submitBtn) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                form.querySelectorAll('.form-error').forEach(el => el.textContent = '');
                form.querySelectorAll('.form-input').forEach(el => el.classList.remove('form-input--error'));

                app.setLoading(submitBtn, true);
                const formData = new FormData(form);

                try {
                    const response = await app.api('api/auth.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.success) {
                        successCallback(response);
                    } else {
                        app.toast.error(response.message || 'Validation failed.');
                    }
                } catch (error) {
                    if (error.data && error.data.errors) {
                        for (const [field, messages] of Object.entries(error.data.errors)) {
                            const prefix = form.id.replace('form-', '');
                            const errEl = form.querySelector(`#${prefix}-${field}-error`) || form.querySelector(`#reg-${field}-error`) || form.querySelector(`#login-${field}-error`);
                            const inputEl = form.querySelector(`[name="${field}"]`);

                            if (errEl) errEl.textContent = messages[0];
                            if (inputEl) inputEl.classList.add('form-input--error');
                        }
                        app.toast.error('Please fix the highlighted fields.');
                    } else {
                        app.toast.error(error.message || 'An error occurred.');
                    }
                } finally {
                    app.setLoading(submitBtn, false);
                }
            });
        };

        // Attach Forms
        handleFormSubmit(
            document.getElementById('form-login'),
            document.getElementById('btn-login'),
            (res) => {
                app.toast.success(res.message);
                setTimeout(() => window.location.href = res.redirect || 'dashboard.php', 600);
            }
        );

        handleFormSubmit(
            document.getElementById('form-register'),
            document.getElementById('btn-register'),
            (res) => {
                app.toast.success(res.message);
                if (res.debug_otp) {
                    app.toast.warning(`[DEBUG OTP CODE]: ${res.debug_otp}`, 15000);
                }
                const emailVal = document.getElementById('reg-email')?.value || '';
                window.switchAuthView('otp', { email: emailVal, userId: res.user_id });
            }
        );

        handleFormSubmit(
            document.getElementById('form-otp'),
            document.getElementById('btn-verify-otp'),
            (res) => {
                app.toast.success(res.message);
                setTimeout(() => {
                    window.switchAuthView('login');
                    const loginEmail = document.getElementById('login-email');
                    const regEmail = document.getElementById('reg-email');
                    if (loginEmail && regEmail) loginEmail.value = regEmail.value;
                }, 1000);
            }
        );

        handleFormSubmit(
            document.getElementById('form-forgot'),
            document.getElementById('btn-forgot'),
            (res) => {
                app.toast.success(res.message);
                if (res.debug_otp) {
                    app.toast.warning(`[DEBUG OTP CODE]: ${res.debug_otp}`, 15000);
                }
                window.switchAuthView('reset-otp', { userId: res.user_id });
            }
        );

        handleFormSubmit(
            document.getElementById('form-reset-otp'),
            document.getElementById('btn-verify-reset-otp'),
            (res) => {
                app.toast.success(res.message);
                setTimeout(() => window.switchAuthView('new-password'), 600);
            }
        );

        handleFormSubmit(
            document.getElementById('form-new-password'),
            document.getElementById('btn-reset-password'),
            (res) => {
                app.toast.success(res.message);
                setTimeout(() => window.switchAuthView('login'), 1200);
            }
        );

        // Resend Button
        const resendBtn = document.getElementById('btn-resend-otp');
        if (resendBtn) {
            resendBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const userId = document.getElementById('otp-user-id')?.value;

                try {
                    const response = await app.api('api/auth.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'resend_otp',
                            user_id: userId,
                            csrf_token: app.getCsrfToken()
                        })
                    });

                    if (response.success) {
                        app.toast.success(response.message);
                        if (response.debug_otp) {
                            app.toast.warning(`[DEBUG OTP CODE]: ${response.debug_otp}`, 15000);
                        }
                        startResendTimer();
                    } else {
                        app.toast.error(response.message);
                    }
                } catch (err) {
                    app.toast.error(err.message || 'Failed to resend code.');
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();


<?php
/**
 * PERSONAL STORAGE — Authentication Page
 * Single page: Login / Register / OTP / Forgot / Reset
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Redirect if already logged in
AuthMiddleware::redirectIfAuthenticated();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName = e(Config::get('APP_NAME', 'Personal Storage'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
</head>
<body class="auth-body">

    <!-- Background Effects -->
    <div class="auth-bg">
        <div class="auth-bg__orb auth-bg__orb--1"></div>
        <div class="auth-bg__orb auth-bg__orb--2"></div>
        <div class="auth-bg__orb auth-bg__orb--3"></div>
    </div>

    <main class="auth-page">
        <div class="auth-card">

        <!-- Back to Home Link -->
            <div class="auth-back-home">
                <a href="index.php">
                    <span>←</span>
                    <span>Back to Home</span>
                </a>
            </div>
            
            <!-- Header -->
            <div class="auth-header">
                <div class="auth-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="url(#logoGrad)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <defs><linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="100%" stop-color="#8b5cf6"/></linearGradient></defs>
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h1 class="auth-title"><?= $appName ?></h1>
                <p class="auth-subtitle" id="auth-subtitle">Sign in to your account</p>
            </div>

            <!-- ═══ VIEW: LOGIN ═══ -->
            <div class="auth-view active" id="view-login">
                <form id="form-login" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label class="form-label" for="login-email">Email Address</label>
                        <input type="email" id="login-email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email">
                        <div class="form-error" id="login-email-error"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="login-password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle" data-target="login-password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="form-error" id="login-password-error"></div>
                    </div>

                    <div class="form-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember"> <span>Remember me</span>
                        </label>
                        <a href="#" class="auth-link" data-view="forgot">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-login">Sign In</button>

                    <div class="auth-divider"><span>or</span></div>

                    <p class="auth-switch">
                        Don't have an account? <a href="#" class="auth-link" data-view="register">Create one</a>
                    </p>
                </form>
            </div>

            <!-- ═══ VIEW: REGISTER ═══ -->
            <div class="auth-view" id="view-register">
                <form id="form-register" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label class="form-label" for="reg-name">Full Name</label>
                        <input type="text" id="reg-name" name="name" class="form-input" placeholder="John Doe" required autocomplete="name">
                        <div class="form-error" id="reg-name-error"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-email">Email Address</label>
                        <input type="email" id="reg-email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email">
                        <div class="form-error" id="reg-email-error"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-password" name="password" class="form-input" placeholder="Min 8 characters" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-target="reg-password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="password-strength" id="password-strength">
                            <div class="password-strength__bar"><div class="password-strength__fill" id="strength-fill"></div></div>
                            <span class="password-strength__text" id="strength-text"></span>
                        </div>
                        <div class="form-error" id="reg-password-error"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-confirm">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="reg-confirm" name="password_confirmation" class="form-input" placeholder="Repeat your password" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-target="reg-confirm" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="form-error" id="reg-password_confirmation-error"></div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-register">Create Account</button>

                    <p class="auth-switch">
                        Already have an account? <a href="#" class="auth-link" data-view="login">Sign in</a>
                    </p>
                </form>
            </div>

            <!-- ═══ VIEW: OTP VERIFICATION ═══ -->
            <div class="auth-view" id="view-otp">
                <form id="form-otp" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="verify_otp">
                    <input type="hidden" name="user_id" id="otp-user-id" value="">

                    <div class="otp-icon">📧</div>
                    <p class="otp-message">Enter the 5-digit code sent to<br><strong id="otp-email-display">your email</strong></p>

                    <div class="otp-inputs" id="otp-inputs">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                    </div>
                    <input type="hidden" name="otp" id="otp-value">
                    <div class="form-error text-center" id="otp-error"></div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-verify-otp">Verify Code</button>

                    <div class="otp-resend">
                        <span id="resend-timer">Resend in <strong id="resend-countdown">60</strong>s</span>
                        <a href="#" class="auth-link" id="btn-resend-otp" style="display:none">Resend Code</a>
                    </div>

                    <p class="auth-switch">
                        <a href="#" class="auth-link" data-view="login">← Back to Sign In</a>
                    </p>
                </form>
            </div>

            <!-- ═══ VIEW: FORGOT PASSWORD ═══ -->
            <div class="auth-view" id="view-forgot">
                <form id="form-forgot" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="forgot_password">

                    <div class="otp-icon">🔑</div>
                    <p class="otp-message">Enter your email and we'll send you a reset code.</p>

                    <div class="form-group">
                        <label class="form-label" for="forgot-email">Email Address</label>
                        <input type="email" id="forgot-email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="email">
                        <div class="form-error" id="forgot-email-error"></div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-forgot">Send Reset Code</button>

                    <p class="auth-switch">
                        <a href="#" class="auth-link" data-view="login">← Back to Sign In</a>
                    </p>
                </form>
            </div>

            <!-- ═══ VIEW: RESET OTP ═══ -->
            <div class="auth-view" id="view-reset-otp">
                <form id="form-reset-otp" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="verify_reset_otp">
                    <input type="hidden" name="user_id" id="reset-otp-user-id" value="">

                    <div class="otp-icon">🔐</div>
                    <p class="otp-message">Enter the reset code sent to your email.</p>

                    <div class="otp-inputs" id="reset-otp-inputs">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                        <input type="text" maxlength="1" class="otp-box" inputmode="numeric" pattern="[0-9]">
                    </div>
                    <input type="hidden" name="otp" id="reset-otp-value">
                    <div class="form-error text-center" id="reset-otp-error"></div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-verify-reset-otp">Verify Code</button>

                    <p class="auth-switch">
                        <a href="#" class="auth-link" data-view="login">← Back to Sign In</a>
                    </p>
                </form>
            </div>

            <!-- ═══ VIEW: NEW PASSWORD ═══ -->
            <div class="auth-view" id="view-new-password">
                <form id="form-new-password" class="auth-form" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="reset_password">

                    <div class="otp-icon">✅</div>
                    <p class="otp-message">Set your new password.</p>

                    <div class="form-group">
                        <label class="form-label" for="new-password">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="new-password" name="password" class="form-input" placeholder="Min 8 characters" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-target="new-password" aria-label="Toggle password visibility">
                                <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="form-error" id="new-password-error"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new-confirm">Confirm New Password</label>
                        <input type="password" id="new-confirm" name="password_confirmation" class="form-input" placeholder="Repeat new password" required autocomplete="new-password">
                        <div class="form-error" id="new-password_confirmation-error"></div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg btn--full" id="btn-reset-password">Reset Password</button>
                </form>
            </div>

        </div>
    </main>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
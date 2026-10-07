<?php
/**
 * PERSONAL STORAGE — Authentication Service
 * Smart registration & unified User/Admin login.
 */

declare(strict_types=1);

class AuthService
{
    private UserRepository $userRepo;
    private StorageSettingsRepository $settingsRepo;
    private Mailer $mailer;

    public function __construct()
    {
        $this->userRepo     = new UserRepository();
        $this->settingsRepo = new StorageSettingsRepository();
        $this->mailer       = new Mailer();
    }

    // ══════════════════════════════════════════
    //  UNIFIED LOGIN
    // ══════════════════════════════════════════
    public function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateKey = "login:{$ip}";

        $maxAttempts = (int)Config::get('RATE_LIMIT_LOGIN', 5);
        $window = (int)Config::get('RATE_LIMIT_WINDOW', 900);

        if (!RateLimiter::check($ip, 'login', $maxAttempts, $window)['allowed']) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }

        // Use standard singleton Database connection
        $db = Database::getInstance()->getConnection();

        // 1. Check Admin Table
        $stmt = $db->prepare('SELECT * FROM `admin_users` WHERE `email` = :email OR `username` = :username LIMIT 1');
        $stmt->execute([':email' => $email, ':username' => $email]);
        $admin = $stmt->fetch();

        // Safe search across both standard password column fields (password and password_hash)
        if ($admin) {
            $adminHash = $admin['password_hash'] ?? $admin['password'] ?? '';
            if (password_verify($password, $adminHash)) {
                if ($admin['status'] !== 'active') {
                    return ['success' => false, 'message' => 'This administrative account is disabled.'];
                }

                session_regenerate_id(true);

                $_SESSION['admin_id']            = (int)$admin['id'];
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_username']      = $admin['username'];
                $_SESSION['admin_email']         = $admin['email'];
                $_SESSION['admin_name']          = $admin['full_name'] ?? 'System Administrator';
                $_SESSION['login_time']          = time();

                $update = $db->prepare('UPDATE `admin_users` SET `last_login_at` = NOW() WHERE `id` = :id');
                $update->execute([':id' => $admin['id']]);

                ActivityLogger::adminLogin((int)$admin['id']);

                return [
                    'success'  => true,
                    'message'  => 'Administrative verification complete.',
                    'redirect' => 'admin/index.php',
                ];
            }
        }

        // 2. Check Standard User Table
        $user = $this->userRepo->findByEmail($email);
        if ($user) {
            $userHash = $user['password_hash'] ?? $user['password'] ?? '';
            if (password_verify($password, $userHash)) {
                if ($user['status'] === 'pending') {
                    return [
                        'success' => false,
                        'message' => 'Please verify your email address first.',
                        'action'  => 'verify',
                        'user_id' => (int)$user['id'],
                    ];
                }
                if ($user['status'] === 'disabled') {
                    return ['success' => false, 'message' => 'Your account has been disabled. Please contact support.'];
                }

                session_regenerate_id(true);

                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['authenticated'] = true;
                $_SESSION['user_name']     = $user['full_name'];
                $_SESSION['user_email']    = $user['email'];
                $_SESSION['login_time']    = time();

                $this->userRepo->updateLastLogin((int)$user['id']);
                ActivityLogger::loginSuccess((int)$user['id']);

                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);

                return [
                    'success'  => true,
                    'message'  => 'Sign in successful.',
                    'redirect' => $redirect,
                ];
            }
        }

        ActivityLogger::loginFailed($email);

        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    // ══════════════════════════════════════════
    //  SMART REGISTRATION
    // ══════════════════════════════════════════
    public function register(string $name, string $username, string $email, string $password): array
    {
        $name  = trim($name);
        $email = mb_strtolower(trim($email));
        $username = mb_strtolower(trim($username));

        if (empty($name) || mb_strlen($name) < 2) {
            return ['success' => false, 'message' => 'Full name must be at least 2 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (mb_strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        // Auto-generate username from email if not provided
        if (empty($username)) {
            $baseUser = preg_replace('/[^a-z0-9_]/', '', explode('@', $email)[0]);
            if (empty($baseUser)) {
                $baseUser = 'user';
            }
            $username = $baseUser . '_' . substr(bin2hex(random_bytes(3)), 0, 4);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $maxReg = (int)Config::get('RATE_LIMIT_REGISTER', 3);
        $window = (int)Config::get('RATE_LIMIT_WINDOW', 900);

        if (!RateLimiter::check($ip, 'register', $maxReg, $window)['allowed']) {
            return ['success' => false, 'message' => 'Too many registration attempts. Please try again later.'];
        }

        // SMART DUPLICATE CHECK
        $existingUser = $this->userRepo->findByEmail($email);
        if ($existingUser) {
            if ($existingUser['status'] === 'active') {
                return ['success' => false, 'message' => 'An account with this email already exists. Please log in instead.'];
            }
            
            if ($existingUser['status'] === 'disabled') {
                return ['success' => false, 'message' => 'This email cannot be used for registration.'];
            }

            if ($existingUser['status'] === 'pending') {
                $userId = (int)$existingUser['id'];
                
                $this->userRepo->update($userId, [
                    'full_name'     => $name,
                    'username'      => $username,
                    'password'      => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                ]);
                
                return $this->resendRegistrationOtp($userId);
            }
        }

        // Create new user (pending)
        $userId = $this->userRepo->create([
            'full_name'     => $name,
            'username'      => $username,
            'email'         => $email,
            'password'      => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'status'        => 'pending',
        ]);

        if (!$userId) {
            return ['success' => false, 'message' => 'Failed to create account.'];
        }

        $otp = (string)random_int(10000, 99999);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expirySeconds = (int)Config::get('OTP_EXPIRY_SECONDS', 600);

        $this->userRepo->invalidateOldVerifications($userId);
        $this->userRepo->createEmailVerification($userId, $otpHash, $expirySeconds);

        $emailSent = false;
        $debugOtp = null;
        try {
            $emailSent = $this->mailer->sendOTP($email, $name, $otp, 'verification');
        } catch (\Throwable $e) {
            error_log('Registration email send failed for ' . $email . ': ' . $e->getMessage());
        }

        if (!$emailSent && Config::get('APP_DEBUG') === 'true') {
            $debugOtp = $otp;
        }

        ActivityLogger::userRegistered($userId, $email);

        $response = [
            'success' => true,
            'message' => $emailSent 
                ? 'Verification code sent to your email.' 
                : 'Account created, but email delivery failed. Please check SMTP configuration.',
            'user_id' => $userId,
        ];
        if ($debugOtp) {
            $response['debug_otp'] = $debugOtp;
        }

        return $response;
    }

    // ══════════════════════════════════════════
    //  VERIFY OTP
    // ══════════════════════════════════════════
    public function verifyRegistrationOtp(int $userId, string $otp): array
    {
        $verification = $this->userRepo->getLatestVerification($userId);

        if (!$verification) {
            return ['success' => false, 'message' => 'No pending verification found.'];
        }

        if (strtotime($verification['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
        }

        $maxAttempts = (int)Config::get('OTP_MAX_ATTEMPTS', 5);
        if ($verification['attempts'] >= $maxAttempts) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.'];
        }

        if (!password_verify($otp, $verification['otp_hash'])) {
            $this->userRepo->incrementVerificationAttempts($verification['id']);
            $remaining = $maxAttempts - $verification['attempts'] - 1;
            return ['success' => false, 'message' => "Invalid code. {$remaining} attempt(s) remaining."];
        }

        $this->userRepo->markVerificationUsed($verification['id']);
        $this->userRepo->activateAccount($userId);

        ActivityLogger::emailVerified($userId);

        return ['success' => true, 'message' => 'Email verified successfully! You can now log in.'];
    }

    // ══════════════════════════════════════════
    //  RESEND OTP
    // ══════════════════════════════════════════
    public function resendRegistrationOtp(int $userId): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invalid request.'];
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $cooldown = (int)Config::get('OTP_RESEND_COOLDOWN', 60);

        $otp = (string)random_int(10000, 99999);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expirySeconds = (int)Config::get('OTP_EXPIRY_SECONDS', 600);

        $this->userRepo->invalidateOldVerifications($userId);
        $this->userRepo->createEmailVerification($userId, $otpHash, $expirySeconds);

        $emailSent = false;
        $debugOtp = null;
        try {
            $emailSent = $this->mailer->sendOTP($user['email'], $user['full_name'], $otp, 'verification');
        } catch (\Throwable $e) {
            error_log('Resend email failed: ' . $e->getMessage());
        }

        if (!$emailSent && Config::get('APP_DEBUG') === 'true') {
            $debugOtp = $otp;
        }

        $response = [
            'success' => true,
            'message' => $emailSent 
                ? 'New verification code sent.' 
                : 'Code generated, but email delivery failed. Please check SMTP configuration.',
            'user_id' => $userId,
        ];
        if ($debugOtp) {
            $response['debug_otp'] = $debugOtp;
        }
        return $response;
    }

    // ══════════════════════════════════════════
    //  FORGOT PASSWORD
    // ══════════════════════════════════════════
    public function forgotPassword(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $user = $this->userRepo->findByEmail($email);
        if (!$user || $user['status'] !== 'active') {
            return [
                'success' => true,
                'message' => 'If an account exists with that email, a reset code has been sent.',
            ];
        }

        $userId = (int)$user['id'];
        $otp = (string)random_int(10000, 99999);
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expirySeconds = (int)Config::get('OTP_EXPIRY_SECONDS', 600);

        $this->userRepo->invalidateOldResets($userId);
        $this->userRepo->createPasswordReset($userId, $otpHash, $expirySeconds);

        $emailSent = false;
        $debugOtp = null;
        try {
            $emailSent = $this->mailer->sendOTP($email, $user['full_name'], $otp, 'reset');
        } catch (\Throwable $e) {
            error_log('Password reset email failed: ' . $e->getMessage());
        }

        if (!$emailSent && Config::get('APP_DEBUG') === 'true') {
            $debugOtp = $otp;
        }

        ActivityLogger::passwordResetRequested($userId);

        $response = [
            'success' => true,
            'message' => $emailSent 
                ? 'Password reset code sent to your email.' 
                : 'Code generated, but email delivery failed. Please check SMTP configuration.',
            'user_id' => $userId,
        ];
        if ($debugOtp) {
            $response['debug_otp'] = $debugOtp;
        }
        return $response;
    }

    // ══════════════════════════════════════════
    //  VERIFY RESET OTP
    // ══════════════════════════════════════════
    public function verifyResetOtp(int $userId, string $otp): array
    {
        $reset = $this->userRepo->getLatestPasswordReset($userId);

        if (!$reset) {
            return ['success' => false, 'message' => 'No pending reset found.'];
        }

        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Reset code has expired.'];
        }

        $maxAttempts = (int)Config::get('OTP_MAX_ATTEMPTS', 5);
        if ($reset['attempts'] >= $maxAttempts) {
            return ['success' => false, 'message' => 'Too many failed attempts.'];
        }

        if (!password_verify($otp, $reset['otp_hash'])) {
            $this->userRepo->incrementResetAttempts($reset['id']);
            $remaining = $maxAttempts - $reset['attempts'] - 1;
            return ['success' => false, 'message' => "Invalid code. {$remaining} attempt(s) remaining."];
        }

        $this->userRepo->markPasswordResetUsed($reset['id']);

        $_SESSION['reset_user_id'] = $userId;
        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_expires'] = time() + 300;

        return ['success' => true, 'message' => 'Code verified. Please set your new password.'];
    }

    // ══════════════════════════════════════════
    //  RESET PASSWORD
    // ══════════════════════════════════════════
    public function resetPassword(string $password, string $confirmPassword): array
    {
        if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_user_id'])) {
            return ['success' => false, 'message' => 'Unauthorized operation.'];
        }
        if (time() > ($_SESSION['reset_expires'] ?? 0)) {
            unset($_SESSION['reset_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);
            return ['success' => false, 'message' => 'Reset session expired.'];
        }

        if (mb_strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $userId = (int)$_SESSION['reset_user_id'];
        $this->userRepo->updatePassword($userId, $password);
        $this->userRepo->invalidateOldResets($userId);

        $user = $this->userRepo->findById($userId);
        if ($user) {
            try {
                $this->mailer->sendPasswordChangedEmail($user['email'], $user['full_name']);
            } catch (\Throwable $e) {
                // Ignore failure on post-reset alert
            }
        }

        unset($_SESSION['reset_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_expires']);

        ActivityLogger::passwordResetCompleted($userId);

        return ['success' => true, 'message' => 'Password reset successfully! You can now log in.'];
    }

    // ══════════════════════════════════════════
    //  LOGOUT
    // ══════════════════════════════════════════
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            ActivityLogger::logout((int)$userId);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }
}
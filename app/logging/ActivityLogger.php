<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Activity Logger
 * ==============================================
 * Logs security-sensitive and important actions
 * to the activity_logs table.
 *
 * NEVER log: plaintext passwords, OTP values,
 *            SMTP passwords, or other secrets.
 */

declare(strict_types=1);

class ActivityLogger
{
    private static ?PDO $db = null;

    private static function db(): PDO
    {
        if (self::$db === null) {
            self::$db = Database::getConnection();
        }
        return self::$db;
    }

    /**
     * Log an activity.
     *
     * @param string      $action     e.g., 'login', 'upload', 'delete'
     * @param string|null $entityType e.g., 'file', 'note', 'user', 'setting'
     * @param int|null    $entityId   The ID of the affected entity
     * @param array|null  $details    Additional context (NO SECRETS)
     * @param int|null    $userId     Acting user (null for admin/system)
     * @param int|null    $adminId    Acting admin (null for user actions)
     */
    public static function log(
        string  $action,
        ?string $entityType = null,
        ?int    $entityId = null,
        ?array  $details = null,
        ?int    $userId = null,
        ?int    $adminId = null
    ): void {
        try {
            // Auto-detect user/admin from session if not provided
            if ($userId === null && !empty($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            }
            if ($adminId === null && !empty($_SESSION['admin_id'])) {
                $adminId = (int)$_SESSION['admin_id'];
            }

            // Sanitize details — remove any keys that might contain secrets
            if ($details !== null) {
                $dangerousKeys = ['password', 'password_confirmation', 'otp', 'token', 'smtp_password', 'secret'];
                foreach ($dangerousKeys as $key) {
                    unset($details[$key]);
                }
            }

            $ip = getClientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($ua !== null) {
                $ua = mb_substr($ua, 0, 500);
            }

            $stmt = self::db()->prepare(
                'INSERT INTO `activity_logs`
                    (`user_id`, `admin_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`)
                 VALUES
                    (:user_id, :admin_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent)'
            );

            $stmt->execute([
                ':user_id'     => $userId,
                ':admin_id'    => $adminId,
                ':action'      => $action,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':details'     => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                ':ip_address'  => $ip,
                ':user_agent'  => $ua,
            ]);
        } catch (\Throwable $e) {
            // Logging should never break the application
            error_log('ActivityLogger failed: ' . $e->getMessage());
        }
    }

    // ── Convenience Methods ──────────────────

    public static function userRegistered(int $userId, string $email): void
    {
        self::log('registration', 'user', $userId, ['email' => $email], $userId);
    }

    public static function emailVerified(int $userId): void
    {
        self::log('email_verified', 'user', $userId, null, $userId);
    }

    public static function loginSuccess(int $userId): void
    {
        self::log('login', 'user', $userId, null, $userId);
    }

    public static function loginFailed(string $email): void
    {
        self::log('login_failed', 'user', null, ['email' => $email]);
    }

    public static function logout(int $userId): void
    {
        self::log('logout', 'user', $userId, null, $userId);
    }

    public static function passwordResetRequested(int $userId): void
    {
        self::log('password_reset_requested', 'user', $userId, null, $userId);
    }

    public static function passwordResetCompleted(int $userId): void
    {
        self::log('password_reset_completed', 'user', $userId, null, $userId);
    }

    public static function fileUploaded(int $userId, int $fileId, string $filename, int $size): void
    {
        self::log('upload', 'file', $fileId, [
            'filename' => $filename,
            'size'     => $size,
        ], $userId);
    }

    public static function fileDownloaded(int $userId, int $fileId, string $filename): void
    {
        self::log('download', 'file', $fileId, ['filename' => $filename], $userId);
    }

    public static function fileDeleted(int $userId, int $fileId, string $filename): void
    {
        self::log('delete', 'file', $fileId, ['filename' => $filename], $userId);
    }

    public static function fileRestored(int $userId, int $fileId, string $filename): void
    {
        self::log('restore', 'file', $fileId, ['filename' => $filename], $userId);
    }

    public static function filePermanentlyDeleted(int $userId, int $fileId): void
    {
        self::log('permanent_delete', 'file', $fileId, null, $userId);
    }

    public static function adminLogin(int $adminId): void
    {
        self::log('admin_login', 'admin', $adminId, null, null, $adminId);
    }

    public static function adminAction(int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        self::log($action, $entityType, $entityId, $details, null, $adminId);
    }

    public static function settingChanged(int $adminId, string $key, string $oldValue, string $newValue): void
    {
        self::log('setting_changed', 'setting', null, [
            'key'       => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ], null, $adminId);
    }
}
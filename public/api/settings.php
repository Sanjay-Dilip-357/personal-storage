<?php
/**
 * PERSONAL STORAGE — User Settings API Endpoint
 * Handles profile updates, password changes, and account deletion.
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../app/bootstrap.php';

SecurityHeaders::sendApi();

// Enforce authenticated regular user
if (!AuthMiddleware::isLoggedIn()) {
    ob_end_clean();
    jsonError('Authentication required.', 401);
}

if (requestMethod() !== 'POST') {
    ob_end_clean();
    jsonError('Method not allowed.', 405);
}

if (!Csrf::validate()) {
    ob_end_clean();
    jsonError('Security token expired. Please refresh the page.', 403);
}

$userId   = AuthMiddleware::userId();
$userRepo = new UserRepository();
$db       = Database::getConnection();
$action   = trim((string)($_POST['action'] ?? ''));

try {
    $result = match ($action) {

        // ── UPDATE PROFILE ───────────────────
        'update_profile' => (function () use ($userRepo, $userId) {
            $name = sanitize($_POST['full_name'] ?? '');

            if (mb_strlen($name) < 2) {
                return ['success' => false, 'message' => 'Full name must be at least 2 characters.'];
            }
            if (mb_strlen($name) > 100) {
                return ['success' => false, 'message' => 'Full name cannot exceed 100 characters.'];
            }

            $userRepo->update($userId, ['full_name' => $name]);
            $_SESSION['user_name'] = $name;

            ActivityLogger::log('profile_updated', 'user', $userId, ['new_name' => $name], $userId);

            return [
                'success' => true,
                'message' => 'Profile updated successfully.',
                'name'    => $name,
            ];
        })(),

        // ── CHANGE PASSWORD ──────────────────
        'change_password' => (function () use ($userRepo, $userId) {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword     = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if (empty($currentPassword) || empty($newPassword)) {
                return ['success' => false, 'message' => 'All password fields are required.'];
            }

            // Verify current password
            $user = $userRepo->findById($userId);
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }

            if (mb_strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'New password must be at least 8 characters.'];
            }

            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'New password confirmation does not match.'];
            }

            if ($currentPassword === $newPassword) {
                return ['success' => false, 'message' => 'New password cannot be the same as your current password.'];
            }

            // Update password with secure BCRYPT hash
            $userRepo->updatePassword($userId, $newPassword);
            session_regenerate_id(true);

            ActivityLogger::log('password_changed', 'user', $userId, null, $userId);

            return ['success' => true, 'message' => 'Password changed successfully.'];
        })(),

        // ── DELETE ACCOUNT (Self-Service) ────
        'delete_account' => (function () use ($userRepo, $db, $userId) {
            $password = (string)($_POST['password'] ?? '');

            if (empty($password)) {
                return ['success' => false, 'message' => 'Please enter your password to confirm account deletion.'];
            }

            $user = $userRepo->findById($userId);
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Incorrect password. Account deletion aborted.'];
            }

            // 1. Physically remove all uploaded files on disk
            $stmt = $db->prepare('SELECT `storage_path` FROM `files` WHERE `user_id` = :uid');
            $stmt->execute([':uid' => $userId]);
            $files = $stmt->fetchAll();

            foreach ($files as $f) {
                $physicalPath = UPLOAD_PATH . '/' . $f['storage_path'];
                if (file_exists($physicalPath) && is_file($physicalPath)) {
                    @unlink($physicalPath);
                }
            }

            // Also clean user's upload directory if empty
            $userDir = UPLOAD_PATH . '/' . $userId;
            if (is_dir($userDir)) {
                @rmdir($userDir);
            }

            // 2. Delete database records (Foreign Keys CASCADE will remove files, notes, settings)
            $delStmt = $db->prepare('DELETE FROM `users` WHERE `id` = :id');
            $delStmt->execute([':id' => $userId]);

            // 3. Clear session
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

            return [
                'success'  => true,
                'message'  => 'Your account and all associated data have been permanently deleted.',
                'redirect' => appUrl('auth.php'),
            ];
        })(),

        default => ['success' => false, 'message' => 'Unknown operation.'],
    };

    ob_end_clean();
    $statusCode = $result['success'] ? 200 : 422;
    jsonResponse($result, $statusCode);

} catch (\Throwable $e) {
    ob_end_clean();
    appLog('Settings API Error: ' . $e->getMessage(), 'error');
    jsonError(Config::isDebug() ? $e->getMessage() : 'An unexpected error occurred.', 500);
}
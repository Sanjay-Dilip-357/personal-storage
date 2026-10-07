<?php
/**
 * PERSONAL STORAGE — Secure Admin Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

// Log admin logout activity
$adminId = $_SESSION['admin_id'] ?? null;
if ($adminId) {
    ActivityLogger::adminAction((int)$adminId, 'admin_logout', 'admin', (int)$adminId);
}

// Clear admin session variables
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_authenticated'],
    $_SESSION['admin_username'],
    $_SESSION['admin_email'],
    $_SESSION['admin_name']
);

// Redirect directly to the unified auth page
header('Location: ' . appUrl('auth.php'));
exit;
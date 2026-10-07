<?php
/**
 * PERSONAL STORAGE — Recycle Bin API Endpoint
 * Handles secure listing, restoration, and permanent removal of soft-deleted items.
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../app/bootstrap.php';

SecurityHeaders::sendApi();

// Enforce session check
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

$userId = AuthMiddleware::userId();
$action = trim($_POST['action'] ?? '');
$db     = Database::getConnection();

try {
    $result = match ($action) {

        // ── LIST DELETED ITEMS ────────────────
        'list' => (function () use ($db, $userId) {
            $type = trim($_POST['type'] ?? 'file'); // 'file' or 'note'
            
            if ($type === 'file') {
                $stmt = $db->prepare(
                    'SELECT `id`, `original_name` AS `name`, `size_bytes` AS `size`, `deleted_at`, `deletion_expiry_at`
                     FROM `files`
                     WHERE `user_id` = :uid AND `deleted_at` IS NOT NULL
                     ORDER BY `deleted_at` DESC'
                );
                $stmt->execute([':uid' => $userId]);
                $items = $stmt->fetchAll();
            } else {
                $stmt = $db->prepare(
                    'SELECT `id`, COALESCE(`title`, "Untitled Note") AS `name`, OCTET_LENGTH(`content`) AS `size`, `deleted_at`, `deletion_expiry_at`
                     FROM `notes`
                     WHERE `user_id` = :uid AND `deleted_at` IS NOT NULL
                     ORDER BY `deleted_at` DESC'
                );
                $stmt->execute([':uid' => $userId]);
                $items = $stmt->fetchAll();
            }

            return [
                'success' => true,
                'items'   => $items,
                'type'    => $type
            ];
        })(),

        // ── RESTORE ITEM ──────────────────────
        'restore' => (function () use ($db, $userId) {
            $type   = trim($_POST['type'] ?? 'file');
            $itemId = (int)($_POST['item_id'] ?? 0);

            if ($itemId <= 0) {
                return ['success' => false, 'message' => 'Invalid ID specified.'];
            }

            if ($type === 'file') {
                // Check if user owns file
                $stmt = $db->prepare('SELECT `original_name` FROM `files` WHERE `id` = :id AND `user_id` = :uid AND `deleted_at` IS NOT NULL LIMIT 1');
                $stmt->execute([':id' => $itemId, ':uid' => $userId]);
                $file = $stmt->fetch();

                if (!$file) {
                    return ['success' => false, 'message' => 'File not found or access denied.'];
                }

                // Restore
                $restore = $db->prepare(
                    'UPDATE `files` SET `deleted_at` = NULL, `deletion_expiry_at` = NULL WHERE `id` = :id'
                );
                $restore->execute([':id' => $itemId]);

                ActivityLogger::fileRestored($userId, $itemId, $file['original_name']);
                $message = 'File restored successfully.';
            } else {
                // Check if user owns note
                $stmt = $db->prepare('SELECT `id` FROM `notes` WHERE `id` = :id AND `user_id` = :uid AND `deleted_at` IS NOT NULL LIMIT 1');
                $stmt->execute([':id' => $itemId, ':uid' => $userId]);
                $note = $stmt->fetch();

                if (!$note) {
                    return ['success' => false, 'message' => 'Note not found or access denied.'];
                }

                // Restore
                $restore = $db->prepare(
                    'UPDATE `notes` SET `deleted_at` = NULL, `deletion_expiry_at` = NULL WHERE `id` = :id'
                );
                $restore->execute([':id' => $itemId]);

                ActivityLogger::log('note_restored', 'note', $itemId, null, $userId);
                $message = 'Note restored successfully.';
            }

            return ['success' => true, 'message' => $message];
        })(),

        // ── PERMANENT DELETE ITEM ─────────────
        'permanent_delete' => (function () use ($db, $userId) {
            $type   = trim($_POST['type'] ?? 'file');
            $itemId = (int)($_POST['item_id'] ?? 0);

            if ($itemId <= 0) {
                return ['success' => false, 'message' => 'Invalid ID specified.'];
            }

            if ($type === 'file') {
                // Verify file ownership
                $stmt = $db->prepare('SELECT `original_name`, `storage_path` FROM `files` WHERE `id` = :id AND `user_id` = :uid AND `deleted_at` IS NOT NULL LIMIT 1');
                $stmt->execute([':id' => $itemId, ':uid' => $userId]);
                $file = $stmt->fetch();

                if (!$file) {
                    return ['success' => false, 'message' => 'File not found or access denied.'];
                }

                // Delete physical storage file safely
                $physicalPath = UPLOAD_PATH . '/' . $file['storage_path'];
                if (file_exists($physicalPath) && is_file($physicalPath)) {
                    @unlink($physicalPath);
                }

                // Delete database record
                $delete = $db->prepare('DELETE FROM `files` WHERE `id` = :id');
                $delete->execute([':id' => $itemId]);

                ActivityLogger::filePermanentlyDeleted($userId, $itemId);
                $message = 'File permanently destroyed.';
            } else {
                // Verify note ownership
                $stmt = $db->prepare('SELECT `id` FROM `notes` WHERE `id` = :id AND `user_id` = :uid AND `deleted_at` IS NOT NULL LIMIT 1');
                $stmt->execute([':id' => $itemId, ':uid' => $userId]);
                $note = $stmt->fetch();

                if (!$note) {
                    return ['success' => false, 'message' => 'Note not found or access denied.'];
                }

                // Delete database record
                $delete = $db->prepare('DELETE FROM `notes` WHERE `id` = :id');
                $delete->execute([':id' => $itemId]);

                ActivityLogger::log('note_permanently_deleted', 'note', $itemId, null, $userId);
                $message = 'Note permanently destroyed.';
            }

            return ['success' => true, 'message' => $message];
        })(),

        default => ['success' => false, 'message' => 'Unknown operation.'],
    };

    ob_end_clean();
    jsonResponse($result);

} catch (\Throwable $e) {
    ob_end_clean();
    appLog('Recycle Bin API Error: ' . $e->getMessage(), 'error');
    jsonError(Config::isDebug() ? $e->getMessage() : 'A system error occurred.', 500);
}
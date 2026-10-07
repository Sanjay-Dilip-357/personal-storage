<?php
/**
 * PERSONAL STORAGE — Files & Notes Combined API Router
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../app/bootstrap.php';

SecurityHeaders::sendApi();

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
$action   = trim($_POST['action'] ?? '');
$fileRepo = new FileRepository();
$noteRepo = new NoteRepository();

try {
    $result = match ($action) {

        // ── LIST COMBINED FILES & NOTES ──────
        'list' => (function () use ($fileRepo, $userId) {
            $search   = sanitize($_POST['search'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $sort     = trim($_POST['sort'] ?? 'created_at');
            $order    = trim($_POST['order'] ?? 'DESC');
            $page     = max(1, (int)($_POST['page'] ?? 1));
            $limit    = 24;
            $offset   = ($page - 1) * $limit;

            $files = $fileRepo->getUserFiles($userId, $search, $category, $sort, $order, $limit, $offset);
            $total = $fileRepo->getUserFileCount($userId, $search, $category);

            return [
                'success' => true,
                'files'   => $files,
                'total'   => $total,
                'page'    => $page,
            ];
        })(),

        // ── RENAME ITEM ─────────────────────
        'rename' => (function () use ($fileRepo, $noteRepo, $userId) {
            $itemId   = (int)($_POST['file_id'] ?? 0);
            $itemType = trim($_POST['item_type'] ?? 'file');
            $newName  = trim($_POST['new_name'] ?? '');

            if ($itemId <= 0) {
                return ['success' => false, 'message' => 'Invalid ID.'];
            }
            if ($newName === '') {
                return ['success' => false, 'message' => 'Name cannot be empty.'];
            }

            if ($itemType === 'note') {
                $note = $noteRepo->getUserNoteById($userId, $itemId);
                if (!$note) {
                    return ['success' => false, 'message' => 'Note not found or access denied.'];
                }
                $noteRepo->updateNote($userId, $itemId, $newName, $note['content']);
                ActivityLogger::log('note_renamed', 'note', $itemId, ['new_title' => $newName], $userId);
                $msg = 'Note renamed successfully.';
            } else {
                $file = $fileRepo->getUserFileById($userId, $itemId);
                if (!$file) {
                    return ['success' => false, 'message' => 'File not found or access denied.'];
                }
                $ext = pathinfo($file['original_name'], PATHINFO_EXTENSION);
                $cleanName = preg_replace('/[^\w\s\-\.\(\)]/u', '_', $newName);
                $cleanName = trim($cleanName, '._ ');
                $finalName = $cleanName . ($ext ? '.' . $ext : '');

                $fileRepo->renameFile($userId, $itemId, $finalName);
                ActivityLogger::log('file_renamed', 'file', $itemId, ['new_name' => $finalName], $userId);
                $msg = 'File renamed successfully.';
            }

            return ['success' => true, 'message' => $msg];
        })(),

        // ── DELETE ITEM (Soft Delete) ───────
        'delete' => (function () use ($fileRepo, $noteRepo, $userId) {
            $itemId   = (int)($_POST['file_id'] ?? 0);
            $itemType = trim($_POST['item_type'] ?? 'file');

            if ($itemId <= 0) {
                return ['success' => false, 'message' => 'Invalid ID.'];
            }

            $settingsRepo = new StorageSettingsRepository();
            $recycleBinDays = (int)$settingsRepo->getSetting('recycle_bin_days', 7);

            if ($itemType === 'note') {
                $deleted = $noteRepo->softDeleteNote($userId, $itemId, $recycleBinDays);
                if (!$deleted) {
                    return ['success' => false, 'message' => 'Note not found or access denied.'];
                }
                ActivityLogger::log('note_deleted', 'note', $itemId, null, $userId);
                $msg = 'Note moved to Recycle Bin.';
            } else {
                $file = $fileRepo->getUserFileById($userId, $itemId);
                $deleted = $fileRepo->softDeleteFile($userId, $itemId, $recycleBinDays);
                if (!$deleted) {
                    return ['success' => false, 'message' => 'File not found or access denied.'];
                }
                $filename = $file ? $file['original_name'] : 'unknown';
                ActivityLogger::fileDeleted($userId, $itemId, $filename);
                $msg = 'File moved to Recycle Bin.';
            }

            return ['success' => true, 'message' => $msg];
        })(),

        // ── UPLOAD FILE ─────────────────────
        'upload' => (function () use ($userId) {
            if (empty($_FILES['file'])) {
                return ['success' => false, 'message' => 'No file provided.'];
            }
            $uploadService = new UploadService();
            return $uploadService->processUpload($userId, $_FILES['file']);
        })(),

        default => ['success' => false, 'message' => 'Unknown action.'],
    };

    ob_end_clean();
    jsonResponse($result);

} catch (\Throwable $e) {
    ob_end_clean();
    appLog('Files API Router Error: ' . $e->getMessage(), 'error');
    jsonError(Config::isDebug() ? $e->getMessage() : 'An unexpected error occurred.', 500);
}
<?php
/**
 * PERSONAL STORAGE — Notes API Endpoint
 * All note CRUD operations with full security enforcement.
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../app/bootstrap.php';

SecurityHeaders::sendApi();

// Require authenticated user
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

$userId     = AuthMiddleware::userId();
$action     = trim($_POST['action'] ?? '');
$noteRepo   = new NoteRepository();
$settingsRepo = new StorageSettingsRepository();

try {
    $result = match ($action) {

        // ── LIST NOTES ──────────────────────
        'list' => (function () use ($noteRepo, $userId) {
            $search = sanitize($_POST['search'] ?? '');
            $sort   = trim($_POST['sort'] ?? 'updated_at');
            $order  = trim($_POST['order'] ?? 'DESC');
            $page   = max(1, (int)($_POST['page'] ?? 1));
            $limit  = 20;
            $offset = ($page - 1) * $limit;

            $notes = $noteRepo->getUserNotes($userId, $search, $sort, $order, $limit, $offset);
            $total = $noteRepo->getUserNoteCount($userId, $search);

            // Truncate content for list view
            foreach ($notes as &$note) {
                $note['preview'] = mb_substr(strip_tags($note['content']), 0, 150);
                unset($note['content']);
            }

            return [
                'success' => true,
                'notes'   => $notes,
                'total'   => $total,
                'page'    => $page,
            ];
        })(),

        // ── GET SINGLE NOTE ─────────────────
        'get' => (function () use ($noteRepo, $userId) {
            $noteId = (int)($_POST['note_id'] ?? 0);
            if ($noteId <= 0) {
                return ['success' => false, 'message' => 'Invalid note ID.'];
            }

            $note = $noteRepo->getUserNoteById($userId, $noteId);
            if (!$note) {
                return ['success' => false, 'message' => 'Note not found.'];
            }

            return ['success' => true, 'note' => $note];
        })(),

        // ── CREATE NOTE ─────────────────────
        'create' => (function () use ($noteRepo, $settingsRepo, $userId) {
            $title   = $_POST['title'] ?? null;
            $content = trim($_POST['content'] ?? '');

            if ($content === '') {
                return ['success' => false, 'message' => 'Note content cannot be empty.'];
            }
            if (mb_strlen($content) > 50000) {
                return ['success' => false, 'message' => 'Note content exceeds 50,000 character limit.'];
            }
            if ($title !== null && mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 255);
            }

            // Check note count limit
            $maxNotes = (int)$settingsRepo->getUserSetting($userId, 'max_note_count', 500);
            $currentCount = $noteRepo->getUserActiveNoteCount($userId);
            if ($currentCount >= $maxNotes) {
                return ['success' => false, 'message' => "Note limit reached ({$maxNotes}). Delete some notes first."];
            }

            $noteId = $noteRepo->createNote($userId, $title, $content);

            ActivityLogger::log('note_created', 'note', $noteId, ['title' => $title], $userId);

            return ['success' => true, 'message' => 'Note created successfully.', 'note_id' => $noteId];
        })(),

        // ── UPDATE NOTE ─────────────────────
        'update' => (function () use ($noteRepo, $userId) {
            $noteId  = (int)($_POST['note_id'] ?? 0);
            $title   = $_POST['title'] ?? null;
            $content = trim($_POST['content'] ?? '');

            if ($noteId <= 0) {
                return ['success' => false, 'message' => 'Invalid note ID.'];
            }
            if ($content === '') {
                return ['success' => false, 'message' => 'Note content cannot be empty.'];
            }
            if (mb_strlen($content) > 50000) {
                return ['success' => false, 'message' => 'Note content exceeds 50,000 character limit.'];
            }
            if ($title !== null && mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 255);
            }

            // Ownership is verified inside the repository method
            $updated = $noteRepo->updateNote($userId, $noteId, $title, $content);
            if (!$updated) {
                return ['success' => false, 'message' => 'Note not found or access denied.'];
            }

            ActivityLogger::log('note_updated', 'note', $noteId, ['title' => $title], $userId);

            return ['success' => true, 'message' => 'Note updated successfully.'];
        })(),

        // ── DELETE NOTE (Soft Delete) ───────
        'delete' => (function () use ($noteRepo, $settingsRepo, $userId) {
            $noteId = (int)($_POST['note_id'] ?? 0);
            if ($noteId <= 0) {
                return ['success' => false, 'message' => 'Invalid note ID.'];
            }

            $recycleBinDays = (int)$settingsRepo->getSetting('recycle_bin_days', 7);
            $deleted = $noteRepo->softDeleteNote($userId, $noteId, $recycleBinDays);
            if (!$deleted) {
                return ['success' => false, 'message' => 'Note not found or access denied.'];
            }

            ActivityLogger::log('note_deleted', 'note', $noteId, null, $userId);

            return ['success' => true, 'message' => 'Note moved to Recycle Bin.'];
        })(),

        default => ['success' => false, 'message' => 'Unknown action.'],
    };

    ob_end_clean();

    $statusCode = $result['success'] ? 200 : 422;
    jsonResponse($result, $statusCode);

} catch (\Throwable $e) {
    ob_end_clean();
    appLog('Notes API Error: ' . $e->getMessage(), 'error');

    $message = Config::isDebug() ? $e->getMessage() : 'An unexpected error occurred.';
    jsonError($message, 500);
}
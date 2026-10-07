<?php
/**
 * PERSONAL STORAGE — Secure File & Note Download
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (!AuthMiddleware::isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized.';
    exit;
}

$userId = AuthMiddleware::userId();
$itemId = (int)($_GET['id'] ?? 0);
$type   = trim((string)($_GET['type'] ?? 'file')); // 'file' or 'note'

if ($itemId <= 0) {
    http_response_code(400);
    echo 'Invalid target ID.';
    exit;
}

// Clear any open output buffers
while (ob_get_level()) {
    ob_end_clean();
}

if ($type === 'note') {
    // ── Note Downloader: Streams Note content as a .txt file ──
    $noteRepo = new NoteRepository();
    $note = $noteRepo->getUserNoteById($userId, $itemId);

    if (!$note) {
        http_response_code(404);
        echo 'Note not found or access denied.';
        exit;
    }

    $filename = sanitize($note['title'] ?: 'Untitled Note') . '.txt';
    $content  = $note['content'];
    $size     = strlen($content);

    ActivityLogger::log('note_downloaded', 'note', $itemId, ['title' => $note['title']], $userId);

    SecurityHeaders::sendFileDownload($filename, 'text/plain; charset=UTF-8', $size);
    echo $content;
    exit;
} else {
    // ── Standard File Downloader ──
    $fileRepo = new FileRepository();
    $file = $fileRepo->getUserFileById($userId, $itemId);

    if (!$file) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $fullPath = UPLOAD_PATH . '/' . $file['storage_path'];
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        http_response_code(404);
        echo 'File missing from storage disk.';
        exit;
    }

    ActivityLogger::fileDownloaded($userId, $itemId, $file['original_name']);

    $mime = $file['mime_type'] ?: 'application/octet-stream';
    $size = (int)$file['size_bytes'];
    $name = $file['original_name'];

    SecurityHeaders::sendFileDownload($name, $mime, $size);

    $handle = fopen($fullPath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
    exit;
}
<?php
/**
 * PERSONAL STORAGE — Automated Recycle Bin Purge Engine
 * Identifies expired soft-deleted items (over 7 days old) and permanently purges them.
 *
 * Safe to execute as a background process / system CRON job.
 * Usage:
 *   php scripts/cleanup_recycle_bin.php
 */

declare(strict_types=1);

// Standard security isolation: restrict execution purely to Command Line (CLI)
if (php_sapi_name() !== 'cli' && !defined('CRON_AUTHORIZED')) {
    http_response_code(403);
    echo "Direct HTTP access strictly forbidden. This engine runs purely via Cron/CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../app/bootstrap.php';

$now = date('Y-m-d H:i:s');
echo "====================================================\n";
echo "🔄 PERSONAL STORAGE Recycle Bin Cleanup (UTC: {$now})\n";
echo "====================================================\n\n";

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // ── 1. CLEANUP EXPIRED FILE RECORDS ──────────────────
    // Find all soft-deleted files where deletion_expiry_at has passed
    $stmt = $db->prepare(
        'SELECT `id`, `user_id`, `original_name`, `storage_path`
         FROM `files`
         WHERE `deleted_at` IS NOT NULL AND `deletion_expiry_at` <= NOW()'
    );
    $stmt->execute();
    $expiredFiles = $stmt->fetchAll();

    $filePurgeCount = 0;
    if (!empty($expiredFiles)) {
        foreach ($expiredFiles as $file) {
            $physicalPath = UPLOAD_PATH . '/' . $file['storage_path'];
            
            // Delete physical file safely
            if (file_exists($physicalPath) && is_file($physicalPath)) {
                if (@unlink($physicalPath)) {
                    echo "🗑️  Physical File Purged: {$file['original_name']} ({$file['storage_path']})\n";
                } else {
                    echo "⚠️  Failed to unlink file on storage disk: {$physicalPath}\n";
                }
            } else {
                echo "ℹ️  Physical File already missing: {$file['original_name']}\n";
            }

            // Remove database entry
            $delStmt = $db->prepare('DELETE FROM `files` WHERE `id` = :id');
            $delStmt->execute([':id' => $file['id']]);

            // Log event to global security feed
            ActivityLogger::log('cron_file_purge', 'file', $file['id'], [
                'original_name' => $file['original_name'],
                'reason'        => 'Auto-retention 7-day limit'
            ], (int)$file['user_id']);

            $filePurgeCount++;
        }
    }

    // ── 2. CLEANUP EXPIRED NOTES ───────────────────────
    // Soft-deleted notes where deletion_expiry_at has passed
    $stmt = $db->prepare(
        'SELECT `id`, `user_id`, `title` FROM `notes`
         WHERE `deleted_at` IS NOT NULL AND `deletion_expiry_at` <= NOW()'
    );
    $stmt->execute();
    $expiredNotes = $stmt->fetchAll();

    $notePurgeCount = 0;
    if (!empty($expiredNotes)) {
        foreach ($expiredNotes as $note) {
            $delStmt = $db->prepare('DELETE FROM `notes` WHERE `id` = :id');
            $delStmt->execute([':id' => $note['id']]);

            // Log event
            ActivityLogger::log('cron_note_purge', 'note', $note['id'], [
                'title'  => $note['title'] ?? 'Untitled Note',
                'reason' => 'Auto-retention 7-day limit'
            ], (int)$note['user_id']);

            $notePurgeCount++;
        }
    }

    $db->commit();

    echo "\n";
    echo "====================================================\n";
    echo "✅ Purge operation completed successfully.\n";
    echo "   Files Purged: {$filePurgeCount}\n";
    echo "   Notes Purged: {$notePurgeCount}\n";
    echo "====================================================\n\n";

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    appLog('Recycle Bin Purge Cron Failure: ' . $e->getMessage(), 'critical');
    echo "❌ Cleanup Engine Crashed: " . $e->getMessage() . "\n\n";
    exit(1);
}
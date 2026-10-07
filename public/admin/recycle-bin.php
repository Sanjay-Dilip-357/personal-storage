<?php
/**
 * PERSONAL STORAGE — Administrative Recycle Bin Panel
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Enforce admin check
AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');
$db        = Database::getConnection();
$error     = '';
$success   = '';

// ── Handle Action Requests ─────────────────────
if (requestMethod() === 'POST') {
    if (!Csrf::validate()) {
        $error = 'Security token validation failed. Please refresh and try again.';
    } else {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $type    = trim((string)($_POST['type'] ?? ''));
        $itemId  = (int)($_POST['item_id'] ?? 0);
        $action  = trim((string)($_POST['action'] ?? ''));

        if ($action === 'force_purge_expired') {
            // Trigger the clean-up routine safely
            if (!defined('CRON_AUTHORIZED')) {
                define('CRON_AUTHORIZED', true);
            }
            ob_start();
            include __DIR__ . '/../../scripts/cleanup_recycle_bin.php';
            $cronOutput = ob_get_clean();
            $success = 'Triggered system-wide purge sequence successfully.';
        } elseif ($itemId > 0 && in_array($type, ['file', 'note'], true)) {
            if ($action === 'admin_restore') {
                $table = ($type === 'file') ? 'files' : 'notes';
                $stmt = $db->prepare("UPDATE `{$table}` SET `deleted_at` = NULL, `deletion_expiry_at` = NULL WHERE `id` = :id");
                $stmt->execute([':id' => $itemId]);

                ActivityLogger::adminAction($adminId, 'admin_restore', $type, $itemId);
                $success = "Restored the {$type} successfully.";
            } elseif ($action === 'admin_delete') {
                if ($type === 'file') {
                    $stmt = $db->prepare('SELECT `storage_path` FROM `files` WHERE `id` = :id LIMIT 1');
                    $stmt->execute([':id' => $itemId]);
                    $file = $stmt->fetch();
                    if ($file) {
                        $physicalPath = UPLOAD_PATH . '/' . $file['storage_path'];
                        if (file_exists($physicalPath) && is_file($physicalPath)) {
                            @unlink($physicalPath);
                        }
                    }
                    $stmt = $db->prepare('DELETE FROM `files` WHERE `id` = :id');
                    $stmt->execute([':id' => $itemId]);
                } else {
                    $stmt = $db->prepare('DELETE FROM `notes` WHERE `id` = :id');
                    $stmt->execute([':id' => $itemId]);
                }

                ActivityLogger::adminAction($adminId, 'admin_purge', $type, $itemId);
                $success = "Permanently deleted the {$type} from storage disk.";
            }
        }
    }
}

// Fetch all deleted files across the entire platform
$deletedFiles = $db->query(
    'SELECT f.*, u.email as user_email
     FROM `files` f
     JOIN `users` u ON f.user_id = u.id
     WHERE f.deleted_at IS NOT NULL
     ORDER BY f.deleted_at DESC'
)->fetchAll();

// Fetch all deleted notes across the entire platform
$deletedNotes = $db->query(
    'SELECT n.*, u.email as user_email
     FROM `notes` n
     JOIN `users` u ON n.user_id = u.id
     WHERE n.deleted_at IS NOT NULL
     ORDER BY n.deleted_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Purge Control — <?= $appName ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/recycle-bin.css') ?>">
    <style>
        :root {
            --color-primary: #8b5cf6;
            --color-primary-light: #a78bfa;
            --gradient-primary: linear-gradient(135deg, #8b5cf6, #ec4899);
        }
    </style>
</head>
<body class="dashboard-body">

    <div class="app-layout">

                <!-- ═══════════════════════════════════════ -->
        <!-- Responsive Sidebar Navigation (Admin)  -->
        <!-- ═══════════════════════════════════════ -->
        <aside class="sidebar" id="sidebar">
            <!-- Mobile Close Button -->
            <button class="sidebar-close-btn" id="sidebar-close" aria-label="Close sidebar">✕</button>

            <!-- Admin Brief (Top-most element) -->
            <div class="sidebar-user" style="background: linear-gradient(180deg, rgba(139, 92, 246, 0.05), transparent);">
                <div class="user-avatar" style="background: var(--gradient-primary)">A</div>
                <div class="user-info">
                    <span class="user-name"><?= $adminName ?></span>
                    <span class="user-email">System Administrator</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav">
                <div class="nav-group-title">ADMINISTRATIVE OVERVIEW</div>
                <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Admin Dashboard</span>
                </a>
                <a href="users.php" class="nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'user.php']) ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span>Users Control</span>
                </a>
                <a href="storage.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'storage.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span>Storage Configuration</span>
                </a>

                <div class="nav-group-title">SECURITY ENGINE</div>
                <a href="activity.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'activity.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📜</span>
                    <span>System Activity Logs</span>
                </a>
                <a href="recycle-bin.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'recycle-bin.php' ? 'active' : '' ?>">
                    <span class="nav-icon">♻️</span>
                    <span>System Purge Bin</span>
                </a>

                <div class="nav-group-title">SYSTEM</div>
                <a href="../index.php" class="nav-item">
                    <span class="nav-icon">🌐</span>
                    <span>View Public Landing</span>
                </a>
                <a href="logout.php" class="nav-item nav-item--danger">
                    <span class="nav-icon">🚪</span>
                    <span>Log Out</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            
            <header class="top-bar">
                <div class="top-bar-left">
                    <div>
                        <h1 class="page-title">Platform Purge Control</h1>
                        <p class="page-subtitle">Central oversight of soft-deleted storage across the platform</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <form action="recycle-bin.php" method="POST">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="force_purge_expired">
                        <button type="submit" class="btn btn--danger btn--sm">⚡ Force Purge Expired</button>
                    </form>
                </div>
            </header>

            <div class="content-body">

                <?php if (!empty($success)): ?>
                    <div class="countdown-badge countdown--safe mb-4" style="width:100%; display:block; padding:12px;">✅ <?= e($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="countdown-badge mb-4" style="width:100%; display:block; padding:12px;">❌ <?= e($error) ?></div>
                <?php endif; ?>

                <!-- Deleted Files Across System -->
                <h3 class="nav-group-title mb-4">PLATFORM SOFT-DELETED FILES (<?= count($deletedFiles) ?>)</h3>
                <div class="card card--glass recycle-table-card mb-8">
                    <table class="recycle-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Owner</th>
                                <th>Size</th>
                                <th>Deleted On</th>
                                <th style="text-align:right;">Platform Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deletedFiles)): ?>
                                <tr><td colspan="5" style="text-align:center; color:var(--color-text-muted);">No soft-deleted files on the platform.</td></tr>
                            <?php else: foreach ($deletedFiles as $file): ?>
                                <tr>
                                    <td><strong><?= e($file['original_name']) ?></strong></td>
                                    <td><?= e($file['user_email']) ?></td>
                                    <td><?= formatBytes((int)$file['size_bytes']) ?></td>
                                    <td><?= e($file['deleted_at']) ?></td>
                                    <td style="text-align:right;">
                                        <form action="recycle-bin.php" method="POST" style="display:inline-block;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="type" value="file">
                                            <input type="hidden" name="item_id" value="<?= $file['id'] ?>">
                                            <button type="submit" name="action" value="admin_restore" class="recycle-btn recycle-btn--restore">Restore</button>
                                            <button type="submit" name="action" value="admin_delete" class="recycle-btn recycle-btn--purge">Purge</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Deleted Notes Across System -->
                <h3 class="nav-group-title mb-4">PLATFORM SOFT-DELETED NOTES (<?= count($deletedNotes) ?>)</h3>
                <div class="card card--glass recycle-table-card">
                    <table class="recycle-table">
                        <thead>
                            <tr>
                                <th>Note Title</th>
                                <th>Owner</th>
                                <th>Deleted On</th>
                                <th style="text-align:right;">Platform Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deletedNotes)): ?>
                                <tr><td colspan="4" style="text-align:center; color:var(--color-text-muted);">No soft-deleted notes on the platform.</td></tr>
                            <?php else: foreach ($deletedNotes as $note): ?>
                                <tr>
                                    <td><strong><?= e($note['title'] ?: 'Untitled Note') ?></strong></td>
                                    <td><?= e($note['user_email']) ?></td>
                                    <td><?= e($note['deleted_at']) ?></td>
                                    <td style="text-align:right;">
                                        <form action="recycle-bin.php" method="POST" style="display:inline-block;">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="type" value="note">
                                            <input type="hidden" name="item_id" value="<?= $note['id'] ?>">
                                            <button type="submit" name="action" value="admin_restore" class="recycle-btn recycle-btn--restore">Restore</button>
                                            <button type="submit" name="action" value="admin_delete" class="recycle-btn recycle-btn--purge">Purge</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
<?php
/**
 * PERSONAL STORAGE — Admin Users Directory
 */

require_once __DIR__ . '/../../app/bootstrap.php';

AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');
$db        = Database::getConnection();
$adminId   = (int)$_SESSION['admin_id'];

$error   = '';
$success = '';

// ── Handle Actions (Enable / Disable / Delete) ──
if (requestMethod() === 'POST') {
    if (!Csrf::validate()) {
        $error = 'Security token expired. Please refresh and try again.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId > 0) {
            if ($action === 'toggle_status') {
                $currentStatus = trim((string)($_POST['current_status'] ?? 'active'));
                $newStatus = ($currentStatus === 'active') ? 'disabled' : 'active';

                $stmt = $db->prepare('UPDATE `users` SET `status` = :st WHERE `id` = :id');
                $stmt->execute([':st' => $newStatus, ':id' => $targetUserId]);

                ActivityLogger::adminAction($adminId, 'user_status_changed', 'user', $targetUserId, ['new_status' => $newStatus]);
                $success = "User status updated to '{$newStatus}'.";
            } elseif ($action === 'delete_user') {
                // 1. Physically delete all user files from storage disk
                $stmt = $db->prepare('SELECT `storage_path` FROM `files` WHERE `user_id` = :uid');
                $stmt->execute([':uid' => $targetUserId]);
                $userFiles = $stmt->fetchAll();

                foreach ($userFiles as $f) {
                    $physicalPath = UPLOAD_PATH . '/' . $f['storage_path'];
                    if (file_exists($physicalPath) && is_file($physicalPath)) {
                        @unlink($physicalPath);
                    }
                }

                // 2. Delete user record (Cascade will clean files, notes, settings, logs)
                $stmt = $db->prepare('DELETE FROM `users` WHERE `id` = :id');
                $stmt->execute([':id' => $targetUserId]);

                ActivityLogger::adminAction($adminId, 'user_deleted', 'user', $targetUserId);
                $success = "User and all associated storage files permanently removed.";
            }
        }
    }
}

// ── Search & Filter ────────────────────────────
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT u.*,
               COALESCE((SELECT SUM(size_bytes) FROM `files` WHERE `user_id` = u.id), 0) AS `storage_used`,
               COALESCE((SELECT COUNT(*) FROM `files` WHERE `user_id` = u.id AND `deleted_at` IS NULL), 0) AS `active_files_count`,
               COALESCE((SELECT COUNT(*) FROM `notes` WHERE `user_id` = u.id AND `deleted_at` IS NULL), 0) AS `active_notes_count`
        FROM `users` u
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE :search_name OR u.email LIKE :search_email)";
    $params[':search_name']  = '%' . $search . '%';
    $params[':search_email'] = '%' . $search . '%';
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'pending', 'disabled'], true)) {
    $sql .= " AND u.status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Global default quota for display
$settingsRepo = new StorageSettingsRepository();
$globalQuota = (int)$settingsRepo->getSetting('default_storage_quota', 10737418240);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Control — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/recycle-bin.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
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
                        <h1 class="page-title">Users Control Directory</h1>
                        <p class="page-subtitle">Manage accounts, status permissions, and individual storage usage</p>
                    </div>
                </div>
            </header>

            <div class="content-body">

                <?php if (!empty($success)): ?>
                    <div class="countdown-badge countdown--safe mb-4" style="width:100%; display:block; padding:12px;">✅ <?= e($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="countdown-badge mb-4" style="width:100%; display:block; padding:12px;">❌ <?= e($error) ?></div>
                <?php endif; ?>

                <!-- Filter Toolbar -->
                <form action="users.php" method="GET" class="files-toolbar mb-4">
                    <div class="notes-search-bar" style="max-width: 320px;">
                        <input type="text" name="search" class="form-input" placeholder="Search by name or email..." value="<?= e($search) ?>">
                    </div>

                    <div style="display:flex; gap:8px;">
                        <select name="status" class="form-input" style="width: auto;" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                            <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>Disabled Only</option>
                        </select>
                        <button type="submit" class="btn btn--primary btn--sm">Search</button>
                        <?php if ($search !== '' || $statusFilter !== ''): ?>
                            <a href="users.php" class="btn btn--secondary btn--sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Users Table -->
                <div class="card card--glass recycle-table-card">
                    <table class="recycle-table">
                        <thead>
                            <tr>
                                <th>User Account</th>
                                <th>Status</th>
                                <th>Storage Used / Quota</th>
                                <th>Files</th>
                                <th>Notes</th>
                                <th>Joined</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="7" style="text-align:center; color:var(--color-text-muted); padding:30px;">No user accounts found matching your query.</td></tr>
                            <?php else: foreach ($users as $u):
                                $userQuota = (int)$settingsRepo->getUserSetting((int)$u['id'], 'default_storage_quota', $globalQuota);
                                $usedBytes = (int)$u['storage_used'];
                                $pct = ($userQuota > 0) ? min(100, round(($usedBytes / $userQuota) * 100)) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="item-name-cell">
                                            <div class="user-avatar" style="width:32px; height:32px; font-size:12px; background:var(--gradient-primary);">
                                                <?= mb_strtoupper(mb_substr($u['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= e($u['full_name']) ?></strong><br>
                                                <span style="font-size:11px; color:var(--color-text-muted);"><?= e($u['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-status--<?= e($u['status']) ?>">
                                            <?= e($u['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; font-weight:600;"><?= formatBytes($usedBytes) ?> / <?= formatBytes($userQuota) ?></div>
                                        <div class="storage-bar" style="height:4px; margin-top:4px; width:120px;">
                                            <div class="storage-bar-fill" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </td>
                                    <td><?= $u['active_files_count'] ?></td>
                                    <td><?= $u['active_notes_count'] ?></td>
                                    <td style="font-size:12px; color:var(--color-text-muted);">
                                        <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="action-btn-group">
                                            <a href="user.php?id=<?= $u['id'] ?>" class="action-btn action-btn--view" title="Manage User & Overrides">Inspect</a>
                                            
                                            <form action="users.php" method="POST" style="display:inline;">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= e($u['status']) ?>">
                                                <button type="submit" class="action-btn action-btn--toggle" title="<?= $u['status'] === 'active' ? 'Disable Account' : 'Enable Account' ?>">
                                                    <?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                                </button>
                                            </form>

                                            <form action="users.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete the user and PERMANENTLY ERASE all their uploaded files from the disk.');">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="action-btn action-btn--delete" title="Delete User">Delete</button>
                                            </form>
                                        </div>
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
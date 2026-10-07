<?php
/**
 * PERSONAL STORAGE — System Activity Audit Logs
 */

require_once __DIR__ . '/../../app/bootstrap.php';

AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');
$adminId   = (int)$_SESSION['admin_id'];
$db        = Database::getConnection();

$error   = '';
$success = '';

// ── Handle Clear / Purge Logs Actions ───────────
if (requestMethod() === 'POST') {
    if (!Csrf::validate()) {
        $error = 'Security token expired. Please refresh and try again.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'clear_all_logs') {
            // Delete all records from activity_logs
            $db->exec('DELETE FROM `activity_logs`');

            // Log that the purge happened so there is always a record of who cleared it
            ActivityLogger::adminAction($adminId, 'logs_cleared', 'system', null, [
                'action_details' => 'Purged all system activity logs',
                'cleared_by'     => $adminName,
            ]);

            $success = 'All system activity logs have been successfully cleared.';
        } elseif ($action === 'purge_old_logs') {
            // Calculate date threshold in PHP to avoid INTERVAL parameterization issues
            $days = (int)($_POST['older_than_days'] ?? 30);
            $threshold = date('Y-m-d H:i:s', time() - ($days * 86400));

            $stmt = $db->prepare('DELETE FROM `activity_logs` WHERE `created_at` < :threshold');
            $stmt->execute([':threshold' => $threshold]);
            $deletedCount = $stmt->rowCount();

            ActivityLogger::adminAction($adminId, 'logs_purged_older', 'system', null, [
                'deleted_count' => $deletedCount,
                'older_than'    => "{$days} days",
                'cleared_by'    => $adminName,
            ]);

            $success = "Cleared {$deletedCount} log entries older than {$days} days.";
        }
    }
}

// ── Search & Filter ────────────────────────────
$search       = trim((string)($_GET['search'] ?? ''));
$actionFilter = trim((string)($_GET['action_filter'] ?? ''));

$sql = "SELECT l.*, u.email as user_email, u.full_name as user_name, a.username as admin_username
        FROM `activity_logs` l
        LEFT JOIN `users` u ON l.user_id = u.id
        LEFT JOIN `admin_users` a ON l.admin_id = a.id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (l.ip_address LIKE :search_ip OR u.email LIKE :search_email OR a.username LIKE :search_admin)";
    $params[':search_ip']    = '%' . $search . '%';
    $params[':search_email'] = '%' . $search . '%';
    $params[':search_admin'] = '%' . $search . '%';
}

if ($actionFilter !== '') {
    $sql .= " AND l.action = :action";
    $params[':action'] = $actionFilter;
}

$sql .= " ORDER BY l.created_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Total log count
$totalLogs = (int)$db->query('SELECT COUNT(*) FROM `activity_logs`')->fetchColumn();

// Distinct actions for dropdown filter
$actionsList = $db->query('SELECT DISTINCT `action` FROM `activity_logs` ORDER BY `action` ASC')->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — <?= $appName ?></title>
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
                        <h1 class="page-title">Security Activity Audit Trail</h1>
                        <p class="page-subtitle"><?= $totalLogs ?> total recorded system events</p>
                    </div>
                </div>

                <!-- Log Purge Action Buttons in Top Bar -->
                <div class="top-bar-actions">
                    <form action="activity.php" method="POST" style="display:inline;" onsubmit="return confirm('Purge activity logs older than 30 days?');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="purge_old_logs">
                        <input type="hidden" name="older_than_days" value="30">
                        <button type="submit" class="btn btn--secondary btn--sm">Clean &gt; 30 Days</button>
                    </form>

                    <form action="activity.php" method="POST" style="display:inline;" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to permanently clear ALL activity logs? This action cannot be undone.');">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="clear_all_logs">
                        <button type="submit" class="btn btn--danger btn--sm">🗑️ Clear All Logs</button>
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

                <!-- Filter Toolbar -->
                <form action="activity.php" method="GET" class="files-toolbar mb-4">
                    <div class="notes-search-bar" style="max-width: 320px;">
                        <input type="text" name="search" class="form-input" placeholder="Search IP or Actor Email..." value="<?= e($search) ?>">
                    </div>

                    <div style="display:flex; gap:8px;">
                        <select name="action_filter" class="form-input" style="width: auto;" onchange="this.form.submit()">
                            <option value="">All Action Types</option>
                            <?php foreach ($actionsList as $act): ?>
                                <option value="<?= e($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', e($act))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn--primary btn--sm">Filter</button>
                        <?php if ($search !== '' || $actionFilter !== ''): ?>
                            <a href="activity.php" class="btn btn--secondary btn--sm">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Activity Log Table -->
                <div class="card card--glass recycle-table-card">
                    <table class="recycle-table">
                        <thead>
                            <tr>
                                <th>Timestamp (UTC)</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Entity Target</th>
                                <th>Context Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:30px;">No activity records found matching filters.</td></tr>
                            <?php else: foreach ($logs as $l): ?>
                                <tr>
                                    <td style="font-size:12px; font-family:var(--font-mono); color:var(--color-text-muted);">
                                        <?= date('Y-m-d H:i:s', strtotime($l['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($l['admin_username'])): ?>
                                            <span style="color:#ec4899; font-weight:600;">👑 <?= e($l['admin_username']) ?></span>
                                        <?php elseif (!empty($l['user_email'])): ?>
                                            <span style="color:#38bdf8;"><?= e($l['user_email']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">System Engine</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:rgba(139, 92, 246, 0.15); color:#a78bfa; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600;">
                                            <?= ucfirst(str_replace('_', ' ', e($l['action']))) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px; color:var(--color-text-secondary);">
                                        <?= !empty($l['entity_type']) ? ucfirst(e($l['entity_type'])) . ' #' . e($l['entity_id'] ?? '') : '—' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($l['details'])): ?>
                                            <span class="log-details-code" title="<?= e($l['details']) ?>">
                                                <?= e($l['details']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px; font-family:var(--font-mono); color:var(--color-text-muted);">
                                        <?= e($l['ip_address']) ?>
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
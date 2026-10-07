<?php
/**
 * PERSONAL STORAGE — Admin Dashboard Central Panel
 */

require_once __DIR__ . '/../../app/bootstrap.php';

// Enforce admin login
AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');

$db = Database::getConnection();

// --- Live Stats Queries ---
// 1. Regular Users
$totalUsers = (int)$db->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
$activeUsers = (int)$db->query('SELECT COUNT(*) FROM `users` WHERE `status` = "active"')->fetchColumn();
$pendingUsers = (int)$db->query('SELECT COUNT(*) FROM `users` WHERE `status` = "pending"')->fetchColumn();

// 2. Files System Stats
$totalFiles = (int)$db->query('SELECT COUNT(*) FROM `files`')->fetchColumn();
$totalStorageBytes = (float)$db->query('SELECT COALESCE(SUM(size_bytes), 0) FROM `files`')->fetchColumn();

// 3. System activity count
$totalLogsCount = (int)$db->query('SELECT COUNT(*) FROM `activity_logs`')->fetchColumn();

// Fetch last 5 registered users
$recentUsers = $db->query(
    'SELECT id, full_name, email, status, created_at FROM `users` 
     ORDER BY `created_at` DESC LIMIT 5'
)->fetchAll();

// Fetch last 6 system security logs
$recentSecurityLogs = $db->query(
    'SELECT l.*, u.email as user_email, a.username as admin_username 
     FROM `activity_logs` l 
     LEFT JOIN `users` u ON l.user_id = u.id
     LEFT JOIN `admin_users` a ON l.admin_id = a.id
     ORDER BY l.created_at DESC LIMIT 6'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?= $appName ?></title>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <style>
        /* Admin panel styles overrides */
        :root {
            --color-primary: #8b5cf6;
            --color-primary-light: #a78bfa;
            --gradient-primary: linear-gradient(135deg, #8b5cf6, #ec4899);
        }
        .admin-nav-badge {
            background: rgba(139, 92, 246, 0.2);
            color: #c084fc;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
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

        <main class="main-content">
            
            <header class="top-bar">
                <div class="top-bar-left">
                    <div>
                        <h1 class="page-title">Admin Control Panel</h1>
                        <p class="page-subtitle">Central Administration Dashboard</p>
                    </div>
                </div>
                <div>
                    <span class="admin-nav-badge">Audit Logging Online</span>
                </div>
            </header>

            <div class="content-body">

                <!-- 4 Administrative Metric Cards -->
                <div class="stats-grid">
                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa;">👥</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $totalUsers ?></span>
                            <span class="stat-label">Registered Users</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(34, 197, 94, 0.1); color: #4ade80;">✓</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $activeUsers ?></span>
                            <span class="stat-label">Active Users</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(14, 165, 233, 0.1); color: #38bdf8;">📁</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $totalFiles ?></span>
                            <span class="stat-label">System-Wide Files</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.1); color: #fbbf24;">💾</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= formatBytes((int)$totalStorageBytes) ?></span>
                            <span class="stat-label">Active Space Occupied</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">

                    <!-- Recent Users -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">Recent Registrations</h3>
                            <span class="badge badge--success">Users Table</span>
                        </div>

                        <div class="recent-list">
                            <?php foreach ($recentUsers as $user): ?>
                                <div class="recent-item" style="background: var(--color-bg-secondary)">
                                    <div class="file-icon-box" style="background: rgba(139, 92, 246, 0.15)">👤</div>
                                    <div class="recent-item-meta">
                                        <span class="recent-item-name"><?= e($user['full_name']) ?></span>
                                        <span class="recent-item-sub"><?= e($user['email']) ?></span>
                                    </div>
                                    <span class="badge" style="background: <?= $user['status'] === 'active' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(245, 158, 11, 0.15)' ?>; color: <?= $user['status'] === 'active' ? '#4ade80' : '#fbbf24' ?>; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                                        <?= e($user['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent System Activity Logs -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">Global Security Activity Feed</h3>
                            <span class="badge badge--success">Activity Logs</span>
                        </div>

                        <div class="activity-timeline">
                            <?php foreach ($recentSecurityLogs as $log): ?>
                                <div class="activity-event">
                                    <div class="activity-dot" style="background: var(--color-primary); box-shadow: 0 0 8px var(--color-primary);"></div>
                                    <div class="activity-info">
                                        <span class="activity-action">
                                            <strong><?= e($log['admin_username'] ?: $log['user_email'] ?: 'System') ?></strong>
                                            &mdash; 
                                            <?= match($log['action']) {
                                                'admin_login' => 'Authenticated Control Gateway',
                                                'login' => 'User Logged In',
                                                'registration' => 'Account Created',
                                                'email_verified' => 'Security OTP Completed',
                                                default => ucfirst(str_replace('_', ' ', e($log['action'])))
                                            } ?>
                                        </span>
                                        <span class="activity-time"><?= date('M d, H:i:s', strtotime($log['created_at'])) ?> &bull; IP: <?= e($log['ip_address']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>

</body>
</html>
<?php
/**
 * PERSONAL STORAGE — User Recycle Bin
 */

require_once __DIR__ . '/../app/bootstrap.php';

AuthMiddleware::requireUser();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$userId    = (int)$_SESSION['user_id'];
$userName  = e($_SESSION['user_name'] ?? 'User');
$userEmail = e($_SESSION['user_email'] ?? '');

$storageService = new StorageService();
$metrics = $storageService->getUserDashboardMetrics($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/recycle-bin.css') ?>">
</head>
<body class="dashboard-body">

    <div class="app-layout">

                <!-- ═══════════════════════════════════════ -->
        <!-- Responsive Sidebar Navigation          -->
        <!-- ═══════════════════════════════════════ -->
        <aside class="sidebar" id="sidebar">
            <!-- Mobile Close Button -->
            <button class="sidebar-close-btn" id="sidebar-close" aria-label="Close sidebar">✕</button>

            <!-- User Brief (Top-most element) -->
            <div class="sidebar-user">
                <div class="user-avatar" id="sidebar-avatar"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
                <div class="user-info">
                    <span class="user-name" id="sidebar-username"><?= $userName ?></span>
                    <span class="user-email"><?= $userEmail ?></span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav">
                <div class="nav-group-title">STORAGE VAULT</div>
                <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="files.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'files.php' && !isset($_GET['category']) ? 'active' : '' ?>">
                    <span class="nav-icon">📁</span>
                    <span>All Files</span>
                    <span class="nav-badge" id="sidebar-file-count"><?= $metrics['total_files'] ?></span>
                </a>
                <a href="files.php?category=document" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'document' ? 'active' : '' ?>">
                    <span class="nav-icon">📄</span>
                    <span>Documents</span>
                    <span class="nav-badge"><?= $metrics['categories']['document']['count'] ?></span>
                </a>
                <a href="files.php?category=image" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'image' ? 'active' : '' ?>">
                    <span class="nav-icon">🖼️</span>
                    <span>Images</span>
                    <span class="nav-badge"><?= $metrics['categories']['image']['count'] ?></span>
                </a>
                <a href="files.php?category=video" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'video' ? 'active' : '' ?>">
                    <span class="nav-icon">🎥</span>
                    <span>Videos</span>
                    <span class="nav-badge"><?= $metrics['categories']['video']['count'] ?></span>
                </a>
                <a href="files.php?category=other" class="nav-item <?= isset($_GET['category']) && $_GET['category'] === 'other' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Other Files</span>
                    <span class="nav-badge"><?= $metrics['categories']['other']['count'] ?></span>
                </a>
                <a href="notes.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'notes.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📝</span>
                    <span>Notes</span>
                    <span class="nav-badge" id="sidebar-note-count"><?= $metrics['total_notes'] ?></span>
                </a>

                <div class="nav-group-title">ACCOUNT</div>
                <a href="recycle-bin.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'recycle-bin.php' ? 'active' : '' ?>">
                    <span class="nav-icon">♻️</span>
                    <span>Recycle Bin</span>
                    <?php if ($metrics['recycle_bin_count'] > 0): ?>
                        <span class="nav-badge nav-badge--warning" id="sidebar-recycle-count"><?= $metrics['recycle_bin_count'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="nav-item nav-item--danger">
                    <span class="nav-icon">🚪</span>
                    <span>Log Out</span>
                </a>
            </nav>

            <!-- Mini Storage Meter in Sidebar -->
            <div class="sidebar-storage">
                <div class="storage-meta">
                    <span>Used Space</span>
                    <span><strong><?= $metrics['percentage_used'] ?>%</strong></span>
                </div>
                <div class="storage-bar">
                    <div class="storage-bar-fill" style="width: <?= $metrics['percentage_used'] ?>%;"></div>
                </div>
                <span class="storage-legend"><?= $metrics['used_formatted'] ?> of <?= $metrics['quota_formatted'] ?></span>
            </div>
        </aside>

        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Main Content -->
        <main class="main-content">

            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle-btn" id="sidebar-open" aria-label="Toggle menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div>
                        <h1 class="page-title">Recycle Bin</h1>
                        <p class="page-subtitle">Soft-deleted items are safely recovered or permanently erased here</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <div class="retention-info-badge">♻️ 7-Day Auto Purge Enabled</div>
                </div>
            </header>

            <div class="content-body">

                <!-- Type Selector Tab Controls -->
                <div class="recycle-tabs">
                    <button class="recycle-tab-btn active" id="tab-files" data-type="file">Soft-Deleted Files</button>
                    <button class="recycle-tab-btn" id="tab-notes" data-type="note">Soft-Deleted Notes</button>
                </div>

                <!-- Empty State -->
                <div id="recycle-empty" class="notes-empty" style="display:none;">
                    <div class="empty-icon">🍃</div>
                    <h3 class="empty-title">Recycle Bin is empty</h3>
                    <p class="empty-text">No deleted items are scheduled for removal.</p>
                </div>

                <!-- Loading -->
                <div id="recycle-loading" class="notes-loading" style="display:none;">
                    <div class="loading-spinner"></div>
                    <p>Scanning bin...</p>
                </div>

                <!-- Items Table list container -->
                <div class="card card--glass recycle-table-card" id="recycle-table-wrapper" style="display:none;">
                    <table class="recycle-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Size</th>
                                <th>Deleted On</th>
                                <th>Permanent Purge Countdown</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recycle-tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/dashboard.js') ?>"></script>
    <script src="<?= asset('js/recycle-bin.js') ?>"></script>
</body>
</html>
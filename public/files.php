<?php
/**
 * PERSONAL STORAGE — File Manager
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

$activeCategory = e($_GET['category'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/files.css') ?>">
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
                        <h1 class="page-title">
                            <?= $activeCategory ? ucfirst(e($activeCategory)) . 's' : 'All Files' ?>
                        </h1>
                        <p class="page-subtitle"><?= $metrics['total_files'] ?> files • <?= $metrics['used_formatted'] ?> used</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <label class="btn btn--primary btn--sm upload-btn-label">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Upload File</span>
                        <input type="file" id="file-upload-input" class="upload-hidden-input">
                    </label>
                </div>
            </header>

            <div class="content-body">

                <!-- Search & View Toggle -->
                <div class="files-toolbar">
                    <div class="notes-search-bar" style="max-width:360px;">
                        <svg class="notes-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="files-search" class="form-input notes-search-input" placeholder="Search files...">
                    </div>
                    <div class="view-toggle">
                        <button class="view-btn active" data-view="grid" title="Grid View">▦</button>
                        <button class="view-btn" data-view="list" title="List View">☰</button>
                    </div>
                </div>

                <!-- Upload Drop Zone -->
                <div id="drop-zone" class="drop-zone" style="display:none;">
                    <div class="drop-zone-inner">
                        <div class="drop-icon">📤</div>
                        <p>Drop your file here to upload</p>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="upload-progress" class="upload-progress" style="display:none;">
                    <div class="upload-progress-info">
                        <span id="upload-filename">Uploading...</span>
                        <span id="upload-percent">0%</span>
                    </div>
                    <div class="upload-progress-bar">
                        <div class="upload-progress-fill" id="upload-fill"></div>
                    </div>
                </div>

                <!-- Files Container -->
                <div id="files-container" class="files-grid"></div>

                <!-- Empty State -->
                <div id="files-empty" class="notes-empty" style="display:none;">
                    <div class="empty-icon">📂</div>
                    <h3 class="empty-title">No files yet</h3>
                    <p class="empty-text">Upload your first file to get started.</p>
                </div>

                <!-- Loading -->
                <div id="files-loading" class="notes-loading" style="display:none;">
                    <div class="loading-spinner"></div>
                    <p>Loading files...</p>
                </div>

            </div>
        </main>
    </div>

    <!-- Rename Modal -->
    <div class="modal-overlay" id="rename-modal">
        <div class="modal-card" style="max-width:440px;">
            <div class="modal-header">
                <h2 class="modal-title">Rename File</h2>
                <button class="modal-close" id="rename-close" aria-label="Close">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="rename-input">New Filename</label>
                    <input type="text" id="rename-input" class="form-input" placeholder="Enter new name">
                </div>
                <div class="form-error" id="rename-error"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn--secondary" id="rename-cancel">Cancel</button>
                <button class="btn btn--primary" id="rename-save">Rename</button>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/dashboard.js') ?>"></script>
    <script src="<?= asset('js/files.js') ?>"></script>
    <script>
        // Pass active category from PHP to JS
        window.PS_ACTIVE_CATEGORY = '<?= $activeCategory ?>';
    </script>
</body>
</html>
<?php
/**
 * PERSONAL STORAGE — Notes Page
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
    <title>Notes — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/notes.css') ?>">
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
                        <h1 class="page-title">My Notes</h1>
                        <p class="page-subtitle">Create, organize, and search your private notes</p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <button class="btn btn--primary btn--sm" id="btn-new-note">
                        <span>+ New Note</span>
                    </button>
                </div>
            </header>

            <div class="content-body">

                <!-- Search Bar -->
                <div class="notes-search-bar">
                    <svg class="notes-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="notes-search" class="form-input notes-search-input" placeholder="Search notes by title or content...">
                </div>

                <!-- Notes Grid -->
                <div id="notes-container" class="notes-grid">
                    <!-- Notes are loaded dynamically via JavaScript -->
                </div>

                <!-- Empty State -->
                <div id="notes-empty" class="notes-empty" style="display:none;">
                    <div class="empty-icon">📝</div>
                    <h3 class="empty-title">No notes yet</h3>
                    <p class="empty-text">Create your first private note to get started.</p>
                    <button class="btn btn--primary btn--sm mt-4" id="btn-new-note-empty">+ Create Note</button>
                </div>

                <!-- Loading State -->
                <div id="notes-loading" class="notes-loading" style="display:none;">
                    <div class="loading-spinner"></div>
                    <p>Loading notes...</p>
                </div>

            </div>
        </main>
    </div>

    <!-- Note Editor Modal -->
    <div class="modal-overlay" id="note-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">New Note</h2>
                <button class="modal-close" id="modal-close" aria-label="Close">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="note-title">Title <span class="text-muted">(optional)</span></label>
                    <input type="text" id="note-title" class="form-input" placeholder="Untitled Note" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label" for="note-content">Content</label>
                    <textarea id="note-content" class="form-input notes-textarea" placeholder="Start writing..." rows="12"></textarea>
                </div>
                <div class="form-error" id="note-error"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn--secondary" id="modal-cancel">Cancel</button>
                <button class="btn btn--primary" id="modal-save">Save Note</button>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/dashboard.js') ?>"></script>
    <script src="<?= asset('js/notes.js') ?>"></script>
</body>
</html>
<?php
/**
 * PERSONAL STORAGE — User Dashboard
 * Complete metrics overview, quick file actions, and storage visualization.
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Enforce session check
AuthMiddleware::requireUser();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName  = e(Config::get('APP_NAME', 'Personal Storage'));
$userId   = (int)$_SESSION['user_id'];
$userName = e($_SESSION['user_name'] ?? 'User');
$userEmail = e($_SESSION['user_email'] ?? '');

// Load live metrics from storage engine
$storageService = new StorageService();
$metrics        = $storageService->getUserDashboardMetrics($userId);
$fileRepo       = new FileRepository();
$recentFiles    = $fileRepo->getRecentFiles($userId, 5);

// Fetch recent activity log for this user
$db = Database::getConnection();
$stmt = $db->prepare(
    'SELECT * FROM `activity_logs` WHERE `user_id` = :uid ORDER BY `created_at` DESC LIMIT 6'
);
$stmt->execute([':uid' => $userId]);
$recentActivities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
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

        <!-- Sidebar overlay for mobile -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- ═══════════════════════════════════════ -->
        <!-- Main Dashboard Content Area            -->
        <!-- ═══════════════════════════════════════ -->
        <main class="main-content">

            <!-- Top Header -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle-btn" id="sidebar-open" aria-label="Toggle navigation menu">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div>
                        <h1 class="page-title">Storage Overview</h1>
                        <p class="page-subtitle">Welcome back, <strong><?= $userName ?></strong></p>
                    </div>
                </div>

                <div class="top-bar-actions">
                    <a href="notes.php?action=new" class="btn btn--secondary btn--sm">
                        <span>+ New Note</span>
                    </a>
                    <a href="files.php?action=upload" class="btn btn--primary btn--sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Upload File</span>
                    </a>
                </div>
            </header>

            <div class="content-body">

                <!-- ── Storage Quota Banner Card ── -->
                <div class="card card--glass quota-banner">
                    <div class="quota-banner-header">
                        <div>
                            <span class="quota-badge">Encrypted Quota</span>
                            <h2 class="quota-title"><?= $metrics['used_formatted'] ?> <span class="quota-total">/ <?= $metrics['quota_formatted'] ?></span></h2>
                        </div>
                        <div class="quota-free">
                            <span class="quota-free-label">Remaining Space</span>
                            <span class="quota-free-value"><?= $metrics['remaining_formatted'] ?></span>
                        </div>
                    </div>

                    <div class="quota-progress-track">
                        <div class="quota-progress-fill" style="width: <?= $metrics['percentage_used'] ?>%;"></div>
                    </div>

                    <div class="quota-breakdown-pills">
                        <div class="quota-pill"><span class="dot dot-cyan"></span> Documents: <?= formatBytes($metrics['categories']['document']['size']) ?></div>
                        <div class="quota-pill"><span class="dot dot-violet"></span> Images: <?= formatBytes($metrics['categories']['image']['size']) ?></div>
                        <div class="quota-pill"><span class="dot dot-green"></span> Videos: <?= formatBytes($metrics['categories']['video']['size']) ?></div>
                        <div class="quota-pill"><span class="dot dot-amber"></span> Others: <?= formatBytes($metrics['categories']['other']['size']) ?></div>
                    </div>
                </div>

                <!-- ── Stat Metric Cards ── -->
                <div class="stats-grid">
                    <div class="card stat-card">
                        <div class="stat-icon-wrapper stat-icon--cyan">📁</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['total_files'] ?></span>
                            <span class="stat-label">Total Files</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper stat-icon--violet">📝</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['total_notes'] ?></span>
                            <span class="stat-label">Active Notes</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper stat-icon--green">⚡</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['percentage_used'] ?>%</span>
                            <span class="stat-label">Quota Consumed</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper stat-icon--amber">♻️</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['recycle_bin_count'] ?></span>
                            <span class="stat-label">Recycle Bin</span>
                        </div>
                    </div>
                </div>

                <!-- ── Two Column Activity & Recent Files Section ── -->
                <div class="dashboard-grid">

                    <!-- Recent Files -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">Recent Files</h3>
                            <a href="files.php" class="card-action-link">View All →</a>
                        </div>

                        <?php if (empty($recentFiles)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📂</div>
                                <p class="empty-text">No files uploaded yet.</p>
                                <a href="files.php?action=upload" class="btn btn--secondary btn--sm mt-4">Upload First File</a>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentFiles as $file): ?>
                                    <div class="recent-item">
                                        <div class="file-icon-box file-icon--<?= e($file['category']) ?>">
                                            <?= match($file['category']) {
                                                'document' => '📄',
                                                'image'    => '🖼️',
                                                'video'    => '🎥',
                                                default    => '📦',
                                            } ?>
                                        </div>
                                        <div class="recent-item-meta">
                                            <span class="recent-item-name"><?= e($file['original_name']) ?></span>
                                            <span class="recent-item-sub"><?= formatBytes((int)$file['size_bytes']) ?> • <?= date('M j, Y', strtotime($file['created_at'])) ?></span>
                                        </div>
                                        <a href="download.php?id=<?= (int)$file['id'] ?>" class="btn btn--ghost btn--sm" title="Download">⬇️</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Security Activity Log -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">Live Security Activity</h3>
                            <span class="badge badge--success">Audit Active</span>
                        </div>

                        <?php if (empty($recentActivities)): ?>
                            <div class="empty-state">
                                <p class="empty-text">No recorded events yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-timeline">
                                <?php foreach ($recentActivities as $act): ?>
                                    <div class="activity-event">
                                        <div class="activity-dot"></div>
                                        <div class="activity-info">
                                            <span class="activity-action">
                                                <?= match($act['action']) {
                                                    'login'              => 'Logged In Successfully',
                                                    'registration'       => 'Created User Account',
                                                    'email_verified'     => 'Verified Security OTP',
                                                    'upload'             => 'Uploaded Encrypted File',
                                                    'download'           => 'Downloaded Vault File',
                                                    'delete'             => 'Moved Item to Recycle Bin',
                                                    'restore'            => 'Restored Item from Bin',
                                                    default              => ucfirst(str_replace('_', ' ', e($act['action']))),
                                                } ?>
                                            </span>
                                            <span class="activity-time"><?= date('M d, H:i', strtotime($act['created_at'])) ?> • IP: <?= e($act['ip_address']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/dashboard.js') ?>"></script>
</body>
</html>
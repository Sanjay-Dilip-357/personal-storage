<?php
/**
 * PERSONAL STORAGE — Admin User Inspection & Overrides
 */

require_once __DIR__ . '/../../app/bootstrap.php';

AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');
$db        = Database::getConnection();
$adminId   = (int)$_SESSION['admin_id'];

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

$userRepo     = new UserRepository();
$settingsRepo = new StorageSettingsRepository();
$user         = $userRepo->findById($userId);

if (!$user) {
    header('Location: users.php');
    exit;
}

$error   = '';
$success = '';

// ── Handle Overrides Submission ────────────────
if (requestMethod() === 'POST') {
    if (!Csrf::validate()) {
        $error = 'Security token invalid. Please refresh.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'save_overrides') {
            $quotaMB       = (int)($_POST['storage_quota_mb'] ?? 0);
            $maxDocMB      = (int)($_POST['max_doc_mb'] ?? 0);
            $maxImgMB      = (int)($_POST['max_img_mb'] ?? 0);
            $maxVidMB      = (int)($_POST['max_vid_mb'] ?? 0);
            $maxOtherMB    = (int)($_POST['max_other_mb'] ?? 0);
            $maxDocCount   = (int)($_POST['max_doc_count'] ?? 0);
            $maxImgCount   = (int)($_POST['max_img_count'] ?? 0);
            $maxVidCount   = (int)($_POST['max_vid_count'] ?? 0);
            $maxOtherCount = (int)($_POST['max_other_count'] ?? 0);
            $maxNotes      = (int)($_POST['max_note_count'] ?? 0);

            // Save overrides (convert MB to bytes where applicable)
            if ($quotaMB > 0)       $settingsRepo->setUserSetting($userId, 'default_storage_quota', (string)($quotaMB * 1048576));
            if ($maxDocMB > 0)      $settingsRepo->setUserSetting($userId, 'max_document_size', (string)($maxDocMB * 1048576));
            if ($maxImgMB > 0)      $settingsRepo->setUserSetting($userId, 'max_image_size', (string)($maxImgMB * 1048576));
            if ($maxVidMB > 0)      $settingsRepo->setUserSetting($userId, 'max_video_size', (string)($maxVidMB * 1048576));
            if ($maxOtherMB > 0)    $settingsRepo->setUserSetting($userId, 'max_other_size', (string)($maxOtherMB * 1048576));
            if ($maxDocCount > 0)   $settingsRepo->setUserSetting($userId, 'max_document_count', (string)$maxDocCount);
            if ($maxImgCount > 0)   $settingsRepo->setUserSetting($userId, 'max_image_count', (string)$maxImgCount);
            if ($maxVidCount > 0)   $settingsRepo->setUserSetting($userId, 'max_video_count', (string)$maxVidCount);
            if ($maxOtherCount > 0) $settingsRepo->setUserSetting($userId, 'max_other_count', (string)$maxOtherCount);
            if ($maxNotes > 0)      $settingsRepo->setUserSetting($userId, 'max_note_count', (string)$maxNotes);

            ActivityLogger::adminAction($adminId, 'user_overrides_updated', 'user', $userId);
            $success = 'User overrides saved successfully.';
        } elseif ($action === 'reset_overrides') {
            $stmt = $db->prepare('DELETE FROM `user_storage_settings` WHERE `user_id` = :uid');
            $stmt->execute([':uid' => $userId]);

            ActivityLogger::adminAction($adminId, 'user_overrides_reset', 'user', $userId);
            $success = 'All user overrides reset back to platform global defaults.';
        }
    }
}

// ── Load User Storage Metrics & Entities ───────
$storageService = new StorageService();
$metrics        = $storageService->getUserDashboardMetrics($userId);

// Fetch user overrides currently in DB
$stmt = $db->prepare('SELECT `setting_key`, `setting_value` FROM `user_storage_settings` WHERE `user_id` = :uid');
$stmt->execute([':uid' => $userId]);
$overrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// User files & notes for audit
$userFiles = $db->prepare('SELECT * FROM `files` WHERE `user_id` = :uid AND `deleted_at` IS NULL ORDER BY `created_at` DESC LIMIT 10');
$userFiles->execute([':uid' => $userId]);
$files = $userFiles->fetchAll();

$userNotes = $db->prepare('SELECT * FROM `notes` WHERE `user_id` = :uid AND `deleted_at` IS NULL ORDER BY `updated_at` DESC LIMIT 10');
$userNotes->execute([':uid' => $userId]);
$notes = $userNotes->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspect User: <?= e($user['full_name']) ?> — <?= $appName ?></title>
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
                        <h1 class="page-title">User: <?= e($user['full_name']) ?></h1>
                        <p class="page-subtitle"><?= e($user['email']) ?> &bull; Registered <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <a href="users.php" class="btn btn--secondary btn--sm">← Back to Directory</a>
                </div>
            </header>

            <div class="content-body">

                <?php if (!empty($success)): ?>
                    <div class="countdown-badge countdown--safe mb-4" style="width:100%; display:block; padding:12px;">✅ <?= e($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="countdown-badge mb-4" style="width:100%; display:block; padding:12px;">❌ <?= e($error) ?></div>
                <?php endif; ?>

                <!-- User Stats Summary -->
                <div class="stats-grid mb-6">
                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa;">💾</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['used_formatted'] ?></span>
                            <span class="stat-label">Used / <?= $metrics['quota_formatted'] ?> (<?= $metrics['percentage_used'] ?>%)</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(14, 165, 233, 0.1); color: #38bdf8;">📁</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['total_files'] ?></span>
                            <span class="stat-label">Active Files</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(34, 197, 94, 0.1); color: #4ade80;">📝</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['total_notes'] ?></span>
                            <span class="stat-label">Active Notes</span>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.1); color: #fbbf24;">♻️</div>
                        <div class="stat-details">
                            <span class="stat-number"><?= $metrics['recycle_bin_count'] ?></span>
                            <span class="stat-label">In Recycle Bin</span>
                        </div>
                    </div>
                </div>

                <!-- Granular Overrides Section -->
                <div class="card card--glass settings-section-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">User-Specific Limits & Overrides</h3>
                            <p class="text-muted" style="font-size:12px;">Resolution: Custom Override → Global Setting → Fallback</p>
                        </div>
                        <?php if (!empty($overrides)): ?>
                            <form action="user.php?id=<?= $userId ?>" method="POST">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="reset_overrides">
                                <button type="submit" class="btn btn--secondary btn--sm">Reset to Global Defaults</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <form action="user.php?id=<?= $userId ?>" method="POST" style="margin-top:16px;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="save_overrides">

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Storage Quota (MB)</span>
                                <p class="settings-label-desc">User total storage capacity ceiling.</p>
                            </div>
                            <div>
                                <input type="number" name="storage_quota_mb" class="form-input" value="<?= round(((int)$settingsRepo->getUserSetting($userId, 'default_storage_quota', 10737418240)) / 1048576) ?>">
                                <span class="override-indicator <?= isset($overrides['default_storage_quota']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['default_storage_quota']) ? 'Custom Override Active' : 'Inheriting Global (10 GB)' ?>
                                </span>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Document Size (MB)</span>
                                <p class="settings-label-desc">Limit for PDF, DOC, XLS, etc.</p>
                            </div>
                            <div>
                                <input type="number" name="max_doc_mb" class="form-input" value="<?= round(((int)$settingsRepo->getUserSetting($userId, 'max_document_size', 20971520)) / 1048576) ?>">
                                <span class="override-indicator <?= isset($overrides['max_document_size']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['max_document_size']) ? 'Custom Override Active' : 'Inheriting Global' ?>
                                </span>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Image Size (MB)</span>
                                <p class="settings-label-desc">Limit for JPG, PNG, WEBP, etc.</p>
                            </div>
                            <div>
                                <input type="number" name="max_img_mb" class="form-input" value="<?= round(((int)$settingsRepo->getUserSetting($userId, 'max_image_size', 10485760)) / 1048576) ?>">
                                <span class="override-indicator <?= isset($overrides['max_image_size']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['max_image_size']) ? 'Custom Override Active' : 'Inheriting Global' ?>
                                </span>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Video Size (MB)</span>
                                <p class="settings-label-desc">Limit for MP4, WEBM, MKV, etc.</p>
                            </div>
                            <div>
                                <input type="number" name="max_vid_mb" class="form-input" value="<?= round(((int)$settingsRepo->getUserSetting($userId, 'max_video_size', 31457280)) / 1048576) ?>">
                                <span class="override-indicator <?= isset($overrides['max_video_size']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['max_video_size']) ? 'Custom Override Active' : 'Inheriting Global' ?>
                                </span>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Other File Size (MB)</span>
                                <p class="settings-label-desc">Limit for general universal file formats.</p>
                            </div>
                            <div>
                                <input type="number" name="max_other_mb" class="form-input" value="<?= round(((int)$settingsRepo->getUserSetting($userId, 'max_other_size', 10485760)) / 1048576) ?>">
                                <span class="override-indicator <?= isset($overrides['max_other_size']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['max_other_size']) ? 'Custom Override Active' : 'Inheriting Global' ?>
                                </span>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Note Count</span>
                                <p class="settings-label-desc">Maximum notes allowed for this user.</p>
                            </div>
                            <div>
                                <input type="number" name="max_note_count" class="form-input" value="<?= (int)$settingsRepo->getUserSetting($userId, 'max_note_count', 500) ?>">
                                <span class="override-indicator <?= isset($overrides['max_note_count']) ? 'override-indicator--custom' : 'override-indicator--inherited' ?>">
                                    <?= isset($overrides['max_note_count']) ? 'Custom Override Active' : 'Inheriting Global' ?>
                                </span>
                            </div>
                        </div>

                        <div style="margin-top:20px; text-align:right;">
                            <button type="submit" class="btn btn--primary">Save User Overrides</button>
                        </div>
                    </form>
                </div>

                <!-- User Content Audits -->
                <div class="dashboard-grid">
                    <!-- User Files List -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">User Files (<?= count($files) ?>)</h3>
                        </div>
                        <div class="recent-list">
                            <?php if (empty($files)): ?>
                                <p class="text-muted" style="font-size:13px;">No active files uploaded by this user.</p>
                            <?php else: foreach ($files as $f): ?>
                                <div class="recent-item">
                                    <div class="file-icon-box">📁</div>
                                    <div class="recent-item-meta">
                                        <span class="recent-item-name"><?= e($f['original_name']) ?></span>
                                        <span class="recent-item-sub"><?= formatBytes((int)$f['size_bytes']) ?> &bull; <?= e($f['category']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- User Notes List -->
                    <div class="card card--glass">
                        <div class="card-header">
                            <h3 class="card-title">User Notes (<?= count($notes) ?>)</h3>
                        </div>
                        <div class="recent-list">
                            <?php if (empty($notes)): ?>
                                <p class="text-muted" style="font-size:13px;">No active notes drafted by this user.</p>
                            <?php else: foreach ($notes as $n): ?>
                                <div class="recent-item">
                                    <div class="file-icon-box">📝</div>
                                    <div class="recent-item-meta">
                                        <span class="recent-item-name"><?= e($n['title'] ?: 'Untitled Note') ?></span>
                                        <span class="recent-item-sub"><?= mb_substr(strip_tags($n['content']), 0, 60) ?>...</span>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
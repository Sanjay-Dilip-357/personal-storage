<?php
/**
 * PERSONAL STORAGE — Global Storage Settings
 */

require_once __DIR__ . '/../../app/bootstrap.php';

AuthMiddleware::requireAdmin();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$adminName = e($_SESSION['admin_name'] ?? 'Administrator');
$adminId   = (int)$_SESSION['admin_id'];

$settingsRepo = new StorageSettingsRepository();
$error        = '';
$success      = '';

// ── Handle Global Settings Update ─────────────
if (requestMethod() === 'POST') {
    if (!Csrf::validate()) {
        $error = 'Security token expired. Please refresh.';
    } else {
        $quotaMB       = (int)($_POST['default_storage_quota_mb'] ?? 10240);
        $maxDocMB      = (int)($_POST['max_document_size_mb'] ?? 20);
        $maxImgMB      = (int)($_POST['max_image_size_mb'] ?? 10);
        $maxVidMB      = (int)($_POST['max_video_size_mb'] ?? 30);
        $maxOtherMB    = (int)($_POST['max_other_size_mb'] ?? 10);
        $maxNotes      = (int)($_POST['max_note_count'] ?? 500);
        $retentionDays = (int)($_POST['recycle_bin_days'] ?? 7);
        $notesStorage  = isset($_POST['notes_count_toward_storage']) ? '1' : '0';

        // Update database settings
        $settingsRepo->updateSetting('default_storage_quota', (string)($quotaMB * 1048576));
        $settingsRepo->updateSetting('max_document_size', (string)($maxDocMB * 1048576));
        $settingsRepo->updateSetting('max_image_size', (string)($maxImgMB * 1048576));
        $settingsRepo->updateSetting('max_video_size', (string)($maxVidMB * 1048576));
        $settingsRepo->updateSetting('max_other_size', (string)($maxOtherMB * 1048576));
        $settingsRepo->updateSetting('max_note_count', (string)$maxNotes);
        $settingsRepo->updateSetting('recycle_bin_days', (string)$retentionDays);
        $settingsRepo->updateSetting('notes_count_toward_storage', $notesStorage);

        ActivityLogger::adminAction($adminId, 'global_settings_updated', 'setting');
        $success = 'Global storage configuration updated successfully.';
    }
}

// Current Global Settings
$defaultQuotaMB = round(((int)$settingsRepo->getSetting('default_storage_quota', 10737418240)) / 1048576);
$maxDocMB       = round(((int)$settingsRepo->getSetting('max_document_size', 20971520)) / 1048576);
$maxImgMB       = round(((int)$settingsRepo->getSetting('max_image_size', 10485760)) / 1048576);
$maxVidMB       = round(((int)$settingsRepo->getSetting('max_video_size', 31457280)) / 1048576);
$maxOtherMB     = round(((int)$settingsRepo->getSetting('max_other_size', 10485760)) / 1048576);
$maxNotes       = (int)$settingsRepo->getSetting('max_note_count', 500);
$retentionDays  = (int)$settingsRepo->getSetting('recycle_bin_days', 7);
$notesCount     = (bool)$settingsRepo->getSetting('notes_count_toward_storage', false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Configuration — <?= $appName ?></title>
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
                        <h1 class="page-title">Global Storage Configuration</h1>
                        <p class="page-subtitle">Platform-wide default quotas, file size limits, and retention policy</p>
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

                <div class="card card--glass">
                    <form action="storage.php" method="POST">
                        <?= Csrf::field() ?>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Default User Quota (MB)</span>
                                <p class="settings-label-desc">Storage allocation for newly registered users (10240 MB = 10 GB).</p>
                            </div>
                            <div>
                                <input type="number" name="default_storage_quota_mb" class="form-input" value="<?= $defaultQuotaMB ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Document Size (MB)</span>
                                <p class="settings-label-desc">PDF, Word, Excel, PowerPoint, Text, CSV files.</p>
                            </div>
                            <div>
                                <input type="number" name="max_document_size_mb" class="form-input" value="<?= $maxDocMB ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Image Size (MB)</span>
                                <p class="settings-label-desc">JPG, PNG, GIF, WEBP, SVG files.</p>
                            </div>
                            <div>
                                <input type="number" name="max_image_size_mb" class="form-input" value="<?= $maxImgMB ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Video Size (MB)</span>
                                <p class="settings-label-desc">MP4, WEBM, MKV, AVI, MOV media.</p>
                            </div>
                            <div>
                                <input type="number" name="max_video_size_mb" class="form-input" value="<?= $maxVidMB ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Other Format Size (MB)</span>
                                <p class="settings-label-desc">Universal file types (*.* fallback).</p>
                            </div>
                            <div>
                                <input type="number" name="max_other_size_mb" class="form-input" value="<?= $maxOtherMB ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Max Notes Per User</span>
                                <p class="settings-label-desc">Maximum active personal notes allowed per account.</p>
                            </div>
                            <div>
                                <input type="number" name="max_note_count" class="form-input" value="<?= $maxNotes ?>" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Recycle Bin Retention Duration (Days)</span>
                                <p class="settings-label-desc">Days before soft-deleted files/notes are permanently purged by cron.</p>
                            </div>
                            <div>
                                <input type="number" name="recycle_bin_days" class="form-input" value="<?= $retentionDays ?>" min="1" max="90" required>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div>
                                <span class="settings-label-title">Notes Count Toward Storage Quota</span>
                                <p class="settings-label-desc">If enabled, note content bytes will be added to total storage consumption.</p>
                            </div>
                            <div>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="notes_count_toward_storage" value="1" <?= $notesCount ? 'checked' : '' ?>>
                                    <span>Enable Note Byte Accounting</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top:24px; text-align:right;">
                            <button type="submit" class="btn btn--primary">Save Global Configuration</button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

</body>
</html>
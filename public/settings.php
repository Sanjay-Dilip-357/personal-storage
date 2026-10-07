<?php
/**
 * PERSONAL STORAGE — User Settings & Security Center
 */

require_once __DIR__ . '/../app/bootstrap.php';

AuthMiddleware::requireUser();

SecurityHeaders::send();
SecurityHeaders::noCache();

$appName   = e(Config::get('APP_NAME', 'Personal Storage'));
$userId    = (int)$_SESSION['user_id'];
$userName  = e($_SESSION['user_name'] ?? 'User');
$userEmail = e($_SESSION['user_email'] ?? '');

$userRepo       = new UserRepository();
$user           = $userRepo->findById($userId);
$storageService = new StorageService();
$metrics        = $storageService->getUserDashboardMetrics($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings — <?= $appName ?></title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/settings.css') ?>">
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
                        <h1 class="page-title">Account Settings</h1>
                        <p class="page-subtitle">Manage your personal profile, security credentials, and storage vault</p>
                    </div>
                </div>
            </header>

            <div class="content-body">

                <div class="settings-grid-layout">

                    <!-- Left Column: Forms -->
                    <div class="settings-main-col">

                        <!-- ── 1. Profile Information ── -->
                        <div class="card card--glass settings-card">
                            <div class="card-header">
                                <div>
                                    <h2 class="card-title">Profile Information</h2>
                                    <p class="text-muted" style="font-size:12px;">Update your display name</p>
                                </div>
                            </div>

                            <form id="form-profile" class="mt-4">
                                <div class="form-group">
                                    <label class="form-label" for="profile-name">Full Name</label>
                                    <input type="text" id="profile-name" name="full_name" class="form-input" value="<?= $userName ?>" required maxlength="100">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="profile-email">Email Address</label>
                                    <input type="email" id="profile-email" class="form-input" value="<?= $userEmail ?>" disabled style="opacity:0.6; cursor:not-allowed;">
                                    <span class="form-hint">Email address cannot be changed directly for security purposes.</span>
                                </div>

                                <div class="settings-form-actions">
                                    <button type="submit" class="btn btn--primary" id="btn-save-profile">Save Profile</button>
                                </div>
                            </form>
                        </div>

                        <!-- ── 2. Security & Password ── -->
                        <div class="card card--glass settings-card">
                            <div class="card-header">
                                <div>
                                    <h2 class="card-title">Change Password</h2>
                                    <p class="text-muted" style="font-size:12px;">Ensure your password is at least 8 characters</p>
                                </div>
                            </div>

                            <form id="form-password" class="mt-4">
                                <div class="form-group">
                                    <label class="form-label" for="current-password">Current Password</label>
                                    <input type="password" id="current-password" name="current_password" class="form-input" placeholder="Enter current password" required autocomplete="current-password">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="new-password">New Password</label>
                                    <input type="password" id="new-password" name="new_password" class="form-input" placeholder="Min 8 characters" required autocomplete="new-password">
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="confirm-password">Confirm New Password</label>
                                    <input type="password" id="confirm-password" name="confirm_password" class="form-input" placeholder="Repeat new password" required autocomplete="new-password">
                                </div>

                                <div class="settings-form-actions">
                                    <button type="submit" class="btn btn--primary" id="btn-save-password">Update Password</button>
                                </div>
                            </form>
                        </div>

                        <!-- ── 3. Danger Zone ── -->
                        <div class="card card--glass settings-card danger-zone-card">
                            <div class="card-header">
                                <div>
                                    <h2 class="card-title" style="color:var(--color-danger-light);">Danger Zone</h2>
                                    <p class="text-muted" style="font-size:12px;">Irreversible and permanent actions</p>
                                </div>
                            </div>

                            <div class="danger-zone-content">
                                <div>
                                    <strong>Delete Account</strong>
                                    <p class="text-muted" style="font-size:12px; margin-top:2px;">Permanently erase your account, files, notes, and recycle bin data.</p>
                                </div>
                                <button type="button" class="btn btn--danger btn--sm" id="btn-open-delete-modal">Delete Account</button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Account Summary -->
                    <div class="settings-side-col">

                        <div class="card card--glass settings-card">
                            <h3 class="card-title mb-4">Vault Overview</h3>

                            <div class="vault-overview-stat">
                                <span class="stat-label">User ID</span>
                                <span class="stat-value">#<?= $userId ?></span>
                            </div>

                            <div class="vault-overview-stat">
                                <span class="stat-label">Verification Status</span>
                                <span class="badge-status badge-status--active">Verified</span>
                            </div>

                            <div class="vault-overview-stat">
                                <span class="stat-label">Member Since</span>
                                <span class="stat-value"><?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                            </div>

                            <div class="vault-overview-stat">
                                <span class="stat-label">Storage Consumed</span>
                                <span class="stat-value"><?= $metrics['used_formatted'] ?> / <?= $metrics['quota_formatted'] ?></span>
                            </div>

                            <div class="storage-bar" style="height:6px; margin: 16px 0 8px;">
                                <div class="storage-bar-fill" style="width: <?= $metrics['percentage_used'] ?>%;"></div>
                            </div>
                            <span class="text-muted" style="font-size:11px;"><?= $metrics['percentage_used'] ?>% of allocated storage used</span>
                        </div>

                        <div class="card card--glass settings-card">
                            <h3 class="card-title mb-2">Privacy & Security</h3>
                            <p class="text-muted" style="font-size:12px; line-height:1.6;">
                                Your files are isolated in private disk sectors outside the public web root. Access is permitted strictly through token-authenticated PHP endpoints.
                            </p>
                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div class="modal-overlay" id="delete-account-modal">
        <div class="modal-card" style="max-width:440px;">
            <div class="modal-header">
                <h2 class="modal-title" style="color:var(--color-danger-light);">Confirm Account Deletion</h2>
                <button class="modal-close" id="delete-modal-close" aria-label="Close">✕</button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px; color:var(--color-text-secondary); line-height:1.5; margin-bottom:16px;">
                    ⚠️ This action is <strong>permanent</strong> and cannot be undone. All your uploaded files, notes, and activity history will be instantly destroyed.
                </p>
                <div class="form-group">
                    <label class="form-label" for="delete-password-input">Enter Your Password to Confirm</label>
                    <input type="password" id="delete-password-input" class="form-input" placeholder="••••••••" required>
                </div>
                <div class="form-error" id="delete-error"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn--secondary" id="delete-modal-cancel">Cancel</button>
                <button class="btn btn--danger" id="btn-confirm-delete">Permanently Delete</button>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script src="<?= asset('js/dashboard.js') ?>"></script>
    <script src="<?= asset('js/settings.js') ?>"></script>
</body>
</html>
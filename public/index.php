<?php
/**
 * PERSONAL STORAGE — Production Landing Page
 */

require_once __DIR__ . '/../app/bootstrap.php';

SecurityHeaders::send();

$appName = e(Config::get('APP_NAME', 'Personal Storage'));
$isLoggedIn = AuthMiddleware::isLoggedIn();
$isAdmin = AuthMiddleware::isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $appName ?> — Enterprise-grade private cloud storage for your files, media, and notes.">
    <title><?= $appName ?> — Private & Secure Cloud Storage</title>
    <?= Csrf::meta() ?>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/landing.css') ?>">
</head>
<body class="landing-body">

    <!-- Background Glows -->
    <div class="landing-bg">
        <div class="landing-glow landing-glow--1"></div>
        <div class="landing-glow landing-glow--2"></div>
        <div class="landing-glow landing-glow--3"></div>
    </div>

    <!-- Navigation Header -->
    <header class="landing-nav">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <span><?= $appName ?></span>
            </a>

            <nav class="nav-links">
                <a href="#features">Features</a>
                <a href="#security">Security</a>
                <a href="#notes">Notes</a>
                <a href="#storage">Storage</a>
            </nav>

            <div class="nav-actions">
                <?php if ($isAdmin): ?>
                    <a href="admin/index.php" class="btn btn--primary">Admin Panel</a>
                <?php elseif ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn--primary">Go to Dashboard</a>
                <?php else: ?>
                    <a href="auth.php" class="btn btn--ghost">Sign In</a>
                    <a href="auth.php" class="btn btn--primary">Get Started</a>
                <?php endif; ?>
            </div>

            <!-- Mobile Toggle Button -->
            <button class="nav-mobile-toggle" id="mobile-menu-open" aria-label="Open menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Mobile Drawer -->
    <div class="nav-mobile-overlay" id="mobile-overlay"></div>
    <aside class="nav-mobile-drawer" id="mobile-drawer">
        <div class="nav-mobile-drawer-header">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <span><?= $appName ?></span>
            </a>
            <button class="nav-mobile-drawer-close" id="mobile-menu-close" aria-label="Close menu">✕</button>
        </div>

        <nav class="nav-mobile-drawer-links">
            <a href="#features" data-mobile-close>Features</a>
            <a href="#security" data-mobile-close>Security</a>
            <a href="#notes" data-mobile-close>Notes</a>
            <a href="#storage" data-mobile-close>Storage</a>
        </nav>

        <div class="nav-mobile-drawer-actions">
            <?php if ($isAdmin): ?>
                <a href="admin/index.php" class="btn btn--primary btn--full">Admin Panel</a>
            <?php elseif ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn--primary btn--full">Go to Dashboard</a>
            <?php else: ?>
                <a href="auth.php" class="btn btn--ghost btn--full">Sign In</a>
                <a href="auth.php" class="btn btn--primary btn--full">Get Started</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-badge">
                <span class="badge-dot"></span>
                <span>Next-Generation Private Storage Architecture</span>
            </div>
            
            <h1 class="hero-title">
                Your Private Space For Files, Notes, And <span class="gradient-text">Everything That Matters.</span>
            </h1>
            
            <p class="hero-subtitle">
                Zero public file leaks. Pure server-side ownership authorization. Enterprise-grade personal cloud built for individuals who prioritize ultimate privacy and uncompromised speed.
            </p>

            <div class="hero-cta-group">
                <?php if ($isAdmin): ?>
                    <a href="admin/index.php" class="btn btn--primary btn--lg">Open Admin Panel</a>
                <?php elseif ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn--primary btn--lg">Open My Storage Vault</a>
                <?php else: ?>
                    <a href="auth.php" class="btn btn--primary btn--lg">Create Free Vault</a>
                    <a href="auth.php" class="btn btn--secondary btn--lg">Access Existing Account</a>
                <?php endif; ?>
            </div>

            <div class="hero-preview-wrapper">
                <div class="hero-preview-card">
                    <div class="preview-header">
                        <div class="preview-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="preview-url">vault.personalstorage.internal/user</div>
                    </div>
                    <div class="preview-content">
                        <div class="preview-metric-row">
                            <div class="preview-mini-stat">
                                <span class="stat-label">Storage Allocated</span>
                                <span class="stat-value">10.0 GB</span>
                            </div>
                            <div class="preview-mini-stat">
                                <span class="stat-label">Security Protocol</span>
                                <span class="stat-value accent">Isolated Vault</span>
                            </div>
                            <div class="preview-mini-stat">
                                <span class="stat-label">Recycle Protection</span>
                                <span class="stat-value success">7-Day Recovery</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="section-padding">
        <div class="section-container">
            <div class="section-header text-center">
                <h2 class="section-title">Engineered For Total Digital Sovereignty</h2>
                <p class="section-subtitle">Every feature is designed from the ground up to prevent data leaks and eliminate unauthorized access.</p>
            </div>

            <div class="features-grid">
                <div class="card card--glass feature-card">
                    <div class="feature-icon">📁</div>
                    <h3 class="feature-title">Encrypted Document Vault</h3>
                    <p class="feature-desc">Store PDFs, spreadsheets, presentations, and archives with automated MIME and signature inspection.</p>
                </div>

                <div class="card card--glass feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3 class="feature-title">High-Res Media Streaming</h3>
                    <p class="feature-desc">Safely preview photos and stream personal videos directly through token-authenticated PHP endpoints.</p>
                </div>

                <div class="card card--glass feature-card">
                    <div class="feature-icon">📝</div>
                    <h3 class="feature-title">Integrated Markdown Notes</h3>
                    <p class="feature-desc">Draft personal notes, ideas, and secrets alongside your files with instant searching and full version logs.</p>
                </div>

                <div class="card card--glass feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Strict User Isolation</h3>
                    <p class="feature-desc">No shared public folders. Physical filenames are randomized and stored entirely outside the web root.</p>
                </div>

                <div class="card card--glass feature-card">
                    <div class="feature-icon">♻️</div>
                    <h3 class="feature-title">7-Day Recycle Bin</h3>
                    <p class="feature-desc">Accidental deletions are held in a soft-deleted quarantine with automatic expiration and instant restoration.</p>
                </div>

                <div class="card card--glass feature-card">
                    <div class="feature-icon">⚙️</div>
                    <h3 class="feature-title">Configurable Quotas</h3>
                    <p class="feature-desc">Granular per-user storage allocations, file count ceilings, and customized MIME allowlists.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Security -->
    <section id="security" class="section-padding">
        <div class="section-container">
            <div class="security-box">
                <div class="security-badge">Security Whitepaper Standard</div>
                <h2 class="security-title">Zero Direct Web-Root File Exposure</h2>
                <p class="security-desc">
                    Unlike standard web scripts that drop uploads into publicly browsable folders, <strong>Personal Storage</strong> isolates all physical files in a private disk sector inaccessible to web browsers. Every single byte is streamed only after server-side session, quota, and ownership verification.
                </p>
                <div class="security-checklist">
                    <div class="check-item"><span class="check-icon">✓</span> CSRF Double-Submit Validation</div>
                    <div class="check-item"><span class="check-icon">✓</span> Hashed OTP Challenge & Expiration</div>
                    <div class="check-item"><span class="check-icon">✓</span> Deep MIME Magic Byte Inspection</div>
                    <div class="check-item"><span class="check-icon">✓</span> Database-Indexed Rate Limiting</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> <?= $appName ?>. All rights reserved. Private Cloud Infrastructure.</p>
        </div>
    </footer>

    <script src="<?= asset('js/app.js') ?>"></script>
    <script>
        // Mobile Menu Controls
        document.addEventListener('DOMContentLoaded', () => {
            const openBtn = document.getElementById('mobile-menu-open');
            const closeBtn = document.getElementById('mobile-menu-close');
            const drawer = document.getElementById('mobile-drawer');
            const overlay = document.getElementById('mobile-overlay');

            const openMenu = () => {
                drawer.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            };

            const closeMenu = () => {
                drawer.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            };

            if (openBtn) openBtn.addEventListener('click', openMenu);
            if (closeBtn) closeBtn.addEventListener('click', closeMenu);
            if (overlay) overlay.addEventListener('click', closeMenu);

            // Close menu when clicking a link
            document.querySelectorAll('[data-mobile-close]').forEach(link => {
                link.addEventListener('click', closeMenu);
            });
        });
    </script>
</body>
</html>
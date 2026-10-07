<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Automated Admin Account Seed
 * ==============================================
 * ⚠️ IMPORTANT: Delete this file after running it!
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

// ── Admin Credentials Setup ──────────────────
$username = 'admin';
$email    = 'sanjaydk357@gmail.com';
$name     = 'System Administrator';
$password = 'SanjayDK@0357';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>Admin Seed</title><style>
        body { background:#0a0e17; color:#f1f5f9; font-family:sans-serif; padding:40px; display:flex; justify-content:center; }
        .card { background:#111827; border:1px solid rgba(148,163,184,0.15); border-radius:16px; padding:32px; max-width:500px; width:100%; box-shadow:0 10px 30px rgba(0,0,0,0.5); }
        h1 { color:#38bdf8; font-size:20px; margin-bottom:16px; }
        p { font-size:14px; color:#94a3b8; line-height:1.6; }
        .success { background:rgba(34,197,94,0.1); border:1px solid rgba(34,197,94,0.3); color:#4ade80; padding:12px; border-radius:8px; margin:16px 0; font-size:14px; }
        .danger { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#f87171; padding:12px; border-radius:8px; margin:16px 0; font-size:14px; }
        .box { background:#0f172a; border-radius:8px; padding:16px; margin:16px 0; font-family:monospace; font-size:13px; color:#e2e8f0; }
    </style></head><body><div class="card">';
}

try {
    $db = Database::getConnection();

    // Check if admin user exists
    $stmt = $db->prepare('SELECT `id` FROM `admin_users` WHERE `username` = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $existing = $stmt->fetch();

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($existing) {
        // Update existing admin account
        $updateStmt = $db->prepare(
            'UPDATE `admin_users`
             SET `email` = :email, `password_hash` = :hash, `full_name` = :name, `status` = "active"
             WHERE `id` = :id'
        );
        $updateStmt->execute([
            ':email' => $email,
            ':hash'  => $passwordHash,
            ':name'  => $name,
            ':id'    => $existing['id'],
        ]);
        $action = "UPDATED (ID: {$existing['id']})";
    } else {
        // Insert new admin account
        $insertStmt = $db->prepare(
            'INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `status`, `created_at`)
             VALUES (:username, :email, :hash, :name, "active", NOW())'
        );
        $insertStmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':hash'     => $passwordHash,
            ':name'     => $name,
        ]);
        $newId = $db->lastInsertId();
        $action = "CREATED (ID: {$newId})";
    }

    if ($isCli) {
        echo "\n==============================================\n";
        echo "✅ Admin Account {$action} Successfully!\n";
        echo "==============================================\n";
        echo "Username: {$username}\n";
        echo "Email:    {$email}\n";
        echo "Password: {$password}\n";
        echo "==============================================\n";
        echo "⚠️ Please remember to DELETE this seed file now.\n\n";
    } else {
        echo '<h1>🔐 Admin Seed Complete</h1>';
        echo "<div class='success'>✅ Admin Account {$action} Successfully!</div>";
        echo '<div class="box">';
        echo "<strong>Username:</strong> " . htmlspecialchars($username) . "<br>";
        echo "<strong>Email:</strong> " . htmlspecialchars($email) . "<br>";
        echo "<strong>Password:</strong> " . htmlspecialchars($password) . "<br>";
        echo '</div>';
        echo '<p style="color:#f87171; font-weight:600;">⚠️ Please manually DELETE this file (<code>database/seed/admin_seed.php</code>) now from your project.</p>';
        echo '</div></body></html>';
    }

} catch (\Throwable $e) {
    if ($isCli) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    } else {
        echo '<h1>❌ Admin Seed Failed</h1>';
        echo '<div class="danger">' . htmlspecialchars($e->getMessage()) . '</div></div></body></html>';
    }
}
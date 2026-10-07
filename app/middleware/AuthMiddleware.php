<?php
/**
 * PERSONAL STORAGE — Authentication Middleware
 * Enforces unified authentication rules for users and administrators.
 */

declare(strict_types=1);

class AuthMiddleware
{
    /**
     * Require authenticated regular user.
     * Redirects to unified auth.php if not logged in.
     */
    public static function requireUser(): void
    {
        if (!self::isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: ' . appUrl('auth.php'));
            exit;
        }
    }

    /**
     * Require authenticated administrator.
     * Redirects directly to unified auth.php if not logged in as admin.
     */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . appUrl('auth.php'));
            exit;
        }
    }

    /**
     * Check if a regular user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['authenticated']);
    }

    /**
     * Check if an administrator is logged in.
     */
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_authenticated']);
    }

    /**
     * Get the current user's ID.
     */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Get the current admin's ID.
     */
    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    }

    /**
     * Redirect already authenticated sessions away from auth page.
     */
    public static function redirectIfAuthenticated(): void
    {
        if (self::isAdmin()) {
            header('Location: ' . appUrl('admin/index.php'));
            exit;
        }
        if (self::isLoggedIn()) {
            header('Location: ' . appUrl('dashboard.php'));
            exit;
        }
    }
}
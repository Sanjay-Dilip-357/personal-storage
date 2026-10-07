<?php
/**
 * ==============================================
 * PERSONAL STORAGE — CSRF Protection
 * ==============================================
 */

declare(strict_types=1);

class Csrf
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    /**
     * Generate or return existing CSRF token for this session.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Generate an HTML hidden input field.
     */
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate a meta tag for JS access.
     */
    public static function meta(): string
    {
        $token = self::token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate a submitted CSRF token.
     */
    public static function validate(?string $submittedToken = null): bool
    {
        if ($submittedToken === null) {
            $submittedToken = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (empty($_SESSION[self::TOKEN_NAME]) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME], $submittedToken);
    }

    /**
     * Validate and throw if invalid.
     */
    public static function validateOrFail(?string $submittedToken = null): void
    {
        if (!self::validate($submittedToken)) {
            http_response_code(403);
            throw new RuntimeException('CSRF token validation failed.');
        }
    }

    /**
     * Regenerate token (use after successful sensitive operation if desired).
     */
    public static function regenerate(): string
    {
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_NAME];
    }
}
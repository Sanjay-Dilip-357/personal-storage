<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Security Headers
 * ==============================================
 */

declare(strict_types=1);

class SecurityHeaders
{
    /**
     * Send security headers for HTML pages.
     */
    public static function send(): void
    {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
        header("Content-Security-Policy: {$csp}");
    }

    /**
     * Send headers suitable for JSON API responses.
     */
    public static function sendApi(): void
    {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    public static function setApiHeaders(): void
    {
        self::sendApi();
    }

    public static function setSecurityHeaders(): void
    {
        self::send();
    }

    public static function sendFileDownload(string $filename, string $mimeType, int $fileSize): void
    {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Type: {$mimeType}");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-Length: {$fileSize}");
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function noCache(): void
    {
        if (headers_sent()) return;

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

// Global alias so any reference to Headers or SecurityHeaders resolves seamlessly
if (!class_exists('Headers', false)) {
    class_alias('SecurityHeaders', 'Headers');
}
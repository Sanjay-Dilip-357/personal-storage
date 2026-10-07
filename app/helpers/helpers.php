<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Global Helper Functions
 * ==============================================
 */

declare(strict_types=1);

/**
 * Escape string for safe HTML output.
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get config value (shorthand).
 */
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/**
 * Get the application base URL.
 */
function appUrl(string $path = ''): string
{
    $base = rtrim(Config::get('APP_URL', ''), '/');
    if ($path) {
        $path = '/' . ltrim($path, '/');
    }
    return $base . $path;
}

/**
 * Get asset URL.
 */
function asset(string $path): string
{
    return appUrl('assets/' . ltrim($path, '/'));
}

/**
 * Redirect to a URL.
 */
function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

/**
 * Return a JSON response.
 */
function jsonResponse(mixed $data, int $statusCode = 200): never
{
    SecurityHeaders::sendApi();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Return a JSON error response.
 */
function jsonError(string $message, int $statusCode = 400, array $errors = []): never
{
    $response = ['success' => false, 'message' => $message];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Return a JSON success response.
 */
function jsonSuccess(string $message = 'Success', array $data = []): never
{
    $response = ['success' => true, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    jsonResponse($response, 200);
}

/**
 * Format bytes to human-readable.
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    if ($bytes <= 0) {
        return '0 B';
    }

    $power = floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);

    return round($bytes / pow(1024, $power), $precision) . ' ' . $units[(int)$power];
}

/**
 * Get client IP address.
 */
function getClientIp(): string
{
    // Only trust REMOTE_ADDR in standard setups.
    // Do NOT trust X-Forwarded-For without a trusted proxy configuration.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate a cryptographically secure random string.
 */
function randomString(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

/**
 * Generate a secure numeric OTP.
 */
function generateOtp(int $digits = 5): string
{
    $min = (int)pow(10, $digits - 1);
    $max = (int)pow(10, $digits) - 1;
    return (string)random_int($min, $max);
}

/**
 * Set a flash message.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Get and clear flash messages.
 */
function getFlash(string $type = ''): array
{
    if ($type) {
        $messages = $_SESSION['flash'][$type] ?? [];
        unset($_SESSION['flash'][$type]);
        return $messages;
    }

    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/**
 * Check if current request is AJAX / fetch.
 */
function isAjax(): bool
{
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (!empty($_SERVER['HTTP_ACCEPT']) &&
         str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    );
}

/**
 * Get the current request method.
 */
function requestMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Sanitize a string for safe use.
 */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

/**
 * Log an application message.
 */
function appLog(string $message, string $level = 'info'): void
{
    $logDir = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

    file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
}
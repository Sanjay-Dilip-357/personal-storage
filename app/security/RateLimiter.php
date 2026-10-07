<?php
/**
 * PERSONAL STORAGE — Rate Limiter
 * Database-backed rate limiting compatible with native prepared statements.
 */

declare(strict_types=1);

class RateLimiter
{
    /**
     * Check if an action is rate-limited.
     */
    public static function check(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        try {
            $db = Database::getConnection();

            // Calculate threshold in PHP to avoid INTERVAL parameterization bugs
            $threshold = date('Y-m-d H:i:s', time() - $windowSeconds);

            // Clean up old entries
            $stmt = $db->prepare(
                'DELETE FROM rate_limits WHERE rate_key = :key AND attempted_at < :threshold'
            );
            $stmt->execute([
                ':key'       => $key,
                ':threshold' => $threshold,
            ]);

            // Count recent attempts
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM rate_limits WHERE rate_key = :key AND attempted_at >= :threshold'
            );
            $stmt->execute([
                ':key'       => $key,
                ':threshold' => $threshold,
            ]);

            $count = (int)$stmt->fetchColumn();

            return $count < $maxAttempts;
        } catch (\Throwable $e) {
            error_log('RateLimiter error: ' . $e->getMessage());
            return true; // Fail open to prevent locking users out if DB hiccup occurs
        }
    }

    /**
     * Record an attempt.
     */
    public static function hit(string $key): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                'INSERT INTO rate_limits (rate_key, attempted_at) VALUES (:key, NOW())'
            );
            $stmt->execute([':key' => $key]);
        } catch (\Throwable $e) {
            error_log('RateLimiter hit error: ' . $e->getMessage());
        }
    }

    /**
     * Clear rate limit entries for a key.
     */
    public static function clear(string $key): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('DELETE FROM rate_limits WHERE rate_key = :key');
            $stmt->execute([':key' => $key]);
        } catch (\Throwable $e) {
            error_log('RateLimiter clear error: ' . $e->getMessage());
        }
    }
}
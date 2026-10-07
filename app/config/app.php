<?php
/**
 * ==============================================
 * PERSONAL STORAGE — Application Configuration
 * ==============================================
 * Loads .env and provides config access.
 */

declare(strict_types=1);

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file.
     */
    public static function load(string $envPath): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($envPath)) {
            throw new RuntimeException(
                '.env file not found. Copy .env.example to .env and configure it.'
            );
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Must contain =
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            // Remove surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Convert special string values
            $value = match (strtolower($value)) {
                'true'  => true,
                'false' => false,
                'null'  => null,
                default => $value,
            };

            self::$config[$key] = $value;

            // Also set in environment
            if (is_string($value) || is_numeric($value)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get a configuration value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Check if a key exists.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$config);
    }

    /**
     * Get boolean value.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default);

        if (is_bool($val)) {
            return $val;
        }

        return in_array(strtolower((string)$val), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get integer value.
     */
    public static function int(string $key, int $default = 0): int
    {
        return (int)(self::get($key, $default));
    }

    /**
     * Check if we are in debug mode.
     */
    public static function isDebug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }

    /**
     * Check if we are in production.
     */
    public static function isProduction(): bool
    {
        return strtolower((string)self::get('APP_ENV', 'production')) === 'production';
    }
}
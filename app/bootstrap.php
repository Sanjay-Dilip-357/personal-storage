<?php
/**
 * Personal Storage - Application Bootstrap
 */

// Error handling based on environment
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Base Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/private/uploads');

// Ensure essential storage directories exist
@mkdir(STORAGE_PATH . '/logs', 0755, true);
@mkdir(STORAGE_PATH . '/cache', 0755, true);
@mkdir(STORAGE_PATH . '/temp', 0755, true);
@mkdir(UPLOAD_PATH, 0755, true);

// 1. Config
require_once APP_PATH . '/config/app.php';
Config::load(ROOT_PATH . '/.env');

// Set Application Timezone
$timezone = Config::get('APP_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set($timezone);

// Display errors in debug mode
if (Config::isDebug()) {
    ini_set('display_errors', '1');
}

// 2. Logging & Database
require_once APP_PATH . '/logging/ActivityLogger.php';
require_once APP_PATH . '/database/Database.php';
require_once APP_PATH . '/database/Repository.php';

// 3. Security
require_once APP_PATH . '/security/Headers.php';
require_once APP_PATH . '/security/Csrf.php';
require_once APP_PATH . '/security/RateLimiter.php';

// 4. Repositories
require_once APP_PATH . '/repositories/UserRepository.php';
require_once APP_PATH . '/repositories/FileRepository.php';
require_once APP_PATH . '/repositories/NoteRepository.php';
require_once APP_PATH . '/repositories/StorageSettingsRepository.php';

// 5. Mailer & Services
require_once APP_PATH . '/mail/Mailer.php';
require_once APP_PATH . '/services/AuthService.php';
require_once APP_PATH . '/services/UploadService.php';
require_once APP_PATH . '/services/StorageService.php';

// 6. Middleware & Helpers
require_once APP_PATH . '/middleware/AuthMiddleware.php';
require_once APP_PATH . '/helpers/helpers.php';

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = (int)Config::get('SESSION_LIFETIME', 120) * 60;
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.cookie_lifetime', (string)$sessionLifetime);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }

    session_name(Config::get('SESSION_NAME', 'ps_session'));
    session_start();
}

// Universal JSON Request Parser: Ensure $_POST is populated for all JSON payloads
if (empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            $_POST = $jsonData;
        }
    }
}
<?php
/**
 * Personal Storage - Authentication API Endpoint
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

ob_start();

try {
    require_once __DIR__ . '/../../app/bootstrap.php';

    SecurityHeaders::sendApi();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        jsonResponse(false, 'Method not allowed.', [], 405);
    }

    // CSRF check
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!Csrf::validate($csrfToken)) {
        ob_end_clean();
        jsonResponse(false, 'Invalid or expired security token. Please refresh the page.', [], 403);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $authService = new AuthService();

    switch ($action) {
        case 'register':
            $name = trim((string)($_POST['name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $result = $authService->register($name, $username, $email, $password);

            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'verify_otp':
            $userId = (int)($_POST['user_id'] ?? 0);
            $otp = trim((string)($_POST['otp'] ?? ''));

            $result = $authService->verifyRegistrationOtp($userId, $otp);
            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'resend_otp':
            $userId = (int)($_POST['user_id'] ?? 0);

            $result = $authService->resendRegistrationOtp($userId);
            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'login':
            $login = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $result = $authService->login($login, $password);

            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'forgot_password':
            $email = trim((string)($_POST['email'] ?? ''));

            $result = $authService->forgotPassword($email);
            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'verify_reset_otp':
            $userId = (int)($_POST['user_id'] ?? 0);
            $otp = trim((string)($_POST['otp'] ?? ''));

            $result = $authService->verifyResetOtp($userId, $otp);
            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        case 'reset_password':
            $password = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['password_confirmation'] ?? '');

            $result = $authService->resetPassword($password, $confirmPassword);
            ob_end_clean();
            jsonResponse($result['success'], $result['message'] ?? '', $result, $result['success'] ? 200 : 400);
            break;

        default:
            ob_end_clean();
            jsonResponse(false, 'Unknown action.', ['received_action' => $action], 400);
            break;
    }

} catch (\Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    $logMessage = "[" . date('Y-m-d H:i:s') . "] Fatal API Error: " . $e->getMessage() . 
                  " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . 
                  "Stack trace:\n" . $e->getTraceAsString() . "\n\n";

    $logDir = dirname(__DIR__, 2) . '/storage/logs';
    @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/php_error.log', $logMessage, FILE_APPEND);

    jsonResponse(false, 'System Error: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], 500);
}
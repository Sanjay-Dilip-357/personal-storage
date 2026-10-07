<?php
/**
 * PERSONAL STORAGE — Logout Endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$auth = new AuthService();
$auth->logout();

header('Location: auth.php');
exit;
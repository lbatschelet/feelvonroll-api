<?php
/**
 * Admin auth endpoint for login, bootstrap, and token refresh.
 */

require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_auth_service.php';
admin_handle_options('GET, POST, OPTIONS', 'Content-Type, Authorization, X-Admin-Token');

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';

try {
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(admin_auth_status($pdo));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$data = json_request();

$action = $data['action'] ?? '';

if ($action === 'request_reset') {
    $email = isset($data['email']) ? trim($data['email']) : '';
    json_response(admin_auth_request_reset($pdo, $config, $email));
}

if ($action === 'bootstrap_login') {
    $requestToken = $data['admin_token'] ?? '';
    json_response(admin_auth_bootstrap_login($pdo, $config, $requestToken));
}

if ($action === 'login') {
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = $data['password'] ?? '';
    if (!$email || !$password) {
        json_error('Missing email or password', 400);
    }
    json_response(admin_auth_login($pdo, $config, $email, $password));
}

if ($action === 'set_password') {
    $resetToken = $data['reset_token'] ?? '';
    $password = $data['password'] ?? '';
    if (!$resetToken || !$password) {
        json_error('Missing reset token or password', 400);
    }
    json_response(admin_auth_set_password($pdo, $resetToken, $password));
}

if ($action === 'refresh') {
    $payload = require_admin_auth($config, $pdo);
    if (isset($payload['role']) && $payload['role'] === 'bootstrap') {
        json_error('Bootstrap token not allowed', 403);
    }
    json_response(admin_auth_refresh($pdo, $config, $payload));
}

json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

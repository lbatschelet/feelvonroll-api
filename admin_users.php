<?php
/**
 * Admin users endpoint for CRUD and reset flows.
 */

require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_users_service.php';
admin_handle_options('GET, POST, OPTIONS');

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';
try {
    $payload = require_admin_auth($config, $pdo);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;
    $role = $payload['role'] ?? 'user';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        json_response(admin_users_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? '';

    if ($action === 'create') {
        json_response(admin_users_create($pdo, $userId, $role, $data));
    }

    if ($action === 'reset') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        $targetId = isset($data['id']) ? intval($data['id']) : 0;
        if (!$targetId) {
            json_error('Missing user id', 400);
        }
        json_response(admin_users_reset($pdo, $userId, $targetId));
    }

    if ($action === 'update') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        $targetId = isset($data['id']) ? intval($data['id']) : 0;
        $email = isset($data['email']) ? trim($data['email']) : '';
        $name = isset($data['name']) ? trim($data['name']) : '';
        if (!$targetId || !$email || !$name) {
            json_error('Missing id, name or email', 400);
        }
        json_response(admin_users_update($pdo, $userId, $targetId, $email, $name));
    }

    if ($action === 'delete') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        $targetId = isset($data['id']) ? intval($data['id']) : 0;
        if (!$targetId) {
            json_error('Missing user id', 400);
        }
        json_response(admin_users_delete($pdo, $userId, $targetId));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

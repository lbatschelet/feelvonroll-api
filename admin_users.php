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
    $isAdmin = isset($payload['is_admin']) ? intval((bool)$payload['is_admin']) : 0;
    if (!$isAdmin && $userId) {
        $adminCheck = $pdo->prepare('SELECT is_admin FROM admin_users WHERE id = :id');
        $adminCheck->execute(['id' => $userId]);
        $isAdmin = intval($adminCheck->fetchColumn()) === 1 ? 1 : 0;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        $action = isset($_GET['action']) ? trim($_GET['action']) : '';
        if ($action === 'self') {
            $stmt = $pdo->prepare(
                'SELECT id, email, first_name, last_name, is_admin, must_set_password, last_login_at, created_at
                 FROM admin_users
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                json_error('Invalid user', 401);
            }
            json_response($user);
        }
        if (!$isAdmin) {
            json_error('Forbidden', 403);
        }
        json_response(admin_users_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? '';

    if ($action === 'create') {
        if (!$isAdmin) {
            json_error('Forbidden', 403);
        }
        json_response(admin_users_create($pdo, $userId, $role, $data));
    }

    if ($action === 'reset') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        if (!$isAdmin) {
            json_error('Forbidden', 403);
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
        if (!$isAdmin) {
            json_error('Forbidden', 403);
        }
        $targetId = isset($data['id']) ? intval($data['id']) : 0;
        $email = isset($data['email']) ? trim($data['email']) : '';
        $firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
        $lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
        $isAdminFlag = isset($data['is_admin']) ? intval((bool)$data['is_admin']) : 0;
        if (!$targetId || !$email || !$firstName) {
            json_error('Missing id, first name or email', 400);
        }
        json_response(admin_users_update($pdo, $userId, $targetId, $email, $firstName, $lastName, $isAdminFlag));
    }

    if ($action === 'delete') {
        if ($role === 'bootstrap') {
            json_error('Bootstrap token not allowed', 403);
        }
        if (!$isAdmin) {
            json_error('Forbidden', 403);
        }
        $targetId = isset($data['id']) ? intval($data['id']) : 0;
        if (!$targetId) {
            json_error('Missing user id', 400);
        }
        json_response(admin_users_delete($pdo, $userId, $targetId));
    }

    if ($action === 'self_update') {
        if (!$userId) {
            json_error('Invalid user', 401);
        }
        json_response(admin_users_update_self($pdo, $userId, $data));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

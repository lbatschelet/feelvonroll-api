<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token');
    exit;
}

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
    $count = intval($stmt->fetchColumn());
    echo json_encode(['user_count' => $count, 'bootstrap_required' => $count === 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? '';
$secret = $config['jwt_secret'] ?? $config['admin_token'] ?? '';

if (!$secret) {
    http_response_code(500);
    echo json_encode(['error' => 'JWT secret missing']);
    exit;
}

if ($action === 'bootstrap_login') {
    $adminToken = $config['admin_token'] ?? '';
    $requestToken = $data['admin_token'] ?? '';
    $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
    $count = intval($countStmt->fetchColumn());
    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Bootstrap disabled']);
        exit;
    }
    if (!$adminToken || !$requestToken || !hash_equals($adminToken, $requestToken)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $token = jwt_encode(['role' => 'bootstrap', 'user_id' => 0], $secret);
    echo json_encode(['token' => $token, 'bootstrap' => true]);
    exit;
}

if ($action === 'login') {
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = $data['password'] ?? '';
    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or password']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !$user['password_hash']) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    if (intval($user['must_set_password']) === 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Password reset required']);
        exit;
    }
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    $token = jwt_encode(['user_id' => intval($user['id']), 'email' => $user['email']], $secret);
    $update = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => $user['id']]);
    echo json_encode(['token' => $token, 'user' => ['id' => intval($user['id']), 'email' => $user['email'], 'name' => $user['name']]]);
    exit;
}

if ($action === 'set_password') {
    $resetToken = $data['reset_token'] ?? '';
    $password = $data['password'] ?? '';
    if (!$resetToken || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing reset token or password']);
        exit;
    }
    $tokenHash = hash('sha256', $resetToken);
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE reset_token_hash = :hash LIMIT 1');
    $stmt->execute(['hash' => $tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid reset token']);
        exit;
    }
    if ($user['reset_token_expires'] && strtotime($user['reset_token_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Reset token expired']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare(
        'UPDATE admin_users SET password_hash = :hash, must_set_password = 0, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = :id'
    );
    $update->execute(['hash' => $hash, 'id' => $user['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'refresh') {
    $payload = require_admin_auth($config);
    if (isset($payload['role']) && $payload['role'] === 'bootstrap') {
        http_response_code(403);
        echo json_encode(['error' => 'Bootstrap token not allowed']);
        exit;
    }
    $userId = intval($payload['user_id'] ?? 0);
    $email = $payload['email'] ?? '';
    $token = jwt_encode(['user_id' => $userId, 'email' => $email], $secret);
    echo json_encode(['token' => $token]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

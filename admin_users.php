<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';
$payload = require_admin_auth($config);
$userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;
$role = $payload['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($role === 'bootstrap') {
        http_response_code(403);
        echo json_encode(['error' => 'Bootstrap token not allowed']);
        exit;
    }
    $stmt = $pdo->query('SELECT id, email, name, must_set_password, last_login_at, created_at FROM admin_users ORDER BY created_at ASC');
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
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

if ($action === 'create') {
    $email = isset($data['email']) ? trim($data['email']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    if (!$email || !$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing name or email']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email']);
        exit;
    }
    if ($role === 'bootstrap') {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        if (intval($countStmt->fetchColumn()) > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Bootstrap disabled']);
            exit;
        }
    }
    $resetToken = base64url_encode(random_bytes(32));
    $resetHash = hash('sha256', $resetToken);
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (email, name, password_hash, must_set_password, reset_token_hash, reset_token_expires)
         VALUES (:email, :name, NULL, 1, :hash, :expires)'
    );
    $stmt->execute([
        'email' => $email,
        'name' => $name,
        'hash' => $resetHash,
        'expires' => $expires,
    ]);
    $newId = intval($pdo->lastInsertId());
    log_admin_action($pdo, $userId, 'admin_user_create', 'admin_users', [
        'id' => $newId,
        'email' => $email,
    ]);
    echo json_encode(['id' => $newId, 'reset_token' => $resetToken, 'reset_expires' => $expires]);
    exit;
}

if ($action === 'reset') {
    if ($role === 'bootstrap') {
        http_response_code(403);
        echo json_encode(['error' => 'Bootstrap token not allowed']);
        exit;
    }
    $targetId = isset($data['id']) ? intval($data['id']) : 0;
    if (!$targetId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user id']);
        exit;
    }
    $resetToken = base64url_encode(random_bytes(32));
    $resetHash = hash('sha256', $resetToken);
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $stmt = $pdo->prepare(
        'UPDATE admin_users SET must_set_password = 1, reset_token_hash = :hash, reset_token_expires = :expires WHERE id = :id'
    );
    $stmt->execute([
        'hash' => $resetHash,
        'expires' => $expires,
        'id' => $targetId,
    ]);
    log_admin_action($pdo, $userId, 'admin_user_reset', 'admin_users', ['id' => $targetId]);
    echo json_encode(['id' => $targetId, 'reset_token' => $resetToken, 'reset_expires' => $expires]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

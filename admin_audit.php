<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/config.php';
$payload = require_admin_auth($config);
$role = $payload['role'] ?? '';
if ($role === 'bootstrap') {
    http_response_code(403);
    echo json_encode(['error' => 'Bootstrap token not allowed']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = max(1, min($limit, 200));
$offset = max(0, $offset);

$stmt = $pdo->prepare(
    'SELECT admin_audit_logs.*, admin_users.email AS user_email
     FROM admin_audit_logs
     LEFT JOIN admin_users ON admin_users.id = admin_audit_logs.user_id
     ORDER BY admin_audit_logs.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$countStmt = $pdo->query('SELECT COUNT(*) FROM admin_audit_logs');
$total = intval($countStmt->fetchColumn());

echo json_encode([
    'items' => $rows,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
]);

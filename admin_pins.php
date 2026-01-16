<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
    exit;
}

$config = require __DIR__ . '/config.php';
$adminToken = $config['admin_token'] ?? '';
$requestToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';

if (!$adminToken || !$requestToken || !hash_equals($adminToken, $requestToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    $rows = $stmt->fetchAll();
    $result = array_map(function ($row) {
        $row['reasons'] = $row['reason_keys'] ? explode(',', $row['reason_keys']) : [];
        unset($row['reason_keys']);
        return $row;
    }, $rows);
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $id = intval($data['id']);
    $approved = isset($data['approved']) ? intval((bool)$data['approved']) : null;

    if ($approved === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing approved flag']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE pins SET approved = :approved WHERE id = :id');
    $stmt->execute(['approved' => $approved, 'id' => $id]);

    echo json_encode(['id' => $id, 'approved' => $approved]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

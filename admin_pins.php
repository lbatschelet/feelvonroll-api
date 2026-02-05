<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
    exit;
}

require_once __DIR__ . '/helpers.php';

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
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id AND pin_reasons.question_key = 'reasons'
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    $rows = $stmt->fetchAll();
    $result = array_map('normalize_pin_row', $rows);
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $action = $data['action'] ?? null;
    $ids = isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : null;

    if ($action === 'update_approval') {
        $approved = isset($data['approved']) ? intval((bool)$data['approved']) : null;
        if (!$ids || $approved === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing ids or approved flag']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$approved], array_map('intval', $ids));
        $stmt = $pdo->prepare("UPDATE pins SET approved = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        echo json_encode(['updated' => $stmt->rowCount()]);
        exit;
    }

    if ($action === 'delete') {
        if (!$ids) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing ids']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_map('intval', $ids);
        $stmt = $pdo->prepare("DELETE FROM pins WHERE id IN ($placeholders)");
        $stmt->execute($params);
        echo json_encode(['deleted' => $stmt->rowCount()]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

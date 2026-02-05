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
    $questionKey = isset($_GET['question_key']) ? trim($_GET['question_key']) : null;
    $params = [];
    $sql = 'SELECT question_key, option_key, sort, is_active FROM question_options';
    if ($questionKey) {
        $sql .= ' WHERE question_key = :question_key';
        $params['question_key'] = $questionKey;
    }
    $sql .= ' ORDER BY question_key ASC, sort ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
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

$action = $data['action'] ?? null;
$questionKey = isset($data['question_key']) ? trim($data['question_key']) : null;
$optionKey = isset($data['option_key']) ? trim($data['option_key']) : null;

if ($action === 'upsert') {
    $sort = isset($data['sort']) ? intval($data['sort']) : 0;
    $isActive = isset($data['is_active']) ? intval((bool)$data['is_active']) : 1;
    if (!$questionKey || !$optionKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question_key or option_key']);
        exit;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO question_options (question_key, option_key, sort, is_active)
         VALUES (:question_key, :option_key, :sort, :is_active)
         ON DUPLICATE KEY UPDATE sort = VALUES(sort), is_active = VALUES(is_active)'
    );
    $stmt->execute([
        'question_key' => $questionKey,
        'option_key' => $optionKey,
        'sort' => $sort,
        'is_active' => $isActive,
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    if (!$questionKey || !$optionKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question_key or option_key']);
        exit;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM question_options WHERE question_key = :question_key AND option_key = :option_key'
    );
    $stmt->execute(['question_key' => $questionKey, 'option_key' => $optionKey]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

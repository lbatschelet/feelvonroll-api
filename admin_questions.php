<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
$payload = require_admin_auth($config);
$userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;
$role = $payload['role'] ?? '';
if ($role === 'bootstrap') {
    http_response_code(403);
    echo json_encode(['error' => 'Bootstrap token not allowed']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        'SELECT question_key, type, required, sort, is_active, config
         FROM questions
         ORDER BY sort ASC'
    );
    $rows = $stmt->fetchAll();
    $result = array_map(function ($row) {
        $row['required'] = intval($row['required']);
        $row['sort'] = intval($row['sort']);
        $row['is_active'] = intval($row['is_active']);
        $row['config'] = $row['config'] ? json_decode($row['config'], true) : [];
        return $row;
    }, $rows);
    echo json_encode($result);
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

if ($action === 'upsert') {
    $key = isset($data['question_key']) ? trim($data['question_key']) : null;
    $type = isset($data['type']) ? trim($data['type']) : null;
    $required = isset($data['required']) ? intval((bool)$data['required']) : 0;
    $sort = isset($data['sort']) ? intval($data['sort']) : 0;
    $isActive = isset($data['is_active']) ? intval((bool)$data['is_active']) : 1;
    $config = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];

    if (!$key || !$type) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question_key or type']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO questions (question_key, type, required, sort, is_active, config)
         VALUES (:key, :type, :required, :sort, :is_active, :config)
         ON DUPLICATE KEY UPDATE
           type = VALUES(type),
           required = VALUES(required),
           sort = VALUES(sort),
           is_active = VALUES(is_active),
           config = VALUES(config)'
    );
    $stmt->execute([
        'key' => $key,
        'type' => $type,
        'required' => $required,
        'sort' => $sort,
        'is_active' => $isActive,
        'config' => json_encode($config),
    ]);
    log_admin_action($pdo, $userId, 'question_upsert', 'questions', ['question_key' => $key]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $key = isset($data['question_key']) ? trim($data['question_key']) : null;
    if (!$key) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing question_key']);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM questions WHERE question_key = :key');
    $stmt->execute(['key' => $key]);
    log_admin_action($pdo, $userId, 'question_delete', 'questions', ['question_key' => $key]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

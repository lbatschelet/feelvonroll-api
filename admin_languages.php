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
    $stmt = $pdo->query('SELECT lang, label, enabled FROM languages ORDER BY label ASC');
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
$lang = isset($data['lang']) ? trim($data['lang']) : null;

if ($action === 'upsert') {
    $label = isset($data['label']) ? trim($data['label']) : '';
    $enabled = isset($data['enabled']) ? intval((bool)$data['enabled']) : 1;
    if (!$lang || !$label) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lang or label']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO languages (lang, label, enabled) VALUES (:lang, :label, :enabled)
         ON DUPLICATE KEY UPDATE label = VALUES(label), enabled = VALUES(enabled)'
    );
    $stmt->execute(['lang' => $lang, 'label' => $label, 'enabled' => $enabled]);
    log_admin_action($pdo, $userId, 'language_upsert', 'languages', ['lang' => $lang]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'toggle') {
    $enabled = isset($data['enabled']) ? intval((bool)$data['enabled']) : null;
    if (!$lang || $enabled === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lang or enabled flag']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE languages SET enabled = :enabled WHERE lang = :lang');
    $stmt->execute(['lang' => $lang, 'enabled' => $enabled]);
    log_admin_action($pdo, $userId, 'language_toggle', 'languages', ['lang' => $lang, 'enabled' => $enabled]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    if (!$lang) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing lang']);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM languages WHERE lang = :lang');
    $stmt->execute(['lang' => $lang]);
    log_admin_action($pdo, $userId, 'language_delete', 'languages', ['lang' => $lang]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

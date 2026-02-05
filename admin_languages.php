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
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

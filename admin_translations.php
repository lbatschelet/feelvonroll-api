<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? null;
$key = isset($data['translation_key']) ? trim($data['translation_key']) : null;
$lang = isset($data['lang']) ? trim($data['lang']) : null;

if ($action === 'upsert') {
    $text = isset($data['text']) ? trim($data['text']) : '';
    if (!$key || !$lang) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing translation_key or lang']);
        exit;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO translations (translation_key, lang, text)
         VALUES (:translation_key, :lang, :text)
         ON DUPLICATE KEY UPDATE text = VALUES(text)'
    );
    $stmt->execute(['translation_key' => $key, 'lang' => $lang, 'text' => $text]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    if (!$key || !$lang) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing translation_key or lang']);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM translations WHERE translation_key = :key AND lang = :lang');
    $stmt->execute(['key' => $key, 'lang' => $lang]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);

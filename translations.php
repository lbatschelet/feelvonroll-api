<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'de';
$prefix = isset($_GET['prefix']) ? trim($_GET['prefix']) : null;

if (!$lang || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $lang)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid lang']);
    exit;
}

$pdo = require __DIR__ . '/db.php';

$sql = 'SELECT translation_key, text FROM translations WHERE lang = :lang';
$params = ['lang' => $lang];

if ($prefix) {
    $sql .= ' AND translation_key LIKE :prefix';
    $params['prefix'] = $prefix . '%';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $row) {
    $result[$row['translation_key']] = $row['text'];
}

echo json_encode($result);

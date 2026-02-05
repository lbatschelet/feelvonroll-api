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

$pdo = require __DIR__ . '/db.php';

$stmt = $pdo->query(
    'SELECT lang, label, enabled
     FROM languages
     WHERE enabled = 1
     ORDER BY label ASC'
);

$rows = $stmt->fetchAll();
$result = array_map(function ($row) {
    return [
        'lang' => $row['lang'],
        'label' => $row['label'],
    ];
}, $rows);

echo json_encode($result);

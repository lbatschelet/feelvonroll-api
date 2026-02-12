<?php
/**
 * Public stations endpoint: get station info by key.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
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

// TEMPORARY DEBUG — remove after fixing
if (isset($_GET['debug'])) {
    echo json_encode([
        'query_string' => $_SERVER['QUERY_STRING'] ?? '(not set)',
        'request_uri'  => $_SERVER['REQUEST_URI'] ?? '(not set)',
        'get_params'   => $_GET,
    ], JSON_PRETTY_PRINT);
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/stations_service.php';

try {
    $key = isset($_GET['station_key']) ? trim($_GET['station_key']) : '';
    if (!$key) {
        json_error('Missing station key', 400);
    }

    $pdo = require __DIR__ . '/db.php';

    json_response(public_station_get($pdo, $key));
} catch (Throwable $error) {
    handle_api_exception($error);
}

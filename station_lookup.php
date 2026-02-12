<?php
/**
 * Public station lookup endpoint.
 * GET ?station_key=<key> — returns station info (camera position, questionnaire).
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

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/stations_service.php';

try {
    $key = isset($_GET['station_key']) ? trim($_GET['station_key']) : '';
    if (!$key) {
        json_error('Missing station_key parameter', 400);
    }

    $pdo = require __DIR__ . '/db.php';

    json_response(public_station_get($pdo, $key));
} catch (Throwable $error) {
    handle_api_exception($error);
}

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

// TEMPORARY: trace every step to find where "api_debug" comes from
if (isset($_GET['trace'])) {
    $trace = [];
    $trace['step1_GET_before_includes'] = $_GET;

    require_once __DIR__ . '/helpers.php';
    $trace['step2_GET_after_helpers'] = $_GET;

    require_once __DIR__ . '/services/stations_service.php';
    $trace['step3_GET_after_service'] = $_GET;

    $key = isset($_GET['station_key']) ? trim($_GET['station_key']) : '';
    $trace['step4_key_value'] = $key;

    $pdo = require __DIR__ . '/db.php';
    $trace['step5_GET_after_db'] = $_GET;
    $trace['step5_key_still'] = $key;

    // Try the actual query
    $stmt = $pdo->prepare('SELECT station_key, name, camera_x, camera_y FROM stations LIMIT 5');
    $stmt->execute();
    $trace['step6_all_stations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($trace, JSON_PRETTY_PRINT);
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

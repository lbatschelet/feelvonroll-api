<?php
/**
 * Public pins endpoint for listing and creating pins.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/public_pins_service.php';

$pdo = require __DIR__ . '/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $floor = isset($_GET['floor']) ? intval($_GET['floor']) : null;
        echo json_encode(public_pins_list($pdo, $floor));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_request();
        echo json_encode(public_pins_create($pdo, $data));
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $error) {
    handle_api_exception($error);
}

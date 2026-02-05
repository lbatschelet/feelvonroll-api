<?php
/**
 * Public questions endpoint for questionnaire config.
 */

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

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/public_questions_service.php';

try {
    $lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'de';
    if (!$lang || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $lang)) {
        json_error('Invalid lang', 400);
    }

    $pdo = require __DIR__ . '/db.php';

    echo json_encode(public_questions_list($pdo, $lang));
} catch (Throwable $error) {
    handle_api_exception($error);
}

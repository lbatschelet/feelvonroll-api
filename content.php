<?php
/**
 * Public content endpoint for fetching content pages (e.g. about text).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/lib/errors.php';
require_once __DIR__ . '/services/public_content_service.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new ApiError('Method not allowed', 405);
    }

    $key = isset($_GET['key']) ? trim($_GET['key']) : '';
    $lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'de';

    if (!$key || !preg_match('/^[a-z0-9_-]+$/i', $key)) {
        throw new ApiError('Invalid or missing key', 400);
    }
    if (!$lang || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $lang)) {
        throw new ApiError('Invalid lang', 400);
    }

    $pdo = require __DIR__ . '/db.php';

    echo json_encode(public_content_get($pdo, $key, $lang));
} catch (Throwable $error) {
    handle_api_exception($error);
}

<?php
/**
 * Public translations endpoint for translation lookups.
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

require_once __DIR__ . '/lib/errors.php';
require_once __DIR__ . '/services/public_translations_service.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new ApiError('Method not allowed', 405);
    }

    $lang = isset($_GET['lang']) ? trim($_GET['lang']) : 'de';
    $prefix = isset($_GET['prefix']) ? trim($_GET['prefix']) : null;

    if (!$lang || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $lang)) {
        throw new ApiError('Invalid lang', 400);
    }

    $pdo = require __DIR__ . '/db.php';

    echo json_encode(public_translations_list($pdo, $lang, $prefix));
} catch (Throwable $error) {
    handle_api_exception($error);
}

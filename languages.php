<?php
/**
 * Public languages endpoint for enabled languages.
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
require_once __DIR__ . '/services/public_languages_service.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new ApiError('Method not allowed', 405);
    }

    $pdo = require __DIR__ . '/db.php';

    echo json_encode(public_languages_list($pdo));
} catch (Throwable $error) {
    handle_api_exception($error);
}

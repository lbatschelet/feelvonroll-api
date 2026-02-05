<?php
/**
 * Admin audit endpoint for audit log retrieval.
 */

require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_audit_service.php';
admin_handle_options('GET, OPTIONS');

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';
try {
    $payload = require_admin_auth($config, $pdo);
    $role = $payload['role'] ?? '';
    if ($role === 'bootstrap') {
        json_error('Bootstrap token not allowed', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('Method not allowed', 405);
    }

    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = max(1, min($limit, 200));
    $offset = max(0, $offset);

    json_response(admin_audit_list($pdo, $limit, $offset));
} catch (Throwable $error) {
    handle_api_exception($error);
}

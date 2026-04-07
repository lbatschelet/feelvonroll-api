<?php
/**
 * Admin LV95 calibration endpoint.
 *
 * Actions (POST):
 * - get_active: returns active calibration (or null)
 * - preview: computes params + diagnostics for given points (no DB write)
 * - save: stores calibration (activates it) + optionally updates stations.lv95_e/n
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/lv95_calibration_service.php';

admin_handle_options('POST, OPTIONS');

try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();
    $action = $data['action'] ?? '';

    if ($action === 'get_active') {
        json_response(admin_lv95_calibration_get_active($pdo));
    }

    if ($action === 'preview') {
        json_response(admin_lv95_calibration_preview($pdo, $userId, $data));
    }

    if ($action === 'save') {
        json_response(admin_lv95_calibration_save($pdo, $userId, $data));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}


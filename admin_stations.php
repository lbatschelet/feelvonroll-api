<?php
/**
 * Admin stations endpoint: CRUD for QR-code stations.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/stations_service.php';

admin_handle_options('GET, POST, DELETE, OPTIONS');

try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(admin_stations_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_request();
        $action = $data['action'] ?? '';

        if ($action === 'upsert') {
            json_response(admin_stations_upsert($pdo, $userId, $data));
        }

        if ($action === 'delete') {
            $id = intval($data['id'] ?? 0);
            if (!$id) {
                json_error('Missing id', 400);
            }
            json_response(admin_stations_delete($pdo, $userId, $id));
        }

        json_error('Invalid action', 400);
    }

    json_error('Method not allowed', 405);
} catch (Throwable $error) {
    handle_api_exception($error);
}

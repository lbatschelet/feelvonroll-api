<?php
/**
 * Admin pins endpoint for listing and approval updates.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_pins_service.php';

admin_handle_options('GET, POST, OPTIONS');

try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(admin_pins_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_request();

        $action = $data['action'] ?? null;
        $ids = isset($data['ids']) && is_array($data['ids']) ? $data['ids'] : null;

        if ($action === 'update_approval') {
            $approved = isset($data['approved']) ? intval((bool)$data['approved']) : null;
            if (!$ids || $approved === null) {
                json_error('Missing ids or approved flag', 400);
            }

            json_response(admin_pins_update_approval($pdo, $userId, $ids, $approved));
        }

        if ($action === 'delete') {
            if (!$ids) {
                json_error('Missing ids', 400);
            }

            json_response(admin_pins_delete($pdo, $userId, $ids));
        }

        json_error('Invalid action', 400);
    }

    json_error('Method not allowed', 405);
} catch (Throwable $error) {
    handle_api_exception($error);
}

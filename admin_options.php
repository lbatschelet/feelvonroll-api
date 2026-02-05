<?php
/**
 * Admin options endpoint for question option management.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_options_service.php';
admin_handle_options('GET, POST, OPTIONS');
try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $questionKey = isset($_GET['question_key']) ? trim($_GET['question_key']) : null;
        json_response(admin_options_list($pdo, $questionKey));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? null;
    $questionKey = isset($data['question_key']) ? trim($data['question_key']) : null;
    $optionKey = isset($data['option_key']) ? trim($data['option_key']) : null;

    if ($action === 'upsert') {
        $sort = isset($data['sort']) ? intval($data['sort']) : 0;
        $isActive = isset($data['is_active']) ? intval((bool)$data['is_active']) : 1;
        if (!$questionKey || !$optionKey) {
            json_error('Missing question_key or option_key', 400);
        }
        $data['sort'] = $sort;
        $data['is_active'] = $isActive;
        json_response(admin_options_upsert($pdo, $userId, $data));
    }

    if ($action === 'delete') {
        if (!$questionKey || !$optionKey) {
            json_error('Missing question_key or option_key', 400);
        }
        json_response(admin_options_delete($pdo, $userId, $questionKey, $optionKey));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

<?php
/**
 * Admin questions endpoint for question management.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_questions_service.php';
admin_handle_options('GET, POST, OPTIONS');
try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $result = admin_questions_list($pdo);
        json_response($result);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? null;

    if ($action === 'upsert') {
        $key = isset($data['question_key']) ? trim($data['question_key']) : null;
        $type = isset($data['type']) ? trim($data['type']) : null;
        if (!$key || !$type) {
            json_error('Missing question_key or type', 400);
        }
        json_response(admin_questions_upsert($pdo, $userId, $data));
    }

    if ($action === 'delete') {
        $key = isset($data['question_key']) ? trim($data['question_key']) : null;
        if (!$key) {
            json_error('Missing question_key', 400);
        }
        json_response(admin_questions_delete($pdo, $userId, $key));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

<?php
/**
 * Admin translations endpoint for translation management.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_translations_service.php';
admin_handle_options('POST, OPTIONS');
try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? null;
    $key = isset($data['translation_key']) ? trim($data['translation_key']) : null;
    $lang = isset($data['lang']) ? trim($data['lang']) : null;

    if ($action === 'upsert') {
        $text = isset($data['text']) ? trim($data['text']) : '';
        if (!$key || !$lang) {
            json_error('Missing translation_key or lang', 400);
        }
        json_response(admin_translations_upsert($pdo, $userId, $key, $lang, $text));
    }

    if ($action === 'delete') {
        if (!$key || !$lang) {
            json_error('Missing translation_key or lang', 400);
        }
        json_response(admin_translations_delete($pdo, $userId, $key, $lang));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

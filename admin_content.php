<?php
/**
 * Admin content endpoint for managing content pages.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_content_service.php';
admin_handle_options('GET, POST, OPTIONS');
try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pageKey = isset($_GET['page_key']) ? trim($_GET['page_key']) : '';
        if (!$pageKey) {
            json_error('Missing page_key', 400);
        }
        json_response(admin_content_list($pdo, $pageKey));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();
    $action = $data['action'] ?? null;

    if ($action === 'upsert') {
        $pageKey = isset($data['page_key']) ? trim($data['page_key']) : null;
        $lang = isset($data['lang']) ? trim($data['lang']) : null;
        $body = $data['body'] ?? '';
        if (!$pageKey || !$lang) {
            json_error('Missing page_key or lang', 400);
        }
        json_response(admin_content_upsert($pdo, $userId, $pageKey, $lang, $body));
    }

    if ($action === 'delete') {
        $pageKey = isset($data['page_key']) ? trim($data['page_key']) : null;
        $lang = isset($data['lang']) ? trim($data['lang']) : null;
        if (!$pageKey || !$lang) {
            json_error('Missing page_key or lang', 400);
        }
        json_response(admin_content_delete($pdo, $userId, $pageKey, $lang));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

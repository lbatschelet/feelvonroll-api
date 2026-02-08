<?php
/**
 * Admin languages endpoint for language management.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/admin_languages_service.php';
admin_handle_options('GET, POST, OPTIONS');
try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_response(admin_languages_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    $action = $data['action'] ?? null;
    $lang = isset($data['lang']) ? trim($data['lang']) : null;

    if ($action === 'upsert') {
        $label = isset($data['label']) ? trim($data['label']) : '';
        $enabled = isset($data['enabled']) ? intval((bool)$data['enabled']) : 1;
        if (!$lang || !$label) {
            json_error('Missing lang or label', 400);
        }
        json_response(admin_languages_upsert($pdo, $userId, $lang, $label, $enabled));
    }

    if ($action === 'toggle') {
        $enabled = isset($data['enabled']) ? intval((bool)$data['enabled']) : null;
        if (!$lang || $enabled === null) {
            json_error('Missing lang or enabled flag', 400);
        }
        // Force-enable bypasses the completeness check.
        $force = !empty($data['force']);
        if ($enabled && $force) {
            $stmt = $pdo->prepare('UPDATE languages SET enabled = 1 WHERE lang = :lang');
            $stmt->execute(['lang' => $lang]);
            log_admin_action($pdo, $userId, 'language_toggle', 'languages', ['lang' => $lang, 'enabled' => 1, 'forced' => true]);
            json_response(['ok' => true]);
        }
        json_response(admin_languages_toggle($pdo, $userId, $lang, $enabled));
    }

    if ($action === 'check') {
        if (!$lang) {
            json_error('Missing lang', 400);
        }
        json_response(admin_languages_check($pdo, $lang));
    }

    if ($action === 'delete') {
        if (!$lang) {
            json_error('Missing lang', 400);
        }
        json_response(admin_languages_delete($pdo, $userId, $lang));
    }

    json_error('Invalid action', 400);
} catch (Throwable $error) {
    handle_api_exception($error);
}

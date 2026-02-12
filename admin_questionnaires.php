<?php
/**
 * Admin questionnaires endpoint: CRUD for questionnaires and slots.
 */

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/admin_common.php';
require_once __DIR__ . '/services/questionnaire_config_service.php';

admin_handle_options('GET, POST, DELETE, OPTIONS');

try {
    [$config, $pdo, $payload] = admin_init($config);
    $userId = isset($payload['user_id']) ? intval($payload['user_id']) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Detail view: GET ?id=N returns questionnaire with slots
        $detailId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($detailId) {
            json_response(admin_questionnaire_detail($pdo, $detailId));
        }
        json_response(admin_questionnaires_list($pdo));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_request();
        $action = $data['action'] ?? '';

        if ($action === 'save_full') {
            json_response(admin_questionnaire_save_full($pdo, $userId, $data));
        }

        if ($action === 'upsert') {
            json_response(admin_questionnaires_upsert($pdo, $userId, $data));
        }

        if ($action === 'save_slots') {
            $qid = intval($data['questionnaire_id'] ?? 0);
            $slots = $data['slots'] ?? [];
            if (!$qid) {
                json_error('Missing questionnaire_id', 400);
            }
            json_response(admin_questionnaire_slots_save($pdo, $userId, $qid, $slots));
        }

        if ($action === 'delete') {
            $id = intval($data['id'] ?? 0);
            if (!$id) {
                json_error('Missing id', 400);
            }
            json_response(admin_questionnaires_delete($pdo, $userId, $id));
        }

        json_error('Invalid action', 400);
    }

    json_error('Method not allowed', 405);
} catch (Throwable $error) {
    handle_api_exception($error);
}

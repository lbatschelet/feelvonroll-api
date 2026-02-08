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
        $action = isset($_GET['action']) ? trim($_GET['action']) : '';
        if ($action === 'export_csv') {
            $rows = admin_pins_export_rows($pdo);
            $timestamp = date('Y-m-d_His');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pins_' . $timestamp . '.csv"');
            $output = fopen('php://output', 'w');
            if (!$rows) {
                fputcsv($output, []);
                fclose($output);
                exit;
            }
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        }
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

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
    $isAdmin = isset($payload['is_admin']) ? intval((bool)$payload['is_admin']) : 0;
    if (!$isAdmin && isset($payload['user_id'])) {
        $adminCheck = $pdo->prepare('SELECT is_admin FROM admin_users WHERE id = :id');
        $adminCheck->execute(['id' => intval($payload['user_id'])]);
        $isAdmin = intval($adminCheck->fetchColumn()) === 1 ? 1 : 0;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = isset($_GET['action']) ? trim($_GET['action']) : '';
        if ($action === 'export_csv') {
            $rows = admin_pins_export_rows($pdo);
            $headers = admin_pins_export_header_keys($rows);
            $timestamp = date('Y-m-d_His');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pins_' . $timestamp . '.csv"');
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = array_key_exists($h, $row) ? $row[$h] : '';
                }
                fputcsv($output, $line);
            }
            fclose($output);
            exit;
        }
        if ($action === 'export_csv_long') {
            $rows = admin_pins_export_long_rows($pdo);
            $headers = admin_pins_export_long_header_keys();
            $timestamp = date('Y-m-d_His');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pins_long_' . $timestamp . '.csv"');
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = array_key_exists($h, $row) ? $row[$h] : '';
                }
                fputcsv($output, $line);
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
            // NOTE: approved is a 3-state flag:
            //  1 = approved, 0 = pending, -1 = rejected.
            // Do NOT cast via (bool) here or -1 would become true -> 1.
            $approved = isset($data['approved']) ? intval($data['approved']) : null;
            if (!$ids || $approved === null) {
                json_error('Missing ids or approved flag', 400);
            }
            if (!in_array($approved, [-1, 0, 1], true)) {
                json_error('Invalid approved value (expected -1, 0, or 1)', 400);
            }

            json_response(admin_pins_update_approval($pdo, $userId, $ids, $approved));
        }

        if ($action === 'delete') {
            if (!$isAdmin) {
                json_error('Forbidden', 403);
            }
            if (!$ids) {
                json_error('Missing ids', 400);
            }
            $confirm = isset($data['confirm']) ? intval((bool)$data['confirm']) : 0;
            if (!$confirm) {
                json_error('Missing confirm flag', 400);
            }

            json_response(admin_pins_delete($pdo, $userId, $ids));
        }

        json_error('Invalid action', 400);
    }

    json_error('Method not allowed', 405);
} catch (Throwable $error) {
    handle_api_exception($error);
}

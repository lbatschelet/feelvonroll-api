<?php
/**
 * Shared admin endpoint helpers for CORS and auth bootstrapping.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Handles CORS preflight for admin endpoints.
 *
 * @param string $methods
 * @param string $headers
 * @return void
 */
function admin_handle_options(string $methods, string $headers = 'Content-Type, Authorization'): void
{
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: $methods");
        header("Access-Control-Allow-Headers: $headers");
        exit;
    }
}

/**
 * Initializes admin endpoint config, db, and auth payload.
 *
 * @param array|null $config
 * @param bool $allowBootstrap
 * @return array
 * @throws ApiError
 */
function admin_init(array $config = null, bool $allowBootstrap = false): array
{
    if (!$config) {
        $config = require __DIR__ . '/config.php';
    }
    $pdo = require __DIR__ . '/db.php';
    $payload = require_admin_auth($config, $pdo);
    $role = $payload['role'] ?? '';
    if (!$allowBootstrap && $role === 'bootstrap') {
        json_error('Bootstrap token not allowed', 403);
    }
    return [$config, $pdo, $payload];
}

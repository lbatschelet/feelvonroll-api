<?php
/**
 * Temporary debug script — shows raw request info.
 * DELETE THIS FILE after debugging!
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode([
    'query_string' => $_SERVER['QUERY_STRING'] ?? '(not set)',
    'request_uri'  => $_SERVER['REQUEST_URI'] ?? '(not set)',
    'get_params'   => $_GET,
    'php_version'  => PHP_VERSION,
], JSON_PRETTY_PRINT);

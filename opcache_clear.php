<?php
/**
 * Temporary script to clear PHP OPcache.
 * Call once, then delete this file.
 */
header('Content-Type: application/json');

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo json_encode(['ok' => true, 'message' => 'OPcache cleared']);
} else {
    echo json_encode(['ok' => false, 'message' => 'OPcache not available']);
}

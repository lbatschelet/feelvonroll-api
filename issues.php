<?php
/**
 * Public issue submission endpoint.
 * Accepts POST requests with issue details and creates a GitHub issue.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/services/public_issues_service.php';

$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed', 405);
    }

    $data = json_request();

    // Validate required fields
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    if (empty($title) || empty($description)) {
        json_error('Title and description are required.', 400);
    }

    // Honeypot check — silently accept but don't create an issue
    if (issue_is_spam($data)) {
        json_response(['success' => true, 'issue_url' => '']);
    }

    // Rate limiting
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Take the first IP if X-Forwarded-For has multiple
    $ip = trim(explode(',', $ip)[0]);

    if (!issue_check_rate_limit($pdo, $ip)) {
        json_error('Too many submissions. Please try again later.', 429);
    }

    // Create the GitHub issue
    $result = issue_create_github_issue($data, $config);

    // Record the submission for rate limiting
    issue_record_submission($pdo, $ip);

    // Opportunistic cleanup of old entries (1 in 10 chance)
    if (random_int(1, 10) === 1) {
        issue_cleanup_old_entries($pdo);
    }

    json_response([
        'success'   => true,
        'issue_url' => $result['issue_url'],
    ]);
} catch (Throwable $error) {
    handle_api_exception($error);
}

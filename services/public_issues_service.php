<?php
/**
 * Public issue submission service.
 * Validates input, enforces rate limits, and creates GitHub issues.
 */

/**
 * Maximum submissions allowed per IP within the rate window.
 */
define('ISSUE_RATE_LIMIT', 3);

/**
 * Rate window in seconds (1 hour).
 */
define('ISSUE_RATE_WINDOW', 3600);

/**
 * Label mapping from form categories to GitHub labels.
 */
function issue_category_label(string $category): string
{
    return match ($category) {
        'bug'     => 'bug',
        'feature' => 'enhancement',
        default   => 'question',
    };
}

/**
 * Title prefix based on category.
 */
function issue_title_prefix(string $category): string
{
    return match ($category) {
        'bug'     => '[BUG] ',
        'feature' => '[FEATURE] ',
        default   => '[FEEDBACK] ',
    };
}

/**
 * Check the honeypot field. Returns true if the submission looks like spam.
 */
function issue_is_spam(array $data): bool
{
    return !empty($data['website'] ?? '');
}

/**
 * Check rate limit for the given IP. Returns true if within limits.
 */
function issue_check_rate_limit(PDO $pdo, string $ip): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM issue_submissions
         WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)'
    );
    $stmt->execute(['ip' => $ip, 'window' => ISSUE_RATE_WINDOW]);
    $count = (int) $stmt->fetchColumn();
    return $count < ISSUE_RATE_LIMIT;
}

/**
 * Record a submission for rate-limiting purposes.
 */
function issue_record_submission(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare('INSERT INTO issue_submissions (ip_address) VALUES (:ip)');
    $stmt->execute(['ip' => $ip]);
}

/**
 * Clean up old rate-limit entries (older than the window).
 * Called opportunistically to keep the table small.
 */
function issue_cleanup_old_entries(PDO $pdo): void
{
    $pdo->exec(
        'DELETE FROM issue_submissions WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (ISSUE_RATE_WINDOW * 2) . ' SECOND)'
    );
}

/**
 * Build the GitHub issue body in Markdown.
 */
function issue_build_body(array $data): string
{
    $category = $data['category'] ?? 'other';
    $description = $data['description'] ?? '';
    $steps = $data['steps'] ?? '';
    $debug = $data['debug'] ?? [];

    $body = '';

    // Description
    if ($category === 'bug') {
        $body .= "**Describe the bug**\n";
    } else {
        $body .= "**Description**\n";
    }
    $body .= $description . "\n\n";

    // Steps to reproduce (bugs only)
    if ($category === 'bug' && !empty($steps)) {
        $body .= "**To Reproduce**\n";
        $body .= $steps . "\n\n";
    }

    // Debug information
    if (!empty($debug) && is_array($debug)) {
        $body .= "**Debug Information**\n\n";
        $body .= "| Key | Value |\n|-----|-------|\n";
        foreach ($debug as $key => $value) {
            $safeKey = str_replace('|', '\\|', (string) $key);
            $safeValue = str_replace('|', '\\|', (string) $value);
            $body .= "| {$safeKey} | {$safeValue} |\n";
        }
        $body .= "\n";
    }

    $body .= "---\n*Submitted via the feelvonRoll issue form*\n";

    return $body;
}

/**
 * Create a GitHub issue via the REST API.
 *
 * @return array{issue_url: string}
 * @throws ApiError
 */
function issue_create_github_issue(array $data, array $config): array
{
    $token = $config['github_token'] ?? '';
    $repo = $config['github_repo'] ?? '';

    if (empty($token) || empty($repo)) {
        throw new ApiError('Issue submission is not configured.', 503);
    }

    $category = $data['category'] ?? 'other';
    $title = issue_title_prefix($category) . ($data['title'] ?? 'Untitled');
    $body = issue_build_body($data);
    $label = issue_category_label($category);

    $url = "https://api.github.com/repos/{$repo}/issues";

    $issueData = [
        'title'  => $title,
        'body'   => $body,
        'labels' => [$label],
    ];

    // Auto-assign configured users (comma-separated GitHub usernames)
    $assignees = $config['github_assignees'] ?? '';
    if (!empty($assignees)) {
        $issueData['assignees'] = array_map('trim', explode(',', $assignees));
    }

    $payload = json_encode($issueData);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/vnd.github+json',
            "Authorization: Bearer {$token}",
            'User-Agent: feelvonRoll-API',
            'X-GitHub-Api-Version: 2022-11-28',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new ApiError('Failed to contact GitHub: ' . $curlError, 502);
    }

    $result = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $ghMessage = $result['message'] ?? 'Unknown GitHub error';
        throw new ApiError("GitHub API error: {$ghMessage}", 502);
    }

    return [
        'issue_url' => $result['html_url'] ?? '',
    ];
}

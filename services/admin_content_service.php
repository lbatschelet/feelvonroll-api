<?php
/**
 * Admin content service for managing content pages.
 * Exports: admin_content_list, admin_content_upsert, admin_content_delete.
 */

require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/errors.php';

/**
 * Lists all content entries for a given page key.
 *
 * @param PDO $pdo
 * @param string $pageKey
 * @return array
 */
function admin_content_list(PDO $pdo, string $pageKey): array
{
    $stmt = $pdo->prepare(
        'SELECT page_key, lang, body, updated_at FROM content_pages WHERE page_key = :key ORDER BY lang'
    );
    $stmt->execute(['key' => $pageKey]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inserts or updates a content page entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $pageKey
 * @param string $lang
 * @param string $body
 * @return array
 */
function admin_content_upsert(PDO $pdo, ?int $userId, string $pageKey, string $lang, string $body): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO content_pages (page_key, lang, body)
         VALUES (:key, :lang, :body)
         ON DUPLICATE KEY UPDATE body = VALUES(body)'
    );
    $stmt->execute(['key' => $pageKey, 'lang' => $lang, 'body' => $body]);

    log_admin_action($pdo, $userId, 'content_upsert', "$pageKey/$lang");

    return ['ok' => true];
}

/**
 * Deletes a content page entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $pageKey
 * @param string $lang
 * @return array
 */
function admin_content_delete(PDO $pdo, ?int $userId, string $pageKey, string $lang): array
{
    $stmt = $pdo->prepare(
        'DELETE FROM content_pages WHERE page_key = :key AND lang = :lang'
    );
    $stmt->execute(['key' => $pageKey, 'lang' => $lang]);

    log_admin_action($pdo, $userId, 'content_delete', "$pageKey/$lang");

    return ['ok' => true];
}

<?php
/**
 * Public content service for fetching content pages.
 * Exports: public_content_get.
 */

/**
 * Returns a content page for a given key and language.
 * Falls back to 'de' if the requested language has no entry.
 *
 * @param PDO $pdo
 * @param string $pageKey
 * @param string $lang
 * @return array { body: string, updated_at: string|null }
 */
function public_content_get(PDO $pdo, string $pageKey, string $lang): array
{
    // Debug: list all rows for this page_key to verify data is accessible
    $debug = $pdo->prepare('SELECT page_key, lang, LENGTH(body) as body_len, updated_at FROM content_pages WHERE page_key = :key');
    $debug->execute(['key' => $pageKey]);
    $allRows = $debug->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT body, updated_at FROM content_pages WHERE page_key = :key AND lang = :lang LIMIT 1'
    );
    $stmt->execute(['key' => $pageKey, 'lang' => $lang]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return ['body' => $row['body'], 'updated_at' => $row['updated_at']];
    }

    // Fallback to 'de'
    if ($lang !== 'de') {
        $stmt->execute(['key' => $pageKey, 'lang' => 'de']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['body' => $row['body'], 'updated_at' => $row['updated_at']];
        }
    }

    // Return debug info when nothing found
    return ['body' => '', 'updated_at' => null, '_debug_rows' => $allRows, '_query_key' => $pageKey, '_query_lang' => $lang];
}

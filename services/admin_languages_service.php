<?php
/**
 * Admin languages service for language CRUD + completeness check.
 * Exports: admin_languages_list, admin_languages_upsert, admin_languages_toggle,
 *          admin_languages_delete, admin_languages_check.
 */

/** Reference language used as the "complete" baseline. */
define('REFERENCE_LANG', 'de');

/**
 * Returns all languages.
 */
function admin_languages_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT lang, label, enabled FROM languages ORDER BY lang ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['enabled'] = (int) $row['enabled'];
    }
    return $rows;
}

/**
 * Creates or updates a language entry.
 * New languages default to disabled (enabled = 0).
 */
function admin_languages_upsert(PDO $pdo, ?int $userId, string $lang, string $label, ?int $enabled): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO languages (lang, label, enabled)
         VALUES (:lang, :label, :enabled)
         ON DUPLICATE KEY UPDATE label = VALUES(label), enabled = VALUES(enabled)'
    );
    $stmt->execute([
        'lang' => $lang,
        'label' => $label,
        'enabled' => $enabled ?? 0,
    ]);
    log_admin_action($pdo, $userId, 'language_upsert', 'languages', ['lang' => $lang, 'label' => $label]);
    return ['ok' => true];
}

/**
 * Toggles a language enabled flag.
 * When enabling, performs a completeness check first. If translations are
 * missing the language is NOT enabled and the missing items are returned.
 */
function admin_languages_toggle(PDO $pdo, ?int $userId, string $lang, int $enabled): array
{
    // Disabling always succeeds immediately.
    if (!$enabled) {
        $stmt = $pdo->prepare('UPDATE languages SET enabled = :enabled WHERE lang = :lang');
        $stmt->execute(['lang' => $lang, 'enabled' => 0]);
        log_admin_action($pdo, $userId, 'language_toggle', 'languages', ['lang' => $lang, 'enabled' => 0]);
        return ['ok' => true];
    }

    // Enabling: run completeness check first.
    $missing = admin_languages_check($pdo, $lang);

    if (!empty($missing['translations']) || !empty($missing['content_pages'])) {
        return [
            'ok' => false,
            'incomplete' => true,
            'missing' => $missing,
        ];
    }

    $stmt = $pdo->prepare('UPDATE languages SET enabled = :enabled WHERE lang = :lang');
    $stmt->execute(['lang' => $lang, 'enabled' => 1]);
    log_admin_action($pdo, $userId, 'language_toggle', 'languages', ['lang' => $lang, 'enabled' => 1]);
    return ['ok' => true];
}

/**
 * Checks what translations / content pages are missing for a language
 * compared to the reference language.
 *
 * Returns ['translations' => [...], 'content_pages' => [...]]
 */
function admin_languages_check(PDO $pdo, string $lang): array
{
    $missing = ['translations' => [], 'content_pages' => []];

    // ── Translations ───────────────────────────────────────
    // All keys present in the reference language
    $stmt = $pdo->prepare(
        'SELECT translation_key FROM translations WHERE lang = :ref'
    );
    $stmt->execute(['ref' => REFERENCE_LANG]);
    $refKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Keys present for the target language
    $stmt = $pdo->prepare(
        'SELECT translation_key FROM translations WHERE lang = :lang'
    );
    $stmt->execute(['lang' => $lang]);
    $targetKeys = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($refKeys as $key) {
        if (!isset($targetKeys[$key])) {
            $missing['translations'][] = $key;
        }
    }

    // ── Content pages ──────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT page_key FROM content_pages WHERE lang = :ref'
    );
    $stmt->execute(['ref' => REFERENCE_LANG]);
    $refPages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare(
        'SELECT page_key FROM content_pages WHERE lang = :lang'
    );
    $stmt->execute(['lang' => $lang]);
    $targetPages = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($refPages as $pageKey) {
        if (!isset($targetPages[$pageKey])) {
            $missing['content_pages'][] = $pageKey;
        }
    }

    return $missing;
}

/**
 * Deletes a language.
 */
function admin_languages_delete(PDO $pdo, ?int $userId, string $lang): array
{
    $stmt = $pdo->prepare('DELETE FROM languages WHERE lang = :lang');
    $stmt->execute(['lang' => $lang]);
    log_admin_action($pdo, $userId, 'language_delete', 'languages', ['lang' => $lang]);
    return ['ok' => true];
}

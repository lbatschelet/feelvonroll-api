<?php
/**
 * Admin languages service for language CRUD.
 * Exports: admin_languages_list, admin_languages_upsert, admin_languages_toggle, admin_languages_delete.
 */

/**
 * Returns all languages.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_languages_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT lang, label, enabled FROM languages ORDER BY lang ASC');
    return $stmt->fetchAll();
}

/**
 * Creates or updates a language entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $lang
 * @param string $label
 * @param int|null $enabled
 * @return array
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
        'enabled' => $enabled ?? 1,
    ]);
    log_admin_action($pdo, $userId, 'language_upsert', 'languages', ['lang' => $lang, 'label' => $label]);
    return ['ok' => true];
}

/**
 * Toggles a language enabled flag.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $lang
 * @param int $enabled
 * @return array
 */
function admin_languages_toggle(PDO $pdo, ?int $userId, string $lang, int $enabled): array
{
    $stmt = $pdo->prepare('UPDATE languages SET enabled = :enabled WHERE lang = :lang');
    $stmt->execute(['lang' => $lang, 'enabled' => $enabled]);
    log_admin_action($pdo, $userId, 'language_toggle', 'languages', ['lang' => $lang, 'enabled' => $enabled]);
    return ['ok' => true];
}

/**
 * Deletes a language.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $lang
 * @return array
 */
function admin_languages_delete(PDO $pdo, ?int $userId, string $lang): array
{
    $stmt = $pdo->prepare('DELETE FROM languages WHERE lang = :lang');
    $stmt->execute(['lang' => $lang]);
    log_admin_action($pdo, $userId, 'language_delete', 'languages', ['lang' => $lang]);
    return ['ok' => true];
}

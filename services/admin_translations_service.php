<?php
/**
 * Admin translations service for translation CRUD.
 * Exports: admin_translations_upsert, admin_translations_delete.
 */

/**
 * Creates or updates a translation entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $key
 * @param string $lang
 * @param string $text
 * @return array
 */
function admin_translations_upsert(PDO $pdo, ?int $userId, string $key, string $lang, string $text): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO translations (translation_key, lang, text)
         VALUES (:translation_key, :lang, :text)
         ON DUPLICATE KEY UPDATE text = VALUES(text)'
    );
    $stmt->execute(['translation_key' => $key, 'lang' => $lang, 'text' => $text]);
    log_admin_action($pdo, $userId, 'translation_upsert', 'translations', ['key' => $key, 'lang' => $lang]);
    return ['ok' => true];
}

/**
 * Deletes a translation entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $key
 * @param string $lang
 * @return array
 */
function admin_translations_delete(PDO $pdo, ?int $userId, string $key, string $lang): array
{
    $stmt = $pdo->prepare('DELETE FROM translations WHERE translation_key = :key AND lang = :lang');
    $stmt->execute(['key' => $key, 'lang' => $lang]);
    log_admin_action($pdo, $userId, 'translation_delete', 'translations', ['key' => $key, 'lang' => $lang]);
    return ['ok' => true];
}

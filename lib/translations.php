<?php
/**
 * Translation helpers for fetching and merging translations.
 * Exports: fetch_translations.
 */

/**
 * Fetches translations for keys with optional fallback.
 *
 * @param PDO $pdo
 * @param string $lang
 * @param array $keys
 * @param string $fallback
 * @return array
 */
function fetch_translations(PDO $pdo, string $lang, array $keys, string $fallback = 'de'): array
{
    if (empty($keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT translation_key, lang, text
         FROM translations
         WHERE translation_key IN ($placeholders)
           AND (lang = ? OR lang = ?)"
    );
    $params = array_merge($keys, [$lang, $fallback]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $key = $row['translation_key'];
        if (!isset($result[$key]) || $row['lang'] === $lang) {
            $result[$key] = $row['text'];
        }
    }

    return $result;
}

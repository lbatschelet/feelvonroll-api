<?php
/**
 * Public translations service for translation lookups.
 * Exports: public_translations_list.
 */

/**
 * Returns translations for a language and prefix.
 *
 * @param PDO $pdo
 * @param string $lang
 * @param string|null $prefix
 * @return array
 */
function public_translations_list(PDO $pdo, string $lang, ?string $prefix): array
{
    $sql = 'SELECT translation_key, text FROM translations WHERE lang = :lang';
    $params = ['lang' => $lang];
    if ($prefix) {
        $sql .= ' AND translation_key LIKE :prefix';
        $params['prefix'] = $prefix . '%';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['translation_key']] = $row['text'];
    }
    return $result;
}

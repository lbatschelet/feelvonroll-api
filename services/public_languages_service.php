<?php
/**
 * Public languages service for enabled languages.
 * Exports: public_languages_list.
 */

/**
 * Returns enabled languages for public API.
 *
 * @param PDO $pdo
 * @return array
 */
function public_languages_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT lang, label FROM languages WHERE enabled = 1 ORDER BY label ASC');
    return $stmt->fetchAll();
}

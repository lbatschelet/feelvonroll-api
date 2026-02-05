<?php
/**
 * Admin options service for option CRUD.
 * Exports: admin_options_list, admin_options_upsert, admin_options_delete.
 */

/**
 * Returns options filtered by question.
 *
 * @param PDO $pdo
 * @param string|null $questionKey
 * @return array
 */
function admin_options_list(PDO $pdo, ?string $questionKey = null): array
{
    $params = [];
    $sql = 'SELECT question_key, option_key, sort, is_active FROM question_options';
    if ($questionKey) {
        $sql .= ' WHERE question_key = :question_key';
        $params['question_key'] = $questionKey;
    }
    $sql .= ' ORDER BY question_key ASC, sort ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Creates or updates an option.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param array $data
 * @return array
 */
function admin_options_upsert(PDO $pdo, ?int $userId, array $data): array
{
    $stmt = $pdo->prepare(
        'INSERT INTO question_options (question_key, option_key, sort, is_active)
         VALUES (:question_key, :option_key, :sort, :is_active)
         ON DUPLICATE KEY UPDATE sort = VALUES(sort), is_active = VALUES(is_active)'
    );
    $stmt->execute([
        'question_key' => $data['question_key'],
        'option_key' => $data['option_key'],
        'sort' => intval($data['sort'] ?? 0),
        'is_active' => intval($data['is_active'] ?? 1),
    ]);
    log_admin_action($pdo, $userId, 'option_upsert', 'question_options', [
        'question_key' => $data['question_key'],
        'option_key' => $data['option_key'],
    ]);
    return ['ok' => true];
}

/**
 * Deletes an option.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $questionKey
 * @param string $optionKey
 * @return array
 */
function admin_options_delete(PDO $pdo, ?int $userId, string $questionKey, string $optionKey): array
{
    $stmt = $pdo->prepare(
        'DELETE FROM question_options WHERE question_key = :question_key AND option_key = :option_key'
    );
    $stmt->execute(['question_key' => $questionKey, 'option_key' => $optionKey]);
    log_admin_action($pdo, $userId, 'option_delete', 'question_options', [
        'question_key' => $questionKey,
        'option_key' => $optionKey,
    ]);
    return ['ok' => true];
}

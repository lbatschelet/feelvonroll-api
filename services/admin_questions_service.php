<?php
/**
 * Admin questions service for question CRUD.
 * Exports: admin_questions_list, admin_questions_upsert, admin_questions_delete.
 */

/**
 * Returns all questions for admin view.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_questions_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM questions ORDER BY sort ASC, question_key ASC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['required'] = intval($row['required']);
        $row['sort'] = intval($row['sort']);
        $row['is_active'] = intval($row['is_active']);
        $row['config'] = $row['config'] ? json_decode($row['config'], true) : [];
    }
    return $rows;
}

/**
 * Creates or updates a question.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param array $question
 * @return array
 */
function admin_questions_upsert(PDO $pdo, ?int $userId, array $question): array
{
    $config = isset($question['config']) ? json_encode($question['config']) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO questions (question_key, type, required, sort, is_active, config)
         VALUES (:question_key, :type, :required, :sort, :is_active, :config)
         ON DUPLICATE KEY UPDATE type = VALUES(type), required = VALUES(required), sort = VALUES(sort),
         is_active = VALUES(is_active), config = VALUES(config)'
    );
    $stmt->execute([
        'question_key' => $question['question_key'],
        'type' => $question['type'],
        'required' => intval($question['required'] ?? 0),
        'sort' => intval($question['sort'] ?? 0),
        'is_active' => intval($question['is_active'] ?? 0),
        'config' => $config,
    ]);
    log_admin_action($pdo, $userId, 'question_upsert', 'questions', $question);
    return ['ok' => true];
}

/**
 * Deletes a question by key.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $questionKey
 * @return array
 */
function admin_questions_delete(PDO $pdo, ?int $userId, string $questionKey): array
{
    $stmt = $pdo->prepare('DELETE FROM questions WHERE question_key = :key');
    $stmt->execute(['key' => $questionKey]);
    log_admin_action($pdo, $userId, 'question_delete', 'questions', ['question_key' => $questionKey]);
    return ['ok' => true];
}

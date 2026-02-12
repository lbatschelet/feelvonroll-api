<?php
/**
 * Questionnaire configuration service (admin CRUD).
 * Single responsibility: manage questionnaires, slots, and slot-question assignments.
 *
 * Exports: admin_questionnaires_list, admin_questionnaires_upsert,
 *          admin_questionnaires_delete, admin_questionnaire_slots_save.
 */

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/audit.php';

/**
 * Lists all questionnaires with their slot count.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_questionnaires_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT q.*,
                (SELECT COUNT(*) FROM questionnaire_slots qs WHERE qs.questionnaire_id = q.id) AS slot_count
         FROM questionnaires q
         ORDER BY q.is_default DESC, q.name ASC'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Creates or updates a questionnaire.
 *
 * @param PDO      $pdo
 * @param int|null $adminUserId  For audit logging.
 * @param array    $data         Must contain: questionnaire_key, name. Optional: id, description, is_active.
 * @return array
 * @throws ApiError
 */
function admin_questionnaires_upsert(PDO $pdo, ?int $adminUserId, array $data): array
{
    $key = trim($data['questionnaire_key'] ?? '');
    $name = trim($data['name'] ?? '');

    if (!$key || !$name) {
        throw new ApiError('Key and name are required', 400);
    }

    $id = isset($data['id']) ? intval($data['id']) : null;
    $description = $data['description'] ?? null;
    $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;

    if ($id) {
        return update_questionnaire($pdo, $adminUserId, $id, $key, $name, $description, $isActive);
    }

    return create_questionnaire($pdo, $adminUserId, $key, $name, $description, $isActive);
}

/**
 * Deletes a questionnaire (cascades to slots and slot_questions).
 *
 * @param PDO      $pdo
 * @param int|null $adminUserId
 * @param int      $id
 * @return array
 * @throws ApiError
 */
function admin_questionnaires_delete(PDO $pdo, ?int $adminUserId, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM questionnaires WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new ApiError('Questionnaire not found', 404);
    }
    if (intval($row['is_default'])) {
        throw new ApiError('Cannot delete the default questionnaire', 400);
    }

    $pdo->prepare('DELETE FROM questionnaires WHERE id = :id')->execute(['id' => $id]);

    log_admin_action($pdo, $adminUserId, 'delete_questionnaire', "questionnaire:$id", [
        'key' => $row['questionnaire_key'],
    ]);

    return ['ok' => true];
}

/**
 * Saves slots (full replacement) for a questionnaire.
 * Deletes all existing slots and re-creates them from the input.
 *
 * @param PDO      $pdo
 * @param int|null $adminUserId
 * @param int      $questionnaireId
 * @param array    $slots  Each: { sort, mode, pool_count, required, questions: [question_key, ...] }
 * @return array
 * @throws ApiError
 */
function admin_questionnaire_slots_save(PDO $pdo, ?int $adminUserId, int $questionnaireId, array $slots): array
{
    // Verify questionnaire exists
    $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM questionnaires WHERE id = :id');
    $existsStmt->execute(['id' => $questionnaireId]);
    $exists = $existsStmt->fetchColumn();
    if (!intval($exists)) {
        throw new ApiError('Questionnaire not found', 404);
    }

    $pdo->beginTransaction();
    try {
        // Delete existing slot_questions for this questionnaire's slots
        $pdo->prepare(
            'DELETE FROM questionnaire_slot_questions
             WHERE slot_id IN (SELECT id FROM questionnaire_slots WHERE questionnaire_id = :qid)'
        )->execute(['qid' => $questionnaireId]);

        // Delete existing slots
        $pdo->prepare('DELETE FROM questionnaire_slots WHERE questionnaire_id = :qid')
            ->execute(['qid' => $questionnaireId]);

        // Insert new slots
        $slotStmt = $pdo->prepare(
            'INSERT INTO questionnaire_slots (questionnaire_id, sort, mode, pool_count, required)
             VALUES (:qid, :sort, :mode, :pool_count, :required)'
        );

        $sqStmt = $pdo->prepare(
            'INSERT INTO questionnaire_slot_questions (slot_id, question_key)
             VALUES (:slot_id, :question_key)'
        );

        foreach ($slots as $slot) {
            $slotStmt->execute([
                'qid' => $questionnaireId,
                'sort' => intval($slot['sort'] ?? 0),
                'mode' => $slot['mode'] ?? 'fixed',
                'pool_count' => intval($slot['pool_count'] ?? 1),
                'required' => ($slot['required'] ?? false) ? 1 : 0,
            ]);
            $slotId = $pdo->lastInsertId();

            foreach ($slot['questions'] ?? [] as $questionKey) {
                $sqStmt->execute([
                    'slot_id' => $slotId,
                    'question_key' => $questionKey,
                ]);
            }
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    log_admin_action($pdo, $adminUserId, 'save_questionnaire_slots', "questionnaire:$questionnaireId", [
        'slot_count' => count($slots),
    ]);

    return ['ok' => true];
}

// ── Internal helpers ────────────────────────────────────────────

function create_questionnaire(PDO $pdo, ?int $adminUserId, string $key, string $name, ?string $description, int $isActive): array
{
    // Check unique key
    $existing = $pdo->prepare('SELECT COUNT(*) FROM questionnaires WHERE questionnaire_key = :key');
    $existing->execute(['key' => $key]);
    if (intval($existing->fetchColumn()) > 0) {
        throw new ApiError("Questionnaire key already exists: $key", 400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO questionnaires (questionnaire_key, name, description, is_default, is_active)
         VALUES (:key, :name, :description, 0, :is_active)'
    );
    $stmt->execute([
        'key' => $key,
        'name' => $name,
        'description' => $description,
        'is_active' => $isActive,
    ]);

    $id = $pdo->lastInsertId();

    log_admin_action($pdo, $adminUserId, 'create_questionnaire', "questionnaire:$id", [
        'key' => $key,
        'name' => $name,
    ]);

    return ['ok' => true, 'id' => intval($id)];
}

function update_questionnaire(PDO $pdo, ?int $adminUserId, int $id, string $key, string $name, ?string $description, int $isActive): array
{
    // Check unique key (exclude self)
    $existing = $pdo->prepare('SELECT COUNT(*) FROM questionnaires WHERE questionnaire_key = :key AND id != :id');
    $existing->execute(['key' => $key, 'id' => $id]);
    if (intval($existing->fetchColumn()) > 0) {
        throw new ApiError("Questionnaire key already exists: $key", 400);
    }

    $stmt = $pdo->prepare(
        'UPDATE questionnaires
         SET questionnaire_key = :key, name = :name, description = :description, is_active = :is_active
         WHERE id = :id'
    );
    $stmt->execute([
        'key' => $key,
        'name' => $name,
        'description' => $description,
        'is_active' => $isActive,
        'id' => $id,
    ]);

    log_admin_action($pdo, $adminUserId, 'update_questionnaire', "questionnaire:$id", [
        'key' => $key,
        'name' => $name,
    ]);

    return ['ok' => true, 'id' => $id];
}

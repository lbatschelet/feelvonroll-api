<?php
/**
 * Admin pins service for listing and approval updates.
 * Exports: admin_pins_list, admin_pins_export_rows, admin_pins_update_approval, admin_pins_delete.
 */

/**
 * Returns all pins for admin view.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_pins_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id AND pin_reasons.question_key = 'reasons'
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    $rows = $stmt->fetchAll();
    return array_map('normalize_pin_row', $rows);
}

/**
 * Returns raw pin rows for CSV export.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_pins_export_rows(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id AND pin_reasons.question_key = 'reasons'
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Updates approval state for a list of pins.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param array $ids
 * @param int $approved
 * @return array
 */
function admin_pins_update_approval(PDO $pdo, ?int $userId, array $ids, int $approved): array
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$approved], array_map('intval', $ids));
    $stmt = $pdo->prepare("UPDATE pins SET approved = ? WHERE id IN ($placeholders)");
    $stmt->execute($params);
    log_admin_action($pdo, $userId, 'pin_update_approval', 'pins', ['ids' => $ids, 'approved' => $approved]);
    return ['updated' => $stmt->rowCount()];
}

/**
 * Deletes pins by id.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param array $ids
 * @return array
 */
function admin_pins_delete(PDO $pdo, ?int $userId, array $ids): array
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_map('intval', $ids);
    $stmt = $pdo->prepare("DELETE FROM pins WHERE id IN ($placeholders)");
    $stmt->execute($params);
    log_admin_action($pdo, $userId, 'pin_delete', 'pins', ['ids' => $ids]);
    return ['deleted' => $stmt->rowCount()];
}

<?php
/**
 * Public pins service for listing and creating pins.
 * Exports: public_pins_list, public_pins_create, normalize_percent.
 */

/**
 * Returns public pins, optionally filtered by floor.
 *
 * @param PDO $pdo
 * @param int|null $floor
 * @return array
 */
function public_pins_list(PDO $pdo, ?int $floor): array
{
    $params = [];
    $where = 'WHERE pins.approved = 1';
    if ($floor !== null) {
        $where .= ' AND pins.floor_index = :floor';
        $params['floor'] = $floor;
    }
    $stmt = $pdo->prepare(
        "SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id AND pin_reasons.question_key = 'reasons'
         $where
         GROUP BY pins.id
         ORDER BY pins.created_at DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    return array_map('normalize_pin_row', $rows);
}

/**
 * Creates a pin from public submission.
 *
 * @param PDO $pdo
 * @param array $data
 * @return array
 * @throws ApiError
 */
function public_pins_create(PDO $pdo, array $data): array
{
    $floorIndex = isset($data['floor_index']) ? intval($data['floor_index']) : null;
    $x = isset($data['x']) ? floatval($data['x']) : null;
    $y = isset($data['y']) ? floatval($data['y']) : null;
    $z = isset($data['z']) ? floatval($data['z']) : null;

    $answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : $data;
    $wellbeing = isset($answers['wellbeing']) ? normalize_percent($answers['wellbeing']) : null;
    $reasons = isset($answers['reasons']) && is_array($answers['reasons']) ? $answers['reasons'] : [];
    $note = isset($answers['note']) ? trim($answers['note']) : '';
    $groupKey = isset($answers['group']) ? $answers['group'] : null;
    if (is_array($groupKey)) {
        $groupKey = $groupKey[0] ?? null;
    }
    if ($groupKey !== null) {
        $groupKey = trim((string)$groupKey);
        if ($groupKey === '') {
            $groupKey = null;
        }
    }

    if ($floorIndex === null || $x === null || $y === null || $z === null || $wellbeing === null) {
        json_error('Missing required fields', 400);
    }

    if (!empty($reasons)) {
        $placeholders = implode(',', array_fill(0, count($reasons), '?'));
        $check = $pdo->prepare(
            "SELECT option_key FROM question_options
             WHERE question_key = 'reasons' AND option_key IN ($placeholders) AND is_active = 1"
        );
        $check->execute($reasons);
        $existing = $check->fetchAll(PDO::FETCH_COLUMN);
        if (count($existing) !== count(array_unique($reasons))) {
            json_error('Invalid reasons', 400);
        }
    }

    if ($groupKey !== null) {
        $check = $pdo->prepare(
            "SELECT option_key FROM question_options
             WHERE question_key = 'group' AND option_key = :key AND is_active = 1"
        );
        $check->execute(['key' => $groupKey]);
        $existing = $check->fetchColumn();
        if (!$existing) {
            json_error('Invalid group', 400);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pins (floor_index, position_x, position_y, position_z, wellbeing, note, group_key)
         VALUES (:floor_index, :position_x, :position_y, :position_z, :wellbeing, :note, :group_key)'
    );
    $stmt->execute([
        'floor_index' => $floorIndex,
        'position_x' => $x,
        'position_y' => $y,
        'position_z' => $z,
        'wellbeing' => $wellbeing,
        'note' => $note,
        'group_key' => $groupKey,
    ]);

    $id = $pdo->lastInsertId();
    if (!empty($reasons)) {
        $insert = $pdo->prepare(
            'INSERT INTO pin_reasons (pin_id, question_key, reason_key)
             VALUES (:pin_id, :question_key, :reason_key)'
        );
        foreach ($reasons as $reasonKey) {
            $insert->execute([
                'pin_id' => $id,
                'question_key' => 'reasons',
                'reason_key' => $reasonKey,
            ]);
        }
    }

    $stmt = $pdo->prepare(
        'SELECT pins.*, GROUP_CONCAT(pin_reasons.reason_key) AS reason_keys
         FROM pins
         LEFT JOIN pin_reasons ON pin_reasons.pin_id = pins.id AND pin_reasons.question_key = "reasons"
         WHERE pins.id = :id
         GROUP BY pins.id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return normalize_pin_row($row);
}

/**
 * Normalizes percent input to 0-100 with 2 decimals.
 *
 * @param mixed $value
 * @return float|null
 */
function normalize_percent($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $numeric = floatval($value);
    $clamped = min(max($numeric, 0), 100);
    return round($clamped, 2);
}

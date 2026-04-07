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

    $stationKey = isset($data['station_key']) ? trim($data['station_key']) : null;
    if ($stationKey === '') {
        $stationKey = null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pins (floor_index, position_x, position_y, position_z, wellbeing, note, group_key, station_key)
         VALUES (:floor_index, :position_x, :position_y, :position_z, :wellbeing, :note, :group_key, :station_key)'
    );
    $stmt->execute([
        'floor_index' => $floorIndex,
        'position_x' => $x,
        'position_y' => $y,
        'position_z' => $z,
        'wellbeing' => $wellbeing,
        'note' => $note,
        'group_key' => $groupKey,
        'station_key' => $stationKey,
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

    // Store generic answers in pin_answers (for non-hardcoded questions)
    $genericAnswers = isset($data['generic_answers']) && is_array($data['generic_answers'])
        ? $data['generic_answers'] : [];
    if (!empty($genericAnswers)) {
        public_pins_store_generic_answers($pdo, intval($id), $genericAnswers);
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

/**
 * @param PDO   $pdo
 * @param int   $pinId
 * @param array $genericAnswers question_key => value
 */
function public_pins_store_generic_answers(PDO $pdo, int $pinId, array $genericAnswers): void
{
    $answerStmt = $pdo->prepare(
        'INSERT INTO pin_answers (pin_id, question_key, answer_text, answer_numeric)
         VALUES (:pin_id, :question_key, :answer_text, :answer_numeric)'
    );
    foreach ($genericAnswers as $qKey => $value) {
        $meta = public_pins_load_question_meta($pdo, $qKey);
        if (!$meta) {
            json_error("Unknown question: $qKey", 400);
        }
        $type = $meta['type'];
        $config = $meta['config'];
        $answerText = null;
        $answerNumeric = null;

        if ($type === 'influence') {
            $answerText = public_pins_normalize_influence_json($pdo, $qKey, $value, $config);
        } elseif (is_string($value)) {
            $answerText = $value;
        } elseif (is_numeric($value)) {
            $answerNumeric = floatval($value);
        } else {
            json_error("Invalid answer for $qKey", 400);
        }

        $answerStmt->execute([
            'pin_id' => $pinId,
            'question_key' => $qKey,
            'answer_text' => $answerText,
            'answer_numeric' => $answerNumeric,
        ]);
    }
}

/**
 * @return array{type: string, config: array}|null
 */
function public_pins_load_question_meta(PDO $pdo, string $qKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT type, config FROM questions WHERE question_key = :k LIMIT 1'
    );
    $stmt->execute(['k' => $qKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'type' => $row['type'],
        'config' => $row['config'] ? json_decode($row['config'], true) : [],
    ];
}

/**
 * @param mixed $value
 */
function public_pins_normalize_influence_json(PDO $pdo, string $qKey, $value, array $config): string
{
    if (!is_array($value)) {
        json_error("Invalid answer for $qKey", 400);
    }
    $stmt = $pdo->prepare(
        'SELECT option_key FROM question_options WHERE question_key = :k AND is_active = 1'
    );
    $stmt->execute(['k' => $qKey]);
    $valid = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $validSet = array_flip($valid);

    $min = isset($config['min']) ? floatval($config['min']) : -1.0;
    $max = isset($config['max']) ? floatval($config['max']) : 1.0;

    $out = [];
    foreach ($value as $optKey => $num) {
        if (!is_string($optKey) && !is_int($optKey)) {
            json_error("Invalid influence keys for $qKey", 400);
        }
        $optKeyStr = (string)$optKey;
        if (!isset($validSet[$optKeyStr])) {
            json_error("Invalid option for $qKey: $optKeyStr", 400);
        }
        if (!is_numeric($num)) {
            json_error("Invalid influence value for $optKeyStr", 400);
        }
        $v = floatval($num);
        if ($v < $min || $v > $max) {
            json_error("Influence value out of range for $optKeyStr", 400);
        }
        $out[$optKeyStr] = round($v, 4);
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

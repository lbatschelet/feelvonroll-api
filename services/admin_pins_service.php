<?php
/**
 * Admin pins service for listing and approval updates.
 * Exports: admin_pins_list, admin_pins_export_rows, admin_pins_export_header_keys,
 *          admin_pins_export_long_rows, admin_pins_export_long_header_keys,
 *          admin_pins_update_approval, admin_pins_delete.
 */

require_once __DIR__ . '/../lib/lv95_affine.php';

/**
 * CSV header order for wide export.
 * Dynamic answer columns are generated as answer__{question_key}.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, string>
 */
function admin_pins_export_header_keys(array $rows = []): array
{
    $base = [
        'id',
        'floor_index',
        'scene_world_x',
        'scene_world_y',
        'scene_world_z',
        'lv95_e',
        'lv95_n',
        'lv95_calibration_id',
        'questionnaire_key',
        'station_key',
    ];

    $dynamic = [];
    foreach ($rows as $row) {
        foreach ($row as $key => $_value) {
            if (strpos($key, 'answer__') === 0) {
                $dynamic[$key] = true;
            }
        }
    }
    $dynamicKeys = array_keys($dynamic);
    sort($dynamicKeys, SORT_STRING);

    $tail = [
        'asked_questions',
        'answers_json',
        'status',
        'approved',
        'created_at',
        'updated_at',
    ];

    return array_merge($base, $dynamicKeys, $tail);
}

/**
 * Long CSV header: one row per (pin, question_key) with asked/answered flags.
 */
function admin_pins_export_long_header_keys(): array
{
    return [
        'pin_id',
        'station_key',
        'questionnaire_key',
        'floor_index',
        'created_at',
        'question_key',
        'asked',
        'answered',
        'answer_kind',
        'answer_text',
        'answer_numeric',
        'answer_json',
    ];
}

/**
 * Loads active LV95 calibration affine (if table exists and a row is active).
 *
 * @return array{ id: int|null, affine: array|null }
 */
function admin_pins_load_active_lv95_context(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT id, points, params FROM lv95_calibrations WHERE is_active = 1 ORDER BY id DESC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['id' => null, 'affine' => null];
        }
        $affine = lv95_affine_from_calibration_row($row);
        return ['id' => intval($row['id']), 'affine' => $affine];
    } catch (Throwable $e) {
        return ['id' => null, 'affine' => null];
    }
}

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
    $pins = array_map('normalize_pin_row', $rows);
    admin_pins_attach_asked_questions($pdo, $pins);
    admin_pins_attach_generic_answers($pdo, $pins);
    return $pins;
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pins = array_map('normalize_pin_row', $rows);
    admin_pins_attach_asked_questions($pdo, $pins);
    admin_pins_attach_generic_answers($pdo, $pins);

    $lv95Ctx = admin_pins_load_active_lv95_context($pdo);
    $lv95Id = $lv95Ctx['id'];
    $affine = $lv95Ctx['affine'];

    $out = [];
    $allAnswerColumnKeys = [];
    foreach ($pins as $row) {
        $val = array_key_exists('approved', $row) ? intval($row['approved']) : 0;
        $status = $val === 1 ? 'approved' : ($val === -1 ? 'rejected' : 'pending');

        $sx = floatval($row['position_x']);
        $sy = floatval($row['position_y']);
        $sz = floatval($row['position_z']);

        $lv95E = '';
        $lv95N = '';
        $lv95CalId = '';
        if ($affine !== null && $lv95Id !== null) {
            $en = lv95_apply_scene_xz_to_lv95($affine, $sx, $sz);
            $lv95E = round($en['e'], 4);
            $lv95N = round($en['n'], 4);
            $lv95CalId = $lv95Id;
        }

        $askedQuestions = isset($row['asked_questions']) && is_array($row['asked_questions'])
            ? $row['asked_questions']
            : [];
        $answersByKey = admin_pins_collect_answers_from_pin($row);
        $answerKeys = array_unique(array_merge(array_keys($answersByKey), $askedQuestions));
        sort($answerKeys, SORT_STRING);
        foreach ($answerKeys as $qk) {
            $allAnswerColumnKeys[$qk] = true;
        }

        $entry = [
            'id' => intval($row['id']),
            'floor_index' => intval($row['floor_index']),
            'scene_world_x' => $sx,
            'scene_world_y' => $sy,
            'scene_world_z' => $sz,
            'lv95_e' => $lv95E,
            'lv95_n' => $lv95N,
            'lv95_calibration_id' => $lv95CalId,
            'questionnaire_key' => $row['questionnaire_key'] ?? 'default',
            'station_key' => $row['station_key'] ?? '',
            'asked_questions' => implode(',', $askedQuestions),
            'answers_json' => json_encode((object)$answersByKey, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'approved' => $val,
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
        ];

        foreach ($answerKeys as $qk) {
            $csvKey = 'answer__' . $qk;
            $entry[$csvKey] = array_key_exists($qk, $answersByKey)
                ? admin_pins_format_answer_for_csv($answersByKey[$qk])
                : '';
        }

        $out[] = $entry;
    }

    $dynamicKeys = array_keys($allAnswerColumnKeys);
    sort($dynamicKeys, SORT_STRING);
    foreach ($out as &$entry) {
        foreach ($dynamicKeys as $qk) {
            $csvKey = 'answer__' . $qk;
            if (!array_key_exists($csvKey, $entry)) {
                $entry[$csvKey] = '';
            }
        }
    }
    unset($entry);

    return $out;
}

/**
 * Long CSV export rows: one row per (pin, question_key) for asked questions.
 */
function admin_pins_export_long_rows(PDO $pdo): array
{
    // Base pin meta.
    $pins = admin_pins_list($pdo);
    if (empty($pins)) return [];

    $out = [];
    foreach ($pins as $pin) {
        $pid = intval($pin['id']);
        $answersByKey = admin_pins_collect_answers_from_pin($pin);
        $asked = isset($pin['asked_questions']) && is_array($pin['asked_questions']) ? $pin['asked_questions'] : [];
        // Use asked snapshot as source of truth; for legacy rows without snapshot, fall back to present answers.
        $askedSet = !empty($asked) ? array_values(array_unique($asked)) : array_keys($answersByKey);
        sort($askedSet, SORT_STRING);

        foreach ($askedSet as $qk) {
            $qk = (string)$qk;
            if ($qk === '') continue;
            $value = array_key_exists($qk, $answersByKey) ? $answersByKey[$qk] : null;
            $answered = admin_pins_is_answer_filled($value) ? 1 : 0;
            $kind = '';
            $answerText = '';
            $answerNumeric = '';
            $answerJson = '';

            if (is_array($value)) {
                $kind = 'json';
                $answerJson = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                $kind = 'numeric';
                $answerNumeric = $value;
            } elseif (is_string($value)) {
                $kind = 'text';
                $answerText = $value;
            }

            $out[] = [
                'pin_id' => $pid,
                'station_key' => $pin['station_key'] ?? '',
                'questionnaire_key' => $pin['questionnaire_key'] ?? 'default',
                'floor_index' => $pin['floor_index'] ?? '',
                'created_at' => $pin['created_at'] ?? '',
                'question_key' => $qk,
                'asked' => 1,
                'answered' => $answered,
                'answer_kind' => $kind,
                'answer_text' => $answerText,
                'answer_numeric' => $answerNumeric,
                'answer_json' => $answerJson,
            ];
        }
    }
    return $out;
}

function admin_pins_attach_asked_questions(PDO $pdo, array &$pins): void
{
    if (empty($pins)) return;
    $ids = array_map(fn($p) => intval($p['id']), $pins);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT pin_id, question_key
         FROM pin_questionnaire_questions
         WHERE pin_id IN ($placeholders)
         ORDER BY slot_sort ASC, question_key ASC"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byPin = [];
    foreach ($rows as $r) {
        $pid = intval($r['pin_id'] ?? 0);
        $qk = (string)($r['question_key'] ?? '');
        if ($pid <= 0 || $qk === '') continue;
        $byPin[$pid][] = $qk;
    }
    foreach ($pins as &$p) {
        $pid = intval($p['id']);
        $p['asked_questions'] = $byPin[$pid] ?? [];
    }
}

function admin_pins_attach_generic_answers(PDO $pdo, array &$pins): void
{
    if (empty($pins)) return;
    $ids = array_map(fn($p) => intval($p['id']), $pins);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT pin_id, question_key, answer_text, answer_numeric
         FROM pin_answers
         WHERE pin_id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byPin = [];
    foreach ($rows as $r) {
        $pid = intval($r['pin_id'] ?? 0);
        $qk = (string)($r['question_key'] ?? '');
        if ($pid <= 0 || $qk === '') continue;
        $val = null;
        if (isset($r['answer_text']) && $r['answer_text'] !== null && $r['answer_text'] !== '') {
            $val = $r['answer_text'];
        } elseif (isset($r['answer_numeric']) && $r['answer_numeric'] !== null && $r['answer_numeric'] !== '') {
            $val = floatval($r['answer_numeric']);
        }
        $byPin[$pid][$qk] = $val;
    }
    foreach ($pins as &$p) {
        $pid = intval($p['id']);
        $p['generic_answers'] = $byPin[$pid] ?? new stdClass();
    }
}

/**
 * Builds a normalized answer map for one pin.
 * Includes legacy fields and generic pin_answers values.
 *
 * @param array<string, mixed> $pin
 * @return array<string, mixed>
 */
function admin_pins_collect_answers_from_pin(array $pin): array
{
    $answers = [];

    if (array_key_exists('wellbeing', $pin) && $pin['wellbeing'] !== null && $pin['wellbeing'] !== '') {
        $answers['wellbeing'] = floatval($pin['wellbeing']);
    }

    $group = isset($pin['group_key']) ? trim((string)$pin['group_key']) : '';
    if ($group !== '') {
        $answers['group'] = $group;
    }

    $note = isset($pin['note']) ? trim((string)$pin['note']) : '';
    if ($note !== '') {
        $answers['note'] = $note;
    }

    $reasonKeys = [];
    if (array_key_exists('reason_keys', $pin) && $pin['reason_keys'] !== null && $pin['reason_keys'] !== '') {
        $reasonKeys = array_values(array_filter(
            array_map('trim', explode(',', (string)$pin['reason_keys'])),
            fn($v) => $v !== ''
        ));
    } elseif (isset($pin['reasons']) && is_array($pin['reasons'])) {
        $reasonKeys = array_values(array_filter(
            array_map(fn($v) => trim((string)$v), $pin['reasons']),
            fn($v) => $v !== ''
        ));
    }
    if (!empty($reasonKeys)) {
        $answers['reasons'] = array_values(array_unique($reasonKeys));
    }

    $generic = $pin['generic_answers'] ?? null;
    if (is_object($generic)) {
        $generic = (array)$generic;
    }
    if (is_array($generic)) {
        foreach ($generic as $qk => $value) {
            $qk = trim((string)$qk);
            if ($qk === '') continue;
            $answers[$qk] = $value;
        }
    }

    return $answers;
}

/**
 * @param mixed $value
 * @return mixed
 */
function admin_pins_format_answer_for_csv($value)
{
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if ($value === null) {
        return '';
    }
    return $value;
}

/**
 * @param mixed $value
 */
function admin_pins_is_answer_filled($value): bool
{
    if ($value === null) return false;
    if (is_string($value)) return trim($value) !== '';
    if (is_array($value)) return !empty($value);
    return true;
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

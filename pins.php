<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once __DIR__ . '/helpers.php';

$pdo = require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $floor = isset($_GET['floor']) ? intval($_GET['floor']) : null;
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
    $result = array_map('normalize_pin_row', $rows);
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $floorIndex = isset($data['floor_index']) ? intval($data['floor_index']) : null;
    $x = isset($data['x']) ? floatval($data['x']) : null;
    $y = isset($data['y']) ? floatval($data['y']) : null;
    $z = isset($data['z']) ? floatval($data['z']) : null;

    $answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : $data;
    $wellbeing = isset($answers['wellbeing']) ? intval($answers['wellbeing']) : null;
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
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
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
            http_response_code(400);
            echo json_encode(['error' => 'Invalid reasons']);
            exit;
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
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group']);
            exit;
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
    $row = normalize_pin_row($row);
    echo json_encode($row);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
